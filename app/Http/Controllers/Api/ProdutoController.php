<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\LoteProduto;
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
            $produtoQuery->whereHas('stocks', function ($q) use ($armazensArray) {
                $q->whereIn('armazem_id', $armazensArray);
            });
        }

        // ==========================================
        // SUBQUERY PARA STOCK TOTAL (PRODUTOS SEM VALIDADE)
        // ==========================================
        $stocksSub = DB::table('stocks')
            ->select('produto_id', DB::raw('SUM(stock_atual) as total_stock'))
            ->groupBy('produto_id');

        // ==========================================
        // SUBQUERY PARA STOCK TOTAL (PRODUTOS COM VALIDADE via lotes)
        // ==========================================
        $lotesSub = DB::table('lotes_produto')
            ->select('produto_id', DB::raw('SUM(qtd_atual) as total_stock_lotes'))
            ->where('status', 'activo')
            ->where('data_validade', '>=', now())
            ->groupBy('produto_id');

        // ==========================================
        // FILTRO POR STOCK (quando o parâmetro 'stock' é enviado)
        // ==========================================
        if ($stock) {
            // Junta ambas subqueries para produtos com e sem validade
            $produtoQuery->leftJoinSub($stocksSub, 's', function ($join) {
                $join->on('produtos.id', '=', 's.produto_id');
            })->leftJoinSub($lotesSub, 'l', function ($join) {
                $join->on('produtos.id', '=', 'l.produto_id');
            })->select(
                'produtos.*',
                DB::raw('COALESCE(s.total_stock, 0) as stock_sem_validade'),
                DB::raw('COALESCE(l.total_stock_lotes, 0) as stock_com_validade')
            );

            $stockFilters = is_array($stock) ? $stock : explode(',', $stock);

            $produtoQuery->where(function ($q) use ($stockFilters) {
                foreach ($stockFilters as $f) {
                    $f = strtolower(trim($f));

                    if ($f === 'positivo') {
                        // Stock total > 0 (considera ambos tipos de produto)
                        $q->orWhere(function ($q2) {
                            $q2->where(function ($q3) {
                                // Produtos sem validade
                                $q3->where('produtos.controla_validade', false)
                                    ->whereRaw('COALESCE(s.total_stock,0) > 0');
                            })->orWhere(function ($q4) {
                                // Produtos com validade
                                $q4->where('produtos.controla_validade', true)
                                    ->whereRaw('COALESCE(l.total_stock_lotes,0) > 0');
                            });
                        });
                    } elseif ($f === 'negativo' || $f === 'menor_que_0' || $f === 'menorque0') {
                        // Stock total < 0 (apenas produtos sem validade podem ter stock negativo)
                        $q->orWhere(function ($q2) {
                            $q2->where('produtos.controla_validade', false)
                                ->whereRaw('COALESCE(s.total_stock,0) < 0');
                        });
                    } elseif ($f === 'nulo' || $f === 'zero') {
                        // Stock igual a 0 ou sem stock
                        $q->orWhere(function ($q2) {
                            $q2->where(function ($q3) {
                                // Produtos sem validade
                                $q3->where('produtos.controla_validade', false)
                                    ->where(function ($q4) {
                                        $q4->whereNull('s.total_stock')
                                            ->orWhereRaw('COALESCE(s.total_stock,0) = 0');
                                    });
                            })->orWhere(function ($q5) {
                                // Produtos com validade
                                $q5->where('produtos.controla_validade', true)
                                    ->where(function ($q6) {
                                        $q6->whereNull('l.total_stock_lotes')
                                            ->orWhereRaw('COALESCE(l.total_stock_lotes,0) = 0');
                                    });
                            });
                        });
                    } elseif ($f === 'menor_que_stock_min' || $f === 'menor_que_stockmin') {
                        // Stock total menor que stock_min (apenas produtos sem validade)
                        $q->orWhere(function ($q2) {
                            $q2->where('produtos.controla_validade', false)
                                ->whereRaw('COALESCE(s.total_stock,0) < COALESCE(produtos.stock_min,0)');
                        });
                    } elseif ($f === 'sem_controlo' || $f === 'semcontrolo') {
                        // Produtos que não controlam stock
                        $q->orWhere('produtos.controla_stock', 0);
                    }
                }
            });
        }

        // ==========================================
        // BUSCAR PRODUTOS
        // ==========================================
        $produtos = $produtoQuery
            ->where('empresa_id', $idEmpresa)
            ->with(['marca', 'categoria', 'subCategoria', 'tipoIva', 'motivoIsencao', 'fornecedor', 'stocks'])
            ->orderByDesc('id')
            ->paginate($per_page);

        // ==========================================
        // ADICIONAR QUANTIDADES POR ARMAZÉM
        // ==========================================
        $produtos->getCollection()->transform(function ($produto) {

            // ==========================================
            // PRODUTO COM VALIDADE (usa lotes_produto)
            // ==========================================
            if ($produto->controla_validade) {
                // Buscar lotes agrupados por armazém
                $lotesPorArmazem = LoteProduto::where('produto_id', $produto->id)
                    ->where('status', 'activo')
                    ->where('qtd_atual', '>', 0)
                    ->where('data_validade', '>=', now())
                    ->get()
                    ->groupBy('armazem_id');

                $quantidades = [];

                foreach ($lotesPorArmazem as $armazemId => $lotes) {
                    $quantidades[$armazemId] = $lotes->sum('qtd_atual');
                }

                // Se não tem lotes em nenhum armazém, buscar configurações existentes
                if (empty($quantidades)) {
                    $stocksConfig = $produto->stocks->keyBy('armazem_id');
                    foreach ($stocksConfig as $armazemId => $config) {
                        $quantidades[$armazemId] = 0;
                    }
                }

                $produto->quantidades = $quantidades;

                // Adicionar stock total consolidado
                $produto->stock_total = array_sum($quantidades);
            }

            // ==========================================
            // PRODUTO SEM VALIDADE (usa stocks)
            // ==========================================
            else {
                $quantidades = $produto->stocks
                    ->groupBy('armazem_id')
                    ->map(function ($stocks) {
                        return $stocks->sum('stock_atual');
                    });

                $produto->quantidades = $quantidades;
                $produto->stock_total = $quantidades->sum();
            }

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
            'controla_validade' => 'nullable|boolean',
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
        $produto = Produto::with(['movimentosStock', 'stocks'])->findOrFail($id);

        // ==========================================
        // VERIFICAR SE PRODUTO CONTROLA VALIDADE
        // ==========================================

        if ($produto->controla_validade) {
            // ==========================================
            // CENÁRIO: Produto com validade (usa tabela lotes_produto)
            // ==========================================

            // Buscar lotes agrupados por armazém
            $lotesPorArmazem = LoteProduto::where('produto_id', $produto->id)
                ->where('status', 'activo')
                // ->where('qtd_atual', '>', 0)
                ->where('data_validade', '>=', now())
                ->get()
                ->groupBy('armazem_id');

            // Buscar configurações de stock (stock_min, stock_ideal, stock_max)
            $stocksPorArmazem = $produto->stocks->keyBy('armazem_id');

            $quantidades = collect();

            foreach ($lotesPorArmazem as $armazemId => $lotes) {
                // Somar quantidade de todos os lotes deste armazém
                $quantidadeTotal = $lotes->sum('qtd_atual');

                // Buscar configuração de stock para este armazém
                $stockConfig = $stocksPorArmazem[$armazemId] ?? null;

                $stockIdeal = $stockConfig->stock_ideal ?? 0;
                $stockMin   = $stockConfig->stock_min ?? 0;
                $stockMax   = $stockConfig->stock_max ?? 0;

                // Definir estado do stock
                $estado = match (true) {
                    $quantidadeTotal <= 0 => 'sem_stock',
                    $quantidadeTotal < $stockMin => 'critico',
                    $quantidadeTotal < $stockIdeal => 'baixo',
                    $stockMax && $quantidadeTotal > $stockMax => 'excesso',
                    default => 'ok',
                };

                // Detalhar lotes deste armazém (opcional)
                $detalheLotes = $lotes->map(function ($lote) {
                    return [
                        'lote_id' => $lote->id,
                        'lote' => $lote->lote,
                        'codigo_lote' => $lote->codigo_lote,
                        'qtd_atual' => $lote->qtd_atual,
                        'data_validade' => $lote->data_validade->format('Y-m-d'),
                        'dias_restantes' => now()->diffInDays($lote->data_validade),
                        'localizacao' => $lote->localizacao_armazem
                    ];
                });

                $quantidades->put($armazemId, [
                    'quantidade' => (int) $quantidadeTotal,
                    'estado' => $estado,
                    'stock_min' => (int) $stockMin,
                    'stock_ideal' => (int) $stockIdeal,
                    'stock_max' => (int) $stockMax,
                    'lotes' => $detalheLotes  // Detalhamento dos lotes
                ]);
            }

            // Se não tem lotes em nenhum armazém, retorna zero para todos
            if ($quantidades->isEmpty()) {
                // Buscar todos armazéns configurados
                $todosArmazens = $produto->stocks->pluck('armazem_id');

                foreach ($todosArmazens as $armazemId) {
                    $stockConfig = $stocksPorArmazem[$armazemId] ?? null;
                    $quantidades->put($armazemId, [
                        'quantidade' => 0,
                        'estado' => 'sem_stock',
                        'stock_min' => (int) ($stockConfig->stock_min ?? 0),
                        'stock_ideal' => (int) ($stockConfig->stock_ideal ?? 0),
                        'stock_max' => (int) ($stockConfig->stock_max ?? 0),
                        'lotes' => []
                    ]);
                }
            }
        } else {
            // ==========================================
            // CENÁRIO: Produto SEM validade (usa tabela stocks)
            // ==========================================

            // Indexar configurações de stock por armazém
            $stocksPorArmazem = $produto->stocks->keyBy('armazem_id');

            // Buscar TODOS os armazéns que têm configuração de stock para este produto
            $todosArmazensConfigurados = $produto->stocks->pluck('armazem_id')->toArray();

            // Se não houver configuração em nenhum armazém, pelo menos retorna vazio
            if (empty($todosArmazensConfigurados)) {
                $quantidades = [];
            } else {
                $quantidades = [];
                foreach ($todosArmazensConfigurados as $armazemId) {
                    $stockConfig = $stocksPorArmazem[$armazemId] ?? null;

                    $quantidade = $stockConfig->stock_atual ?? 0;
                    $stockIdeal = $stockConfig->stock_ideal ?? 0;
                    $stockMin   = $stockConfig->stock_min ?? 0;
                    $stockMax   = $stockConfig->stock_max ?? 0;

                    // Definir estado do stock
                    $estado = match (true) {
                        $quantidade <= 0 => 'sem_stock',
                        $quantidade < $stockMin => 'critico',
                        $quantidade < $stockIdeal => 'baixo',
                        $stockMax && $quantidade > $stockMax => 'excesso',
                        default => 'ok',
                    };

                    $quantidades[$armazemId] = [
                        'quantidade'   => (int) $quantidade,
                        'estado'       => $estado,
                        'stock_min'    => (int) $stockMin,
                        'stock_ideal'  => (int) $stockIdeal,
                        'stock_max'    => (int) $stockMax,
                        'lotes'        => []
                    ];
                }
            }
        }

        $produto->quantidades = $quantidades;

        // Períodos
        $hoje = Carbon::today();
        $ontem = Carbon::yesterday();
        $inicioMes = Carbon::now()->startOfMonth();
        $inicioMesAnterior = Carbon::now()->subMonth()->startOfMonth();
        $fimMesAnterior = Carbon::now()->subMonth()->endOfMonth();

        //Função auxiliar de vendas
        $getDados = function ($start, $end = null) use ($produto) {
            $query = DB::table('itens_documento')
                ->join('documentos', 'documentos.id', '=', 'itens_documento.documento_id')
                ->where('itens_documento.produto_id', $produto->id)
                ->whereIn('documentos.tipo_sigla', ['FT', 'FR'])
                ->whereNotIn('documentos.estado_documento', ['rascunho', 'anulado', 'cancelado']);

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
                'quantidade'     => (int) ($dados->total_qtd ?? 0),
                'vendas'         => (float) ($dados->total_vendas ?? 0),
                'rentabilidade'  => (float) $lucro,
            ];
        };

        //Estatísticas
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
            'controla_validade' => 'nullable|boolean',
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

    public function changeEstado($id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto not found'], 404);
        }

        $produto->estado = $produto->estado == '1' ? '0' : '1';
        $produto->save();

        return response()->json([
            'message' => 'Produto ' . $produto->estado . ' com sucesso',
            'produto' => $produto
        ]);
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
