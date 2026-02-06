<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\Documento;
use App\Models\DocumentoInterno;
use App\Models\Empresa;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MovimentoStockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $movimentoStockQuery = MovimentoStock::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $movimentoStockQuery->where(function ($query) use ($search) {
                // Pesquisa pelo nome do produto
                $query->whereHas('produto', function ($q) use ($search) {
                    $q->where('nome', 'like', '%' . $search . '%');
                })
                    // Pesquisa pelo nome da armazem relacionada ao produto
                    ->orWhereHas('armazem', function ($q) use ($search) {
                        $q->where('nome', $search);
                    });
            });
        }

        $movimentoStock = $movimentoStockQuery->with(['produto.categoria', 'armazem', 'utilizador'])->orderByDesc('id')->paginate($per_page);

        return response()->json($movimentoStock);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'tipo_movimento' => ['required', Rule::in([
                'entrada',
                'saida',
                'transferencia',
                'nota_quebra',
                'entrada_inventario',
                'saida_inventario'
            ])],
            'armazem_id' => ['nullable', 'exists:armazens,id'],
            'utilizador_id' => ['nullable', 'exists:utilizadores,id'],
            'itens' => ['nullable', 'array'],
            'itens.*.produto_id' => ['required', 'exists:produtos,id'],
            'itens.*.quantidade' => ['required', 'integer'],
            'itens.*.observacao' => ['nullable', 'string'],
            'itens.*.armazem_origem_id' => ['nullable', 'exists:armazens,id'],
            'itens.*.armazem_destino_id' => ['nullable', 'exists:armazens,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }


        try {

            DB::beginTransaction();

            $result = null;

            if ($data['tipo_movimento'] === 'nota_quebra') {

                $documento = $this->storeDocumentNotaDeQuebra($request);
                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    $movimentos[] = $documento->movimentosStock()->create([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'nota_quebra',
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'documento_relacionado_id' => $documento->id,
                    ]);
                }

                $result = [
                    'message' => 'Movimentos de stock para Nota de Quebra registrados com sucesso',
                    'data' => $documento
                ];
            } elseif ($data['tipo_movimento'] === 'transferencia') {

                $documento = $this->storeDocumentTransferencia($request);

                $movimentos = [];

                foreach ($data['itens'] as $item) {

                    $movimentos[] = $documento->movimentosStock()->create([
                        'armazem_id' => $data['armazem_origem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'saida',
                        'origem_movimento' => $data['armazem_origem'],
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                    ]);

                    $movimentos[] = $documento->movimentosStock()->create([
                        'armazem_id' => $data['armazem_destino_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'entrada',
                        'origem_movimento' => $data['armazem_destino'],
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                    ]);
                }

                $result = [
                    'message' => 'Transferência realizada com sucesso',
                    'data' => $documento
                ];
            } elseif ($data['tipo_movimento'] === 'saida_inventario' || $data['tipo_movimento'] === 'entrada_inventario') {

                $documento = $this->storeDocumentInventario($request, $data['tipo_movimento']);

                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    $movimentos[] = $documento->movimentosStock()->create([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => $data['tipo_movimento'],
                        'observacao' => $item['observacao'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'documento_relacionado_id' => $documento->id,
                    ]);
                }

                $result = [
                    'message' => 'Movimentação registrada com sucesso',
                    'data' => $documento
                ];
            } else {
                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    $movimentos[] = MovimentoStock::create([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => $data['tipo_movimento'],
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => 'Manual',
                        'documento_relacionado_id' => null,
                    ]);
                }

                $result = [
                    'message' => 'Movimentação registrada com sucesso',
                    'data' => $movimentos
                ];
            }

            //Atualiza na tabela Stock
            $stock = Stock::where('produto_id', $item['produto_id'])->first();

            if ($stock) {
                if ($this->resolveOperacao($data['tipo_movimento']) === '+') {
                    $stock->increment('stock_atual', $item['quantidade']);
                } else {
                    $stock->decrement('stock_atual', $item['quantidade']);
                }
            }

            DB::commit();

            return response()->json($result, 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao processar movimentação de stock',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    function resolveOperacao(string $tipo): string
    {
        return in_array($tipo, [
            'saida',
            'saida_inventario',
            'nota_quebra'
        ]) ? '-' : '+';
    }

    public function storeDocumentNotaDeQuebra(Request $request)
    {

        $numFatura = app(DocumentoController::class)->gerarNumeroDocumento(
            'NQ',
            $request->input("empresa_id"),
        );

        $data = $request->all();

        $itens = $data['itens'] ?? [];

        $totalGeral = 0;
        foreach ($itens as $item) {
            $totalItem = ($item['preco_custo'] * $item['quantidade']);
            $totalGeral += $totalItem;
        }

        $empresa = Empresa::find($request->input("empresa_id"));

        try {

            DB::beginTransaction();

            $documento = DocumentoInterno::create([
                'tipo_nome' => 'Nota de Quebra',
                'tipo_sigla' => 'NQ',
                'estado_documento' => 'emitido',
                'num_fatura' => $numFatura,
                'via' => 'original',

                "empresa_id" => $empresa->id,
                "empresa_nome" => $empresa->nome,
                "empresa_nif" => $empresa->nif,
                "empresa_telefone" => $empresa->telefone,
                "empresa_email" => $empresa->email,
                "empresa_endereco" => $empresa->endereco,

                "cliente_id" => "0",

                "data_emissao" => now(),

                "taxa_iva" => "0",
                "valor_iva" => "0",
                "retencao" => "0",

                "estado" => "emitido",

                "hash" => "",

                "desconto_tipo" => "0",
                "desconto_total" => "0",
                "valor_transporte" => "0",
                "total_sem_desconto" => "0",
                "total_impostos" => "0",
                "total_geral" => $totalGeral,
                "troco" => "0",

                "utilizador_id" => $request["utilizador_id"],
                "utilizador" => $request["utilizador"],

            ]);

            // Criação dos itens
            $itens = [];
            foreach ($request["itens"] as $item) {

                $totalItem = ($item["preco_custo"] * $item["quantidade"]);
                $produto = Produto::find($item["produto_id"]);

                $itens[] = [
                    "documento_id" => $documento->id,
                    "produto_nome" => $produto->nome,
                    "produto_codigo" => $produto->codigo_produto,
                    "preco_unitario" => $item["preco_custo"],
                    "descricao" => $produto->descricao,
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => 0,
                    "desconto_fixo" => 0,
                    "iva_percent" => 0,
                    "imposto_taxa_id" => null,
                    "codigo_iva" => null,
                    "tipo_id" => $produto->tipo_id,
                    "motivo_isencao" => null,
                    "total_sem_desconto" => 0,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $documento->itens()->createMany($itens);

            DB::commit();

            return $documento;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao criar documento de nota de quebra',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function storeDocumentInventario(Request $request, string $tipo)
    {

        $numFatura = app(DocumentoController::class)->gerarNumeroDocumento(
            $tipo === 'entrada_inventario' ? 'EI' : 'SI',
            $request->input("empresa_id"),
        );

        $data = $request->all();

        $itens = $data['itens'] ?? [];

        $totalGeral = 0;
        foreach ($itens as $item) {
            $totalItem = ($item['preco_custo'] * $item['quantidade']);
            $totalGeral += $totalItem;
        }

        $empresa = Empresa::find($request->input("empresa_id"));

        try {

            DB::beginTransaction();

            $documento = DocumentoInterno::create([
                'tipo_nome' => $tipo === 'entrada_inventario' ? 'Entrada de Inventário' : 'Saída de Inventário',
                'tipo_sigla' => $tipo === 'entrada_inventario' ? 'EI' : 'SI',
                // 'estado_documento' => 'emitido',
                'num_fatura' => $numFatura,
                'via' => 'original',

                "empresa_id" => $empresa->id,
                "empresa_nome" => $empresa->nome,
                "empresa_nif" => $empresa->nif,
                "empresa_telefone" => $empresa->telefone,
                "empresa_email" => $empresa->email,
                "empresa_endereco" => $empresa->endereco,

                "data_emissao" => now(),

                "taxa_iva" => "0",
                "valor_iva" => "0",
                "retencao" => "0",

                "estado" => "emitido",

                "hash" => "",

                "desconto_tipo" => "0",
                "desconto_total" => "0",
                "valor_transporte" => "0",
                "total_sem_desconto" => "0",
                "total_impostos" => "0",
                "total_geral" => $totalGeral,
                "troco" => "0",

                "utilizador_id" => $request["utilizador_id"],
                "utilizador" => $request["utilizador"],

            ]);

            // Criação dos itens
            $itens = [];
            foreach ($request["itens"] as $item) {

                $totalItem = ($item["preco_custo"] * $item["quantidade"]);
                $produto = Produto::find($item["produto_id"]);

                $itens[] = [
                    "documento_id" => $documento->id,
                    "produto_nome" => $produto->nome,
                    "produto_codigo" => $produto->codigo_produto,
                    "preco_unitario" => $item["preco_custo"],
                    "descricao" => $produto->descricao,
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => 0,
                    "desconto_fixo" => 0,
                    "iva_percent" => 0,
                    "imposto_taxa_id" => null,
                    "codigo_iva" => null,
                    "tipo_id" => $produto->tipo_id,
                    "motivo_isencao" => null,
                    "total_sem_desconto" => 0,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $documento->itens()->createMany($itens);

            DB::commit();

            return $documento;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao criar documento de inventário',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function storeDocumentTransferencia(Request $request)
    {

        $numFatura = app(DocumentoController::class)->gerarNumeroDocumento(
            'TR',
            $request->input("empresa_id"),
        );

        $data = $request->all();

        $itens = $data['itens'] ?? [];

        $totalGeral = 0;
        foreach ($itens as $item) {
            $totalItem = ($item['preco_custo'] * $item['quantidade']);
            $totalGeral += $totalItem;
        }

        $empresa = Empresa::find($request->input("empresa_id"));

        $armazemOrigem = Armazem::find($request["armazem_origem_id"]);
        $armazemDestino = Armazem::find($request["armazem_destino_id"]);


        try {

            DB::beginTransaction();

            $documento = DocumentoInterno::create([
                'tipo_nome' => 'Transferência',
                'tipo_sigla' => 'TR',
                'estado_documento' => 'emitido',
                'num_fatura' => $numFatura,
                'via' => 'original',

                "empresa_id" => $empresa->id,
                "empresa_nome" => $empresa->nome,
                "empresa_nif" => $empresa->nif,
                "empresa_telefone" => $empresa->telefone,
                "empresa_email" => $empresa->email,
                "empresa_endereco" => $empresa->endereco,

                "cliente_id" => "0",

                "data_emissao" => now(),

                "taxa_iva" => "0",
                "valor_iva" => "0",
                "retencao" => "0",

                "estado" => "emitido",

                "hash" => "",

                "desconto_tipo" => "0",
                "desconto_total" => "0",
                "valor_transporte" => "0",
                "total_sem_desconto" => "0",
                "total_impostos" => "0",
                "total_geral" => $totalGeral,
                "troco" => "0",

                "utilizador_id" => $request["utilizador_id"],
                "utilizador" => $request["utilizador"],

                "armazem_origem_id" => $request["armazem_origem_id"],
                "armazem_destino_id" => $request["armazem_destino_id"],

                "armazem_origem" => $armazemOrigem->id,
                "origem_destino" => $armazemDestino->id,

            ]);

            // Criação dos itens
            $itens = [];
            foreach ($request["itens"] as $item) {

                $totalItem = ($item["preco_custo"] * $item["quantidade"]);
                $produto = Produto::find($item["produto_id"]);

                $itens[] = [
                    "documento_id" => $documento->id,
                    "produto_nome" => $produto->nome,
                    "produto_codigo" => $produto->codigo_produto,
                    "preco_unitario" => $item["preco_custo"],
                    "descricao" => $produto->descricao,
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => 0,
                    "desconto_fixo" => 0,
                    "iva_percent" => 0,
                    "imposto_taxa_id" => null,
                    "codigo_iva" => null,
                    "tipo_id" => $produto->tipo_id,
                    "motivo_isencao" => null,
                    "total_sem_desconto" => 0,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $documento->itens()->createMany($itens);

            DB::commit();

            return $documento;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao criar documento de transferencia',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Lógica para mostrar um movimento de stock específico
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Lógica para atualizar um movimento de stock
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Lógica para remover um movimento de stock
    }
}
