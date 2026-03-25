<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use Illuminate\Http\Request;
use App\Models\Produto;
use App\Models\Stock;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);
        $search = $request->query('search');

        $categorias = $request->input('categorias');
        $armazens = $request->input('armazens');
        $marcas = $request->input('marcas');
        $precoMinVenda = $request->input('preco_min_venda');
        $precoMaxVenda = $request->input('preco_max_venda');
        $precoMinCompra = $request->input('preco_min_compra');
        $precoMaxCompra = $request->input('preco_max_compra');
        $estado = $request->input('estado');
        $stock = $request->input('stock');

        $produtoQuery = Produto::query();

        if ($search) {
            $produtoQuery->where(function ($q) use ($search) {
                $q->where('nome', 'like', '%' . $search . '%')
                    ->orWhere('descricao', 'like', '%' . $search . '%');
            });
        }

        if ($categorias) {
            $cats = is_array($categorias) ? $categorias : explode(',', $categorias);
            $hasSemCategoria = in_array('sem_categoria', $cats);
            $cats = array_values(array_filter($cats, function ($c) {
                return $c !== 'sem_categoria' && $c !== '';
            }));

            $produtoQuery->where(function ($q) use ($cats, $hasSemCategoria) {
                if (count($cats) > 0) {
                    $q->whereIn('categoria_id', $cats);
                    if ($hasSemCategoria) {
                        $q->orWhereNull('categoria_id');
                    }
                } elseif ($hasSemCategoria) {
                    $q->whereNull('categoria_id');
                }
            });
        }

        if ($marcas) {
            $marcasArr = is_array($marcas) ? $marcas : explode(',', $marcas);
            $hasSemMarca = in_array('sem_marca', $marcasArr);
            $marcasArr = array_values(array_filter($marcasArr, function ($m) {
                return $m !== 'sem_marca' && $m !== '';
            }));

            $produtoQuery->where(function ($q) use ($marcasArr, $hasSemMarca) {
                if (count($marcasArr) > 0) {
                    $q->whereIn('marca_id', $marcasArr);
                    if ($hasSemMarca) {
                        $q->orWhereNull('marca_id');
                    }
                } elseif ($hasSemMarca) {
                    $q->whereNull('marca_id');
                }
            });
        }

        if ($precoMinVenda !== null) {
            $produtoQuery->where('preco_venda', '>=', $precoMinVenda);
        }
        if ($precoMaxVenda !== null) {
            $produtoQuery->where('preco_venda', '<=', $precoMaxVenda);
        }

        if ($precoMinCompra !== null) {
            $produtoQuery->where('preco_custo', '>=', $precoMinCompra);
        }
        if ($precoMaxCompra !== null) {
            $produtoQuery->where('preco_custo', '<=', $precoMaxCompra);
        }

        if ($estado !== null && $estado !== 'all') {
            if (is_string($estado) && in_array(strtolower($estado), ['true', 'false'])) {
                $estadoVal = strtolower($estado) === 'true' ? 1 : 0;
            } else {
                $estadoVal = (int) $estado;
            }
            $produtoQuery->where('estado', $estadoVal);
        }

        if ($armazens && is_array($armazens)) {
            $armazensArray = is_array($armazens) ? $armazens : explode(',', $armazens);
            $produtoQuery->whereHas('movimentosStock', function ($q) use ($armazensArray) {
                $q->whereIn('armazem_id', $armazensArray);
            });
        }

        if ($stock) {
            // calcula stock total por produto (somando entradas e subtraindo saídas/ajustes negativos)
            $movimentosSub = DB::table('movimentos_stock')
                ->select('produto_id', DB::raw("SUM(CASE WHEN LOWER(operacao) IN ('saida','ajuste negativo') THEN -quantidade ELSE quantidade END) as total_stock"))
                ->groupBy('produto_id');

            // junta subquery para poder filtrar por total_stock
            $produtoQuery->leftJoinSub($movimentosSub, 'ms', function ($join) {
                $join->on('produtos.id', '=', 'ms.produto_id');
            })->select('produtos.*');

            $stockFilters = is_array($stock) ? $stock : explode(',', $stock);

            $produtoQuery->where(function ($q) use ($stockFilters) {
                foreach ($stockFilters as $f) {
                    $f = strtolower(trim($f));
                    if ($f === 'positivo') {
                        // stock total > 0
                        $q->orWhereRaw('COALESCE(ms.total_stock,0) > 0');
                    } elseif ($f === 'negativo' || $f === 'menor_que_0' || $f === 'menorque0') {
                        // stock total < 0
                        $q->orWhereRaw('COALESCE(ms.total_stock,0) < 0');
                    } elseif ($f === 'nulo' || $f === 'zero') {
                        // stock igual a 0 ou sem movimentos (null)
                        $q->orWhere(function ($q2) {
                            $q2->whereNull('ms.total_stock')->orWhereRaw('COALESCE(ms.total_stock,0) = 0');
                        });
                    } elseif ($f === 'menor_que_stock_min' || $f === 'menor_que_stockmin') {
                        // stock total menor que stock_min do produto
                        $q->orWhereRaw('COALESCE(ms.total_stock,0) < COALESCE(produtos.stock_min,0)');
                    } elseif ($f === 'sem_controlo' || $f === 'semcontrolo') {
                        // produtos que não movimentam stock (controle desligado)
                        $q->orWhere('movimenta_stock', 0);
                    }
                }
            });
        }

        $produtos = $produtoQuery
            ->where('empresa_id', $idEmpresa) // Filtra por empresa_id
            ->with(['marca', 'categoria', 'subCategoria', 'armazem', 'tipoIva', 'motivoIsencao', 'fornecedor', 'movimentosStock'])
            ->orderByDesc('id')
            ->paginate($per_page);

        // Adiciona as quantidades por armazém a cada produto
        $produtos->getCollection()->transform(function ($produto) {
            $quantidades = $produto->movimentosStock
                ->groupBy('armazem_id')
                ->map(function ($movimentos) {
                    return $movimentos->sum(function ($movimento) {
                        if (in_array(strtolower($movimento->operacao), ['saida', 'ajuste negativo'])) {
                            return -$movimento->quantidade;
                        } else {
                            return $movimento->quantidade;
                        }
                    });
                });

            $produto->quantidades = $quantidades;

            return $produto;
        });

        return response()->json($produtos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:255',
            'preco_custo' => 'required|numeric',
            'preco_venda' => 'required|numeric',
            'preco_final' => 'required|numeric',
            'margem_lucro' => 'required|numeric',
            'valor_iva' => 'required|numeric',
            'stock_min' => 'nullable|integer',
            'stock_max' => 'nullable|integer',
            'stock_ideal' => 'nullable|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|file|image|max:2048',
            'movimenta_stock' => 'nullable|boolean',
            'codigo_produto' => 'nullable|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'data_validade' => 'nullable|date',
            'imposto' => 'nullable|string|integer|max:255',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'unidade' => 'nullable|string|max:50',
            'tipo_stock_id' => 'required|integer|exists:tipo_stock,id',
            'marca_id' => 'nullable|integer|exists:marcas,id',
            'tipo_id' => 'required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'nullable|integer|exists:armazens,id',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        return DB::transaction(function () use ($data, $request) {

            $codigoProduto = $this->gerarCodigoProduto($request->nome, $request->tipo_id == 1 ? 'P' : 'S', $request->empresa_id);

            if (empty($data['codigo_produto'])) {
                $data['codigo_produto'] = $codigoProduto; // Gera o código se não for fornecido
            } else {
                if (Produto::where('codigo_produto', $data['codigo_produto'])->exists()) {
                    return response()->json(['error' => 'Código de produto já existe'], 422);
                }
            }

            if (isset($request->empresa_id)) {
                $data['empresa_id'] = $request->empresa_id;
            }

            if ($request->hasFile('imagem')) {
                $file = $request->file('imagem');
                $filename = $file->hashName();
                $file->storeAs('produtos', $filename, 'public');
                $data['imagem'] = $filename;
            }

            // Cria o produto dentro da transaction
            $produto = Produto::create($data);

            // Cria os stocks para cada armazém dentro da mesma transaction
            $armazens = Armazem::where('empresa_id', $data['empresa_id'])->get();
            foreach ($armazens as $armazem) {
                Stock::create([
                    'empresa_id' => $data['empresa_id'],
                    'produto_id' => $produto->id,
                    'armazem_id' => $armazem->id,
                    'stock_min' => $produto->stock_min ?? 0,      // valor padrão
                    'stock_ideal' => $produto->stock_ideial ?? 0,  // valor padrão
                    'stock_max' => $produto->stock_max ?? 0,      // valor padrão
                    'stock_atual' => 0
                ]);
            }

            return response()->json($produto->load([
                'marca',
                'categoria',
                'subCategoria',
                'armazem',
                'tipoIva',
                'motivoIsencao',
                'fornecedor',
                'movimentosStock'
            ]), 201);
        }); // fecha DB::transaction
    }

    function gerarCodigoProduto(string $nomeProduto, string $tipo, string $empresaId): string
    {
        $prefixoMarca = strtoupper(Str::substr(Str::slug($nomeProduto, ''), 0, 3)); // Ex: "Asus x8" → "ASU"
        $prefixo =  $tipo == 'P' ? 'P' . $prefixoMarca : 'S' . $prefixoMarca; // Ex: 'VASU'

        $data = Carbon::now()->format('ymd'); // Ex: 250708

        // Contar quantos produtos já foram cadastrados hoje com esse prefixo
        $hoje = Carbon::today();

        $ultimoProduto = Produto::where('empresa_id', $empresaId)
            ->orderByDesc('id')->first();

        $ultimoId = $ultimoProduto ? $ultimoProduto->id : 0;

        /* $sequencia = Produto::where('codigo_produto', 'like', "{$prefixo}-{$data}%")
            ->whereDate('created_at', $hoje)
            ->count() + 1; */

        $nextId = $ultimoId + 1; // Incrementa o ID do último produto

        $codigo = "{$prefixo}" . str_pad($nextId, 2, '0', STR_PAD_LEFT) . "-{$data}";

        return $codigo;
    }

    public function show($id)
    {
        $produto = Produto::with('movimentosStock')->findOrFail($id);

        // STOCK (já tens)
        $quantidades = $produto->movimentosStock
            ->groupBy('armazem_id')
            ->map(function ($movimentos) {
                return $movimentos->sum(function ($movimento) {
                    if (in_array(strtolower($movimento->operacao), ['saida', 'ajuste negativo'])) {
                        return -$movimento->quantidade;
                    } else {
                        return $movimento->quantidade;
                    }
                });
            });

        $produto->quantidades = $quantidades;

        // 📅 Períodos
        $hoje = Carbon::today();
        $ontem = Carbon::yesterday();
        $inicioMes = Carbon::now()->startOfMonth();
        $inicioMesAnterior = Carbon::now()->subMonth()->startOfMonth();
        $fimMesAnterior = Carbon::now()->subMonth()->endOfMonth();

        // Função auxiliar
        $getDados = function ($start, $end = null) use ($produto) {
            $query = DB::table('itens_documento')
                ->join('documentos', 'documentos.id', '=', 'itens_documento.documento_id')
                ->where('itens_documento.produto_id', $produto->id)
                ->whereIn('documentos.tipo_sigla', ['FT', 'FR']) // só vendas
                ->whereNotIn('documentos.estado_documento', ['rascunho', 'anulado', 'cancelado']); // 👈 aqui

            if ($end) {
                $query->whereBetween('documentos.created_at', [$start, $end]);
            } else {
                $query->whereDate('documentos.created_at', $start);
            }

            $dados = $query
                ->selectRaw('
                SUM(itens_documento.quantidade) as total_qtd,
                SUM(itens_documento.quantidade * itens_documento.preco_unitario) as total_vendas
            ')
                ->first();

            $lucro = ($dados->total_qtd ?? 0) * ($produto->preco_venda - $produto->preco_custo);

            return [
                'quantidade' => (int) ($dados->total_qtd ?? 0),
                'vendas' => (float) ($dados->total_vendas ?? 0),
                'rentabilidade' => (float) $lucro,
            ];
        };

        // 📊 Dados por período
        $produto->estatisticas = [
            'hoje' => $getDados($hoje),
            'ontem' => $getDados($ontem),
            'mes_atual' => $getDados($inicioMes, now()),
            'mes_anterior' => $getDados($inicioMesAnterior, $fimMesAnterior),
        ];

        return response()->json($produto);
    }

    public function update(Request $request, $id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        // Upload da imagem ANTES da validação
        $filename = null;
        if ($request->hasFile('imagem')) {
            // Deleta a imagem antiga
            if ($produto->imagem) {
                Storage::disk('public')->delete('produtos/' . $produto->imagem);
            }

            $file = $request->file('imagem');
            $filename = $file->hashName();
            $file->storeAs('produtos', $filename, 'public');
        }

        // Validação
        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string|max:255',
            'preco_custo' => 'sometimes|required|numeric',
            'preco_venda' => 'sometimes|required|numeric',
            'preco_final' => 'sometimes|required|numeric',
            'margem_lucro' => 'sometimes|required|numeric',
            'valor_iva' => 'sometimes|required|numeric',
            'stock_min' => 'sometimes|nullable|integer',
            'stock_max' => 'sometimes|nullable|integer',
            'stock_ideial' => 'sometimes|nullable|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|file|image|max:2048',
            'movimenta_stock' => 'sometimes|required|boolean',
            'codigo_produto' => 'sometimes|required|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'data_validade' => 'nullable|date',
            'imposto' => 'sometimes|required|string|max:255',
            'unidade' => 'nullable|string|max:50',
            'estado' => 'sometimes|nullable|boolean',
            'marca_id' => 'sometimes|nullable|integer|exists:marcas,id',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'tipo_stock_id' => 'nullable|integer|exists:tipo_stock,id',
            'tipo_id' => 'sometimes|required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'sometimes|nullable|integer|exists:armazens,id',
            'categoria_id' => 'sometimes|nullable|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'fornecedor_id' => 'sometimes|nullable|integer|exists:fornecedores,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Se houve upload, substitui o campo imagem
        if ($filename) {
            $data['imagem'] = $filename;
        }

        // Atualiza
        $produto->update($data);

        return response()->json($produto);
    }


    public function destroy($id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto not found'], 404);
        }

        $produto->delete();
        return response()->json(['message' => 'Produto deleted successfully']);
    }

    public function relatorioProdutos(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'search' => 'nullable|string',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'marca_id' => 'nullable|integer|exists:marcas,id',
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',

            'armazem_id' => 'nullable|integer|exists:armazens,id',

            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',

            'stock_min' => 'nullable|numeric',
            'stock_max' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $empresaId = $request->input('empresa_id');

        $query = Produto::with(['movimentosStock', 'marca', 'categoria', 'fornecedor'])
            ->where('empresa_id', $empresaId);

        /*
    |--------------------------------------------------------------------------
    | FILTROS
    |--------------------------------------------------------------------------
    */

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('nome', 'like', "%{$request->search}%")
                    ->orWhere('descricao', 'like', "%{$request->search}%")
                    ->orWhere('codigo_produto', 'like', "%{$request->search}%");
            });
        }

        if ($request->categoria_id && $request->categoria_id !== 'all') {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->marca_id && $request->marca_id !== 'all') {
            $query->where('marca_id', $request->marca_id);
        }

        if ($request->fornecedor_id && $request->fornecedor_id !== 'all') {
            $query->where('fornecedor_id', $request->fornecedor_id);
        }

        if ($request->data_inicio) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->data_fim) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $produtos = $query->orderByDesc('id')->get();

        /*
    |--------------------------------------------------------------------------
    | CALCULAR STOCK
    |--------------------------------------------------------------------------
    */

        $produtosFormatados = $produtos->map(function ($produto) {

            $quantidades = $produto->movimentosStock
                ->groupBy('armazem_id')
                ->map(function ($movimentos) {
                    return $movimentos->sum(function ($movimento) {
                        if (in_array(strtolower($movimento->operacao), ['saida', 'ajuste negativo'])) {
                            return -$movimento->quantidade;
                        }
                        return $movimento->quantidade;
                    });
                });

            $stockTotal = $quantidades->sum();

            return [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'codigo' => $produto->codigo_produto,
                'descricao' => $produto->descricao,
                'preco_custo' => $produto->preco_custo,
                'preco_venda' => $produto->preco_venda,
                'preco_final' => $produto->preco_final,
                'margem_lucro' => $produto->margem_lucro,
                'valor_iva' => $produto->valor_iva,
                'stock_total' => $stockTotal,
                'categoria' => $produto->categoria->nome ?? '',
                'marca' => $produto->marca->nome ?? '',
                'fornecedor' => $produto->fornecedor->nome ?? '',
                'criado_em' => optional($produto->created_at)->format('d/m/Y H:i'),
            ];
        });

        /*
    |--------------------------------------------------------------------------
    | FILTRO DE STOCK
    |--------------------------------------------------------------------------
    */

        if ($request->stock_min) {
            $produtosFormatados = $produtosFormatados->where('stock_total', '>=', $request->stock_min);
        }

        if ($request->stock_max) {
            $produtosFormatados = $produtosFormatados->where('stock_total', '<=', $request->stock_max);
        }

        /*
    |--------------------------------------------------------------------------
    | GERAR PDF
    |--------------------------------------------------------------------------
    */

        $pdf = Pdf::loadView('pdf.produto-relatorio', [
            'produtos' => $produtosFormatados,
            'data' => Carbon::now()->format('d/m/Y H:i'),
            'filtros' => $request->all()
        ])->setPaper('A4', 'landscape');

        return $pdf->stream('relatorio_produtos.pdf');
    }
}
