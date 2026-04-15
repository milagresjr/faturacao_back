<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\Documento;
use App\Models\DocumentoInterno;
use App\Models\Empresa;
use App\Models\LoteProduto;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;
use App\Models\Utilizador;
use App\Services\ValidadeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MovimentoStockController extends Controller
{

    protected $validadeService;  // ← NOVO

    // ← NOVO: Injetar o serviço
    public function __construct(ValidadeService $validadeService)
    {
        $this->validadeService = $validadeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
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

        $movimentoStock = $movimentoStockQuery
            ->where('empresa_id', $idEmpresa)
            ->with(['produto.categoria', 'armazem', 'utilizador', 'lote'])  // ← ADICIONEI 'lote'
            ->orderByDesc('id')
            ->paginate($per_page);

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

            // ← NOVAS VALIDAÇÕES PARA LOTES
            'itens.*.lote_id' => ['nullable', 'exists:lotes_produto,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $idEmpresa = $request->input('empresa_id');

        try {

            DB::beginTransaction();

            $result = null;

            if ($data['tipo_movimento'] === 'nota_quebra') {

                $documento = $this->storeDocumentNotaDeQuebra($request);
                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    $dadosMovimento = $this->criarMovimentoComLote([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'nota_quebra',
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'documento_relacionado_id' => $documento->id,
                        'empresa_id' => $idEmpresa,
                        'lote_id' => $item['lote_id'] ?? null,
                    ]);

                    $movimentos[] = $documento->movimentosStock()->create($dadosMovimento);
                }

                $result = [
                    'message' => 'Movimentos de stock para Nota de Quebra registrados com sucesso',
                    'data' => $documento
                ];
            } elseif ($data['tipo_movimento'] === 'transferencia') {

                $documento = $this->storeDocumentTransferencia($request);

                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    // Movimento de saída do armazém de origem
                    $dadosSaida = $this->criarMovimentoComLote([
                        'armazem_id' => $data['armazem_origem_id'],
                        'armazem_origem_id' => $data['armazem_origem_id'],
                        'armazem_destino_id' => $data['armazem_destino_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'transferencia_saida',
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'empresa_id' => $idEmpresa,
                        'lote_id' => $item['lote_id'] ?? null,
                        'num_fatura' => $documento->num_fatura,
                    ]);

                    $movimentos[] = $documento->movimentosStock()->create($dadosSaida);

                    // Movimento de entrada no armazém destino
                    $dadosEntrada = $this->criarMovimentoComLote([
                        'armazem_id' => $data['armazem_destino_id'],
                        'armazem_destino_id' => $data['armazem_destino_id'],
                        'armazem_origem_id' => $data['armazem_origem_id'], // ← NOVO: para rastrear origem
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'transferencia_entrada',
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'empresa_id' => $idEmpresa,
                        'lote_id' => $item['lote_id'] ?? null,  // mantém o mesmo lote
                        'num_fatura' => $documento->num_fatura,
                    ]);

                    $movimentos[] = $documento->movimentosStock()->create($dadosEntrada);
                }

                $result = [
                    'message' => 'Transferência realizada com sucesso',
                    'data' => $documento
                ];
            } elseif ($data['tipo_movimento'] === 'saida_inventario' || $data['tipo_movimento'] === 'entrada_inventario') {

                $documento = $this->storeDocumentInventario($request, $data['tipo_movimento']);

                $movimentos = [];

                foreach ($data['itens'] as $item) {
                    $dadosMovimento = $this->criarMovimentoComLote([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => $data['tipo_movimento'],
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => $documento->num_fatura,
                        'documento_relacionado_id' => $documento->id,
                        'empresa_id' => $idEmpresa,
                        'lote_id' => $item['lote_id'] ?? null,
                        'num_fatura' => $documento->num_fatura,
                    ]);

                    $movimentos[] = $documento->movimentosStock()->create($dadosMovimento);
                }

                $result = [
                    'message' => 'Movimentação registrada com sucesso',
                    'data' => $documento
                ];
            } else {

                // Movimentos manuais (entrada/saida simples)

                $movimentos = [];

                foreach ($data['itens'] as $item) {

                    $dadosMovimento = $this->criarMovimentoComLote([
                        'armazem_id' => $data['armazem_id'],
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => $data['tipo_movimento'],
                        'observacao' => $item['observacao'] ?? null,
                        'utilizador_id' => $data['utilizador_id'] ?? null,
                        'origem_movimento' => 'Manual',
                        'documento_relacionado_id' => null,
                        'empresa_id' => $idEmpresa,
                        'lote_id' => $item['lote_id'] ?? null,
                    ]);

                    $movimentos[] = MovimentoStock::create($dadosMovimento);
                }

                $result = [
                    'message' => 'Movimentação registrada com sucesso',
                    'data' => $movimentos
                ];
            }

            // ← MODIFICADO: Atualização do stock com suporte a lotes
            // $this->atualizarStockComLotes($data['itens'], $data['tipo_movimento'], $data);

            //Atualiza na tabela Stock
            // $stock = Stock::where('produto_id', $item['produto_id'])->first();

            // if ($stock) {
            //     if ($this->resolveOperacao($data['tipo_movimento']) === '+') {
            //         $stock->increment('stock_atual', $item['quantidade']);
            //     } else {
            //         $stock->decrement('stock_atual', $item['quantidade']);
            //     }
            // }

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

    /**
     * Criar movimento com suporte a lotes
     * 
     * @param array $dados
     * @return array
     */
    private function criarMovimentoComLote(array $dados)
    {
        $produto = Produto::find($dados['produto_id']);
        $idLote = null;
        $codigoLote = null;
        $dataValidadeLote = null;
        $detalhesLote = null;

        // ==========================================
        // CENÁRIO 1: Produto NÃO controla stock
        // ==========================================
        if (!$produto->movimenta_stock) {
            // Apenas registra o movimento, não altera stock
            return [
                'armazem_origem_id' => $dados['armazem_origem_id'] ?? null,  // para transferências
                'armazem_destino_id' => $dados['armazem_destino_id'] ?? null,  // para transferências
                'armazem_id' => $dados['armazem_id'],
                'produto_id' => $dados['produto_id'],
                'quantidade' => $dados['quantidade'],
                'operacao' => $dados['operacao'],
                'observacao' => $dados['observacao'] ?? null,
                'utilizador_id' => $dados['utilizador_id'] ?? null,
                'origem_movimento' => $dados['origem_movimento'],
                'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
                'empresa_id' => $dados['empresa_id'],
                'lote_id' => null,
                'codigo_lote' => null,
                'data_validade_lote' => null,
                'detalhes_lote' => ['observacao' => 'Produto não controla stock']
            ];
        }

        // ==========================================
        // CENÁRIO 2: Produto controla stock mas NÃO controla validade
        // ==========================================
        if ($produto->movimenta_stock && !$produto->controla_validade) {

            $operacao = $this->resolveOperacao($dados['operacao']);

            // ==========================================
            // PARA TRANSFERÊNCIA (caso especial)
            // ==========================================
            if ($dados['operacao'] === 'transferencia_saida') {

                // SAÍDA do armazém ORIGEM
                $stockOrigem = Stock::where('produto_id', $produto->id)
                    ->where('armazem_id', $dados['armazem_id'])  // armazém origem
                    ->first();

                if (!$stockOrigem) {
                    throw new \Exception("Stock não encontrado no armazém de origem para o produto {$produto->nome}");
                }

                // Verificar se tem quantidade suficiente
                if ($stockOrigem->stock_atual < $dados['quantidade']) {
                    throw new \Exception("Stock insuficiente no armazém de origem. Disponível: {$stockOrigem->stock_atual}, Solicitado: {$dados['quantidade']}");
                }

                // Dar baixa no armazém de origem
                $stockOrigem->decrement('stock_atual', $dados['quantidade']);

                return [
                    'armazem_origem_id' => $dados['armazem_id'],
                    'armazem_destino_id' => $dados['armazem_destino_id'] ?? null,
                    'armazem_id' => $dados['armazem_id'],
                    'produto_id' => $dados['produto_id'],
                    'quantidade' => $dados['quantidade'],
                    'operacao' => $dados['operacao'],
                    'observacao' => $dados['observacao'] ?? null,
                    'utilizador_id' => $dados['utilizador_id'] ?? null,
                    'origem_movimento' => $dados['origem_movimento'],
                    'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
                    'empresa_id' => $dados['empresa_id'],
                    'stock_id' => $stockOrigem->id,
                    'lote_id' => null,
                    'codigo_lote' => null,
                    'data_validade_lote' => null,
                    'detalhes_lote' => [
                        'tipo' => 'transferencia_saida',
                        'stock_restante' => $stockOrigem->stock_atual - $dados['quantidade']
                    ]
                ];
            } elseif ($dados['operacao'] === 'transferencia_entrada') {

                // ENTRADA no armazém DESTINO
                $stockDestino = Stock::where('produto_id', $produto->id)
                    ->where('armazem_id', $dados['armazem_id'])  // armazém destino
                    ->first();

                if ($stockDestino) {
                    // Já existe stock no destino → incrementar
                    $stockDestino->increment('stock_atual', $dados['quantidade']);
                    $stockId = $stockDestino->id;
                } else {
                    // Não existe → criar novo registo de stock
                    $stockDestino = Stock::create([
                        'produto_id' => $produto->id,
                        'armazem_id' => $dados['armazem_id'],
                        'stock_atual' => $dados['quantidade'],
                        'stock_min' => $produto->stock_min,
                        'stock_max' => $produto->stock_max,
                        'stock_ideal' => $produto->stock_ideal,
                        'empresa_id' => $dados['empresa_id']
                    ]);
                    $stockId = $stockDestino->id;
                }

                return [
                    'armazem_origem_id' => $dados['armazem_origem_id'] ?? null,
                    'armazem_destino_id' => $dados['armazem_id'],
                    'armazem_id' => $dados['armazem_id'],
                    'produto_id' => $dados['produto_id'],
                    'quantidade' => $dados['quantidade'],
                    'operacao' => $dados['operacao'],
                    'observacao' => $dados['observacao'] ?? null,
                    'utilizador_id' => $dados['utilizador_id'] ?? null,
                    'origem_movimento' => $dados['origem_movimento'],
                    'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
                    'empresa_id' => $dados['empresa_id'],
                    'stock_id' => $stockId,
                    'lote_id' => null,
                    'codigo_lote' => null,
                    'data_validade_lote' => null,
                    'detalhes_lote' => [
                        'tipo' => 'transferencia_entrada',
                        'stock_atual_destino' => $stockDestino->stock_atual
                    ]
                ];
            }

            // ==========================================
            // MOVIMENTOS NORMAIS (entrada, saida, etc)
            // ==========================================
            else {
                $stock = Stock::where('produto_id', $produto->id)
                    ->where('armazem_id', $dados['armazem_id'])
                    ->first();

                if ($stock) {
                    if ($operacao === '+') {
                        $stock->increment('stock_atual', $dados['quantidade']);
                    } else {
                        // Verificar se tem quantidade suficiente
                        if ($stock->stock_atual < $dados['quantidade']) {
                            throw new \Exception("Stock insuficiente para o produto {$produto->nome}. Disponível: {$stock->stock_atual}, Solicitado: {$dados['quantidade']}");
                        }
                        $stock->decrement('stock_atual', $dados['quantidade']);
                    }
                } else {
                    if ($operacao === '-') {
                        throw new \Exception("Stock insuficiente para o produto {$produto->nome}. Disponível: 0, Solicitado: {$dados['quantidade']}");
                    }
                    // Se não existe stock e é uma entrada, cria um novo registro
                    $stock = Stock::create([
                        'produto_id' => $produto->id,
                        'armazem_id' => $dados['armazem_id'],
                        'stock_atual' => $operacao === '+' ? $dados['quantidade'] : 0,
                        'stock_min' => $produto->stock_min,
                        'stock_max' => $produto->stock_max,
                        'stock_ideal' => $produto->stock_ideal,
                        'empresa_id' => $dados['empresa_id']
                    ]);
                }

                return [
                    'armazem_origem_id' => $dados['armazem_origem_id'] ?? null,
                    'armazem_destino_id' => $dados['armazem_destino_id'] ?? null,
                    'armazem_id' => $dados['armazem_id'],
                    'produto_id' => $dados['produto_id'],
                    'quantidade' => $dados['quantidade'],
                    'operacao' => $dados['operacao'],
                    'observacao' => $dados['observacao'] ?? null,
                    'utilizador_id' => $dados['utilizador_id'] ?? null,
                    'origem_movimento' => $dados['origem_movimento'],
                    'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
                    'empresa_id' => $dados['empresa_id'],
                    'stock_id' => $stock ? $stock->id : null,
                    'lote_id' => null,
                    'codigo_lote' => null,
                    'data_validade_lote' => null,
                    'detalhes_lote' => null
                ];
            }
        }

        // ==========================================
        // CENÁRIO 3: Produto controla stock E controla validade
        // ==========================================
        if ($produto->movimenta_stock && $produto->controla_validade && isset($dados['lote_id']) && !empty($dados['lote_id'])) {
            $operacao = $dados['operacao'];

            // ==========================================
            // PARA TRANSFERÊNCIA (caso especial)
            // ==========================================
            if ($operacao === 'transferencia_saida') {
                // 1. TIRAR do armazém ORIGEM
                $loteOrigem = LoteProduto::find($dados['lote_id']);

                if (!$loteOrigem) {
                    throw new \Exception("Lote ID {$dados['lote_id']} não encontrado para o produto {$produto->nome}");
                }

                // Verificar se tem quantidade suficiente
                if ($loteOrigem->qtd_atual < $dados['quantidade']) {
                    throw new \Exception("Stock insuficiente no lote {$loteOrigem->codigo_lote}. Disponível: {$loteOrigem->qtd_atual}, Solicitado: {$dados['quantidade']}");
                }

                // Dar baixa no lote de origem
                $loteOrigem->qtd_atual -= $dados['quantidade'];

                if ($loteOrigem->qtd_atual <= 0) {
                    $loteOrigem->status = 'consumido';
                }

                $loteOrigem->save();

                // Guardar os dados do lote para usar no destino
                $idLote = $loteOrigem->id;
                $codigoLote = $loteOrigem->codigo_lote;
                $dataValidadeLote = $loteOrigem->data_validade;

                // Armazenar dados temporários para a entrada (serão usados no transferencia_entrada)
                session()->put('transferencia_dados_lote_' . $dados['num_fatura'], [
                    'codigo_lote' => $loteOrigem->codigo_lote,
                    'data_validade' => $loteOrigem->data_validade,
                    'data_fabricacao' => $loteOrigem->data_fabricacao,
                    'produto_id' => $produto->id,
                    'quantidade' => $dados['quantidade']
                ]);

                $detalhesLote = [
                    'tipo' => 'transferencia_saida',
                    'armazem_origem_id' => $dados['armazem_id'],
                    'codigo_lote' => $codigoLote,
                    'quantidade_transferida' => $dados['quantidade'],
                    'quantidade_restante_origem' => $loteOrigem->qtd_atual
                ];
            } elseif ($operacao === 'transferencia_entrada') {
                // 2. ADICIONAR no armazém DESTINO

                // Recuperar os dados do lote que vieram da origem
                $dadosLoteTransferencia = session()->get('transferencia_dados_lote_' . $dados['num_fatura']);

                if (!$dadosLoteTransferencia) {
                    throw new \Exception("Dados da transferência não encontrados. Verifique se a saída foi registrada primeiro.");
                }

                // Verificar se existe com código parecido (LIKE)
                $existeParecido = LoteProduto::where('produto_id', $produto->id)
                    ->where('armazem_id', $dados['armazem_id'])
                    ->where('codigo_lote', $dadosLoteTransferencia['codigo_lote'])
                    ->get();

              
                // Verificar se já existe este lote no armazém de destino
                $loteDestino = LoteProduto::where('produto_id', $produto->id)
                    ->where('armazem_id', $dados['armazem_id'])  // armazém destino
                    ->where('codigo_lote', $dadosLoteTransferencia['codigo_lote'])
                    ->first();

                if ($loteDestino) {
                    // Já existe o mesmo lote no destino → juntar (somar quantidade)
                    $quantidadeAnterior = $loteDestino->qtd_atual;
                    $loteDestino->qtd_atual += $dados['quantidade'];

                    // Atualizar data de validade se a nova for mais curta
                    $dataValidade = \Carbon\Carbon::parse($dadosLoteTransferencia['data_validade']);
                    if ($dataValidade < $loteDestino->data_validade) {
                        $loteDestino->data_validade = $dataValidade;
                    }

                    $loteDestino->save();

                    $idLote = $loteDestino->id;
                    $codigoLote = $loteDestino->codigo_lote;
                    $dataValidadeLote = $loteDestino->data_validade;

                    $detalhesLote = [
                        'tipo' => 'transferencia_entrada_lote_existente',
                        'armazem_destino_id' => $dados['armazem_id'],
                        'quantidade_anterior' => $quantidadeAnterior,
                        'quantidade_adicionada' => $dados['quantidade'],
                        'quantidade_atual' => $loteDestino->qtd_atual
                    ];
                } else {
                    $nomeArmazemOrigem = Armazem::find($dados['armazem_origem_id'])->nome ?? '';
                    // Não existe → criar novo lote no destino com os MESMOS dados
                    $novoLote = LoteProduto::create([
                        'produto_id' => $produto->id,
                        'armazem_id' => $dados['armazem_id'],  // armazém destino
                        'codigo_lote' => $dadosLoteTransferencia['codigo_lote'],
                        'lote' => $dadosLoteTransferencia['codigo_lote'],
                        'data_fabricacao' => $dadosLoteTransferencia['data_fabricacao'] ?? null,
                        'data_validade' => $dadosLoteTransferencia['data_validade'],
                        'qtd_atual' => $dados['quantidade'],
                        'qtd_inicial' => $dados['quantidade'],
                        'status' => 'activo',
                        'observacao' => "Transferido do armazém {$nomeArmazemOrigem}",
                        'data_entrada' => now()
                    ]);

                    $idLote = $novoLote->id;
                    $codigoLote = $novoLote->codigo_lote;
                    $dataValidadeLote = $novoLote->data_validade;

                    $detalhesLote = [
                        'tipo' => 'transferencia_entrada_novo_lote',
                        'armazem_destino_id' => $dados['armazem_id'],
                        'codigo_lote_criado' => $codigoLote,
                        'quantidade' => $dados['quantidade']
                    ];
                }

                // Limpar os dados da sessão
                session()->forget('transferencia_dados_lote_' . $dados['num_fatura']);
            }

            // ==========================================
            // PARA MOVIMENTOS DE SAÍDA (que não são transferência)
            // ==========================================
            elseif (in_array($operacao, ['saida', 'nota_quebra', 'saida_inventario'])) {

                $lote = LoteProduto::find($dados['lote_id']);

                if (!$lote) {
                    throw new \Exception("Lote não encontrado para o produto {$produto->nome}");
                }

                // Verificar se tem quantidade suficiente
                if ($lote->qtd_atual < $dados['quantidade']) {
                    throw new \Exception("Stock insuficiente no lote {$lote->codigo_lote}. Disponível: {$lote->qtd_atual}, Solicitado: {$dados['quantidade']}");
                }

                // Dar baixa no lote
                $lote->qtd_atual -= $dados['quantidade'];

                if ($lote->qtd_atual <= 0) {
                    $lote->status = 'consumido';
                }

                $lote->save();

                $idLote = $lote->id;
                $codigoLote = $lote->codigo_lote;
                $dataValidadeLote = $lote->data_validade;
                $detalhesLote = [
                    'dias_restantes' => now()->diffInDays($lote->data_validade),
                    'status_validade' => $this->getStatusValidade($lote),
                    'quantidade_antes' => $lote->qtd_atual + $dados['quantidade'],
                    'quantidade_depois' => $lote->qtd_atual
                ];
            }

            // ==========================================
            // PARA MOVIMENTOS DE ENTRADA (que não são transferência)
            // ==========================================
            elseif (in_array($operacao, ['entrada', 'entrada_inventario'])) {

                $lote = LoteProduto::find($dados['lote_id']);

                $dataValidade = \Carbon\Carbon::parse($lote->data_validade);
                if ($dataValidade < now()) {
                    throw new \Exception("Não é possível dar entrada em produto com data de validade vencida: {$dataValidade->format('d/m/Y')}");
                }

                if ($lote) {
                    // Lote existe - atualizar quantidade
                    $quantidadeAnterior = $lote->qtd_atual;
                    $lote->qtd_atual += $dados['quantidade'];

                    // Atualizar data de validade se a nova for mais curta
                    if ($dataValidade < $lote->data_validade) {
                        $lote->data_validade = $dataValidade;
                    }

                    $lote->save();

                    $idLote = $lote->id;
                    $codigoLote = $lote->codigo_lote;
                    $dataValidadeLote = $lote->data_validade;
                    $detalhesLote = [
                        'quantidade_anterior' => $quantidadeAnterior,
                        'quantidade_adicionada' => $dados['quantidade'],
                        'quantidade_atual' => $lote->qtd_atual,
                        'lote_ja_existia' => true
                    ];
                }
            }

            // ==========================================
            // ATUALIZAR TABELAS RELACIONADAS
            // ==========================================

            // Atualizar stock consolidado na tabela produtos
            $this->atualizarStockConsolidadoProduto($produto->id);

            // Verificar e gerar alertas se o lote está perto de vencer
            if (isset($lote) || isset($loteOrigem) || isset($loteDestino) || isset($novoLote)) {
                $loteParaAlertar = $lote ?? $loteDestino ?? $novoLote ?? $loteOrigem;
                if ($loteParaAlertar) {
                    $this->validadeService->verificarEmitirAlerta($loteParaAlertar);
                }
            }
        }

        // ==========================================
        // CENÁRIO 4: Produto NÃO controla stock E NÃO controla validade
        // ==========================================
        if (!$produto->controla_stock && !$produto->controla_validade) {
            // Produto serviço - apenas registra, não altera nada
            return [
                'armazem_origem_id' => $dados['armazem_origem_id'] ?? null,  // para transferências
                'armazem_destino_id' => $dados['armazem_destino_id'] ?? null,  // para transferências
                'armazem_id' => $dados['armazem_id'],
                'produto_id' => $dados['produto_id'],
                'quantidade' => $dados['quantidade'],
                'operacao' => $dados['operacao'],
                'observacao' => $dados['observacao'] ?? null,
                'utilizador_id' => $dados['utilizador_id'] ?? null,
                'origem_movimento' => $dados['origem_movimento'],
                'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
                'empresa_id' => $dados['empresa_id'],
                'lote_id' => null,
                'codigo_lote' => null,
                'data_validade_lote' => null,
                'detalhes_lote' => ['observacao' => 'Produto serviço - sem stock']
            ];
        }

        // ==========================================
        // RETORNAR PADRAO DOS DADOS DO MOVIMENTO
        // ==========================================

        return [
            'armazem_origem_id' => $dados['armazem_origem_id'] ?? null,  // para transferências
            'armazem_destino_id' => $dados['armazem_destino_id'] ?? null,  // para transferências
            'armazem_id' => $dados['armazem_id'],
            'produto_id' => $dados['produto_id'],
            'quantidade' => $dados['quantidade'],
            'operacao' => $dados['operacao'],
            'observacao' => $dados['observacao'] ?? null,
            'utilizador_id' => $dados['utilizador_id'] ?? null,
            'origem_movimento' => $dados['origem_movimento'],
            'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
            'empresa_id' => $dados['empresa_id'],
            'lote_id' => $idLote,
            'codigo_lote' => $codigoLote,
            'data_validade_lote' => $dataValidadeLote,
            'detalhes_lote' => $detalhesLote
        ];
    }

    private function getStatusValidade($lote)
    {
        $diasRestantes = now()->diffInDays($lote->data_validade);

        if ($diasRestantes <= 7) return 'critico';
        if ($diasRestantes <= 30) return 'precoce';
        return 'normal';
    }

    /**
     * ← NOVO MÉTODO: Atualizar stock com suporte a lotes
     */
    private function atualizarStockComLotes(array $itens, string $tipoMovimento, array $dadosRequest)
    {
        foreach ($itens as $item) {
            $produto = Produto::find($item['produto_id']);

            // Só processa produtos que controlam stock mas NÃO controlam validade
            if ($produto && $produto->movimenta_stock && !$produto->controla_validade) {
                $operacao = $this->resolveOperacao($tipoMovimento);
                $stock = Stock::where('produto_id', $item['produto_id'])
                    ->where('armazem_id', $dadosRequest['armazem_id'] ?? null)
                    ->first();

                if ($stock) {
                    if ($operacao === '+') {
                        $stock->increment('stock_atual', $item['quantidade']);
                    } else {
                        $stock->decrement('stock_atual', $item['quantidade']);
                    }
                }
            }
        }
    }

    /**
     * ← NOVO MÉTODO: Atualizar stock consolidado do produto (soma de todos lotes)
     */
    private function atualizarStockConsolidadoProduto($produtoId)
    {
        $totalStock = LoteProduto::where('produto_id', $produtoId)
            ->where('status', 'activo')
            ->where('data_validade', '>=', now())
            ->sum('qtd_atual');

        Produto::where('id', $produtoId)->update([
            'stock_atual' => $totalStock
        ]);
    }

    /**
     * ← NOVO MÉTODO: Atualizar stock por armazém considerando lotes
     */
    private function atualizarStockArmazemPorLotes($produtoId, $armazemOrigemId, $armazemDestinoId)
    {
        // Para transferências, precisamos saber qual armazém tem o quê
        // Isso depende de como você organiza stock por armazém com lotes

        // Se você tem stock por armazém na tabela 'stocks'
        if ($armazemOrigemId) {
            $stockOrigem = Stock::where('produto_id', $produtoId)
                ->where('armazem_id', $armazemOrigemId)
                ->first();

            if ($stockOrigem) {
                $stockOrigem->decrement('stock_atual', request()->input('itens.0.quantidade'));
            }
        }

        if ($armazemDestinoId) {
            $stockDestino = Stock::where('produto_id', $produtoId)
                ->where('armazem_id', $armazemDestinoId)
                ->first();

            if ($stockDestino) {
                $stockDestino->increment('stock_atual', request()->input('itens.0.quantidade'));
            }
        }
    }

    function resolveOperacao(string $tipo): string
    {
        return in_array($tipo, [
            'saida',
            'saida_inventario',
            'nota_quebra',
            'transferencia_saida'
        ]) ? '-' : '+';
    }

    public function storeDocumentNotaDeQuebra(Request $request)
    {

        $numFatura = $this->gerarNumeroDocumento(
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

        $numFatura = $this->gerarNumeroDocumento(
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

        $numFatura = $this->gerarNumeroDocumento(
            'TR',
            $request->input("empresa_id"),
        );

        $data = $request->all();

        $itens = [];
        foreach ($data['itens'] as $itemData) {
            $produto = Produto::find($itemData['produto_id']);
            $itens[] = [
                'produto_id' => $itemData['produto_id'],
                'preco_custo' => $produto->preco_custo,
                'quantidade' => $itemData['quantidade'],
            ];
        }

        $totalGeral = 0;
        foreach ($itens as $item) {
            $totalItem = ($item['preco_custo'] * $item['quantidade']);
            $totalGeral += $totalItem;
        }

        $empresa = Empresa::find($request->input("empresa_id"));

        $armazemOrigem = Armazem::find($request["armazem_origem_id"])->nome;
        $armazemDestino = Armazem::find($request["armazem_destino_id"])->nome;

        $utilizador = Utilizador::find($request["utilizador_id"]);

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
                "utilizador" => $utilizador->nome_de_utilizador,

                "armazem_origem_id" => $request["armazem_origem_id"],
                "armazem_destino_id" => $request["armazem_destino_id"],

                "armazem_origem" => $armazemOrigem,
                "armazem_destino" => $armazemDestino,

            ]);

            // Criação dos itens
            $itensCreate = [];
            foreach ($itens as $item) {

                $totalItem = ($item["preco_custo"] * $item["quantidade"]);
                $produto = Produto::find($item["produto_id"]);

                $itensCreate[] = [
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

            $documento->itens()->createMany($itensCreate);

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

    private function gerarCodigoLote($produtoId)
    {
        // Formato: LOTE-{produto_id}-{data atual YYYYMMDD}-{sequencial do dia}
        $dataHoje = now()->format('Ymd');
        $prefixo = "LOTE-{$produtoId}-{$dataHoje}";

        // Buscar último lote criado hoje para este produto
        $ultimoLote = LoteProduto::where('produto_id', $produtoId)
            ->where('codigo_lote', 'LIKE', $prefixo . '%')
            ->orderBy('codigo_lote', 'desc')
            ->first();

        if ($ultimoLote) {
            // Extrair o número sequencial do último lote
            $partes = explode('-', $ultimoLote->codigo_lote);
            $sequencial = intval(end($partes)) + 1;
        } else {
            $sequencial = 1;
        }

        // Formatar sequencial com 3 dígitos (001, 002, etc)
        $sequencialFormatado = str_pad($sequencial, 3, '0', STR_PAD_LEFT);

        return "{$prefixo}-{$sequencialFormatado}";
    }

    public function gerarNumeroDocumento(
        string $tipoSigla,
        string $empresId,
    ): string {
        $ano = Carbon::now()->year;

        $empresa = DB::table("empresas")->find($empresId);

        // Conta quantos documentos desse tipo e ano já existem
        $contador = DB::table("documentos_interno")
            ->where("tipo_sigla", $tipoSigla) // campo tipo como 'FR', por exemplo
            ->where("empresa_id", $empresId) // campo empresa_id
            ->whereYear("created_at", $ano)
            ->count();

        $sequencial = $contador + 1;

        // Formato final: FR T11P2025/2
        return "{$tipoSigla} {$empresa->indicativo_fatura}{$ano}/{$sequencial}";
    }

    //Alterar quantidade minima de um produto na tabela stocks
    public function alterarStockMinimo(Request $request, $idArmazem, $idProduto)
    {
        $validated = Validator::make($request->all(), [
            'stock_min' => 'required|integer|min:0',
        ]);

        if ($validated->fails()) {
            return response()->json(
                [
                    'message' => 'Dados inválidos',
                    'errors' => $validated->errors()
                ],
                422
            );
        }

        $stock = Stock::where('armazem_id', $idArmazem)
            ->where('produto_id', $idProduto)
            ->first();

        if (!$stock) {
            return response()->json(['message' => 'Stock não encontrado'], 404);
        }

        $stock->stock_min = $request->input('stock_min');
        $stock->save();

        return response()->json(['message' => 'Stock mínimo atualizado com sucesso', 'stock' => $stock]);
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
