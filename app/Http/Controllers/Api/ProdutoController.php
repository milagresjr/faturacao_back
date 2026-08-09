<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\CategoriaProduto;
use App\Models\Fornecedor;
use App\Models\LoteProduto;
use App\Models\Marca;
use App\Models\Produto;
use App\Models\Stock;
use App\Models\SubCategoria;
use App\Models\TipoTaxaIva;
use App\Models\TipoStock;
use App\Services\LogotipoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            'tipo_stock_id' => 'nullable|integer|exists:tipo_stock,id',
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
        $produto = Produto::with(['tipoStock','subCategoria','movimentosStock', 'stocks', 'categoria'])->findOrFail($id);

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

        $logoData = app(LogotipoService::class)->carregar($empresaId);
        $src = $logoData['src'];
        $dadosPersonalizacaoFatura = $logoData['dadosPersonalizacaoFatura'];

        $pdf = Pdf::loadView('pdf.produto-relatorio', [
            'produtos' => $produtosFormatados,
            'data' => Carbon::now()->format('d/m/Y H:i'),
            'filtros' => $request->all(),
            'src' => $src,
            'dadosPersonalizacaoFatura' => $dadosPersonalizacaoFatura,
        ])->setPaper('A4', 'landscape');

        return $pdf->stream('relatorio_produtos.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORTAR / IMPORTAR
    |--------------------------------------------------------------------------
    */

    private function colunasExportacao(): array
    {
        return [
            'nome',
            'descricao',
            'codigo_produto',
            'codigo_barra',
            'preco_custo',
            'preco_venda',
            'preco_final',
            'margem_lucro',
            'valor_iva',
            'stock_min',
            'stock_max',
            'stock_ideial',
            'unidade',
            'imposto',
            'tipo',
            'marca',
            'categoria',
            'sub_categoria',
            'fornecedor',
            'armazem',
            'tipo_stock',
            'controla_validade',
            'movimenta_stock',
            'estado',
        ];
    }

    private function produtosFormatadosExport(Request $request): array
    {
        $empresaId = $request->input('empresa_id');

        $query = Produto::with(['marca', 'categoria', 'subCategoria', 'fornecedor', 'armazem'])
            ->where('empresa_id', $empresaId);

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

        return $query->orderByDesc('id')
            ->get()
            ->map(function ($produto) {
                $tipoIva = $produto->tipoIva;
                return [
                    $produto->nome,
                    $produto->descricao ?? '',
                    $produto->codigo_produto ?? '',
                    $produto->codigo_barra ?? '',
                    $produto->preco_custo ?? 0,
                    $produto->preco_venda ?? 0,
                    $produto->preco_final ?? 0,
                    $produto->margem_lucro ?? 0,
                    $produto->valor_iva ?? 0,
                    $produto->stock_min ?? 0,
                    $produto->stock_max ?? 0,
                    $produto->stock_ideial ?? 0,
                    $produto->unidade ?? '',
                    $produto->imposto ?? ($tipoIva ? $tipoIva->id : ''),
                    $produto->tipo->nome ?? '',
                    $produto->marca->nome ?? '',
                    $produto->categoria->nome ?? '',
                    $produto->subCategoria->nome ?? '',
                    $produto->fornecedor->nome ?? '',
                    $produto->armazem->nome ?? '',
                    $produto->tipoStock ? ($produto->tipoStock->tipo ?? '') : '',
                    $produto->controla_validade ? 'sim' : 'nao',
                    $produto->movimenta_stock ? 'sim' : 'nao',
                    $produto->estado ? 'ativo' : 'inativo',
                ];
            })
            ->toArray();
    }

    public function exportarCsv(Request $request)
    {
        $linhas = $this->produtosFormatadosExport($request);

        $csv = fopen('php://temp', 'r+');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, array_merge(['id'], $this->colunasExportacao()), separator: ',', enclosure: '"', escape: '\\');

        $linhas = array_map(function ($linha, $indice) {
            return array_merge([$indice + 1], $linha);
        }, $linhas, array_keys($linhas));

        foreach ($linhas as $linha) {
            fputcsv($csv, $linha, separator: ',', enclosure: '"', escape: '\\');
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="produtos.csv"',
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $linhas = $this->produtosFormatadosExport($request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produtos');

        $headings = array_merge(['id'], $this->colunasExportacao());

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Cabeçalho
        foreach ($headings as $indice => $titulo) {
            $coordenada = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1) . '1';
            $sheet->setCellValue($coordenada, $titulo);
        }

        $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
        ]);

        $linhas = array_map(function ($linha, $indice) {
            return array_merge([$indice + 1], $linha);
        }, $linhas, array_keys($linhas));

        $sheet->fromArray($linhas, null, 'A2');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="produtos.xlsx"',
        ]);
    }

    public function importar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
            'empresa_id' => 'required|integer|exists:empresas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $empresaId = (int) $request->input('empresa_id');
        $utilizadorId = $request->input('utilizador_id');

        $linhas = $this->lerLinhasArquivo($request->file('file'));

        if (count($linhas) < 2) {
            return response()->json(['message' => 'O arquivo está vazio.'], 422);
        }

        $cabecalho = array_map([$this, 'normalizarColuna'], $linhas[0]);
        $cabecalhoOriginal = array_map(fn($v) => trim((string) $v), $linhas[0]);

        // Mapeamento custom vindo do frontend: { coluna_original: campo_interno }
        $mapeamentoCustom = $this->mapeamentoCustom($request);

        $mapa = $this->construirMapa($cabecalho, $cabecalhoOriginal, $mapeamentoCustom);

        $criados = 0;
        $atualizados = 0;
        $erros = [];

        foreach (array_slice(array_values($linhas), 1) as $indice => $linha) {
            $numeroLinha = $indice + 2;

            if (!isset($linha[0])) {
                continue;
            }

            $linha = array_values($linha);
            $dados = [];

            foreach ($mapa as $campo => $posicao) {
                $dados[$campo] = $linha[$posicao] ?? null;
            }

            $nome = trim((string) ($dados['nome'] ?? ''));

            if ($nome === '') {
                $erros[] = ['linha' => $numeroLinha, 'erro' => 'Campo "nome" é obrigatório.'];
                continue;
            }

            // Resolver relações por nome ou id
            $tipoId = $this->resolverTipo($dados['tipo'] ?? null, $empresaId);
            $marcaId = $this->resolverPorNome('Marca', $dados['marca'] ?? null, $empresaId);
            $categoriaId = $this->resolverPorNome('CategoriaProduto', $dados['categoria'] ?? null, $empresaId);
            $subCategoriaId = $this->resolverPorNome('SubCategoria', $dados['sub_categoria'] ?? null, $empresaId);
            $fornecedorId = $this->resolverPorNome('Fornecedor', $dados['fornecedor'] ?? null, $empresaId);
            $armazemId = $this->resolverPorNome('Armazem', $dados['armazem'] ?? null, $empresaId);

            $imposto = $this->resolverImposto($dados['imposto'] ?? null, $empresaId);

            $data = [
                'nome' => $nome,
                'descricao' => $dados['descricao'] ?? null,
                'codigo_produto' => $dados['codigo_produto'] ?? null,
                'codigo_barra' => $dados['codigo_barra'] ?? null,
                'preco_custo' => $this->numerico($dados['preco_custo'] ?? 0) ?: 0,
                'preco_venda' => $this->numerico($dados['preco_venda'] ?? 0) ?: 0,
                'preco_final' => $this->numerico($dados['preco_final'] ?? 0) ?: 0,
                'margem_lucro' => $this->numerico($dados['margem_lucro'] ?? 0) ?: 0,
                'valor_iva' => $this->numerico($dados['valor_iva'] ?? 0) ?: 0,
                'stock_min' => (int) ($this->numerico($dados['stock_min'] ?? 0) ?: 0),
                'stock_max' => (int) ($this->numerico($dados['stock_max'] ?? 0) ?: 0),
                'stock_ideial' => (int) ($this->numerico($dados['stock_ideial'] ?? 0) ?: 0),
                'unidade' => $dados['unidade'] ?? 'UNI',
                'imposto' => $imposto,
                'marca_id' => $marcaId,
                'categoria_id' => $categoriaId,
                'sub_categoria_id' => $subCategoriaId,
                'fornecedor_id' => $fornecedorId,
                'armazem_id' => $armazemId,
                'tipo_stock_id' => $this->resolverTipoStock($dados['tipo_stock'] ?? null, $empresaId),
                'controla_validade' => $this->simNao($dados['controla_validade'] ?? 'nao'),
                'movimenta_stock' => $this->simNao($dados['movimenta_stock'] ?? 'sim'),
                'estado' => $this->ativoInativo($dados['estado'] ?? 'ativo'),
                'empresa_id' => $empresaId,
                'tipo_id' => $tipoId ?? 1,
            ];

            try {
                $produto = null;

                if (!empty($dados['codigo_produto'])) {
                    $produto = Produto::where('empresa_id', $empresaId)
                        ->where('codigo_produto', $dados['codigo_produto'])
                        ->withTrashed()
                        ->first();
                } elseif ($nome !== '') {
                    $produto = Produto::where('empresa_id', $empresaId)
                        ->where('nome', $nome)
                        ->first();
                }

                if ($produto) {
                    if ($produto->trashed()) {
                        $produto->restore();
                    }
                    // Na atualização, aplicar apenas as colunas presentes no arquivo
                    $colunasPresentes = array_keys(array_filter($dados, fn($v) => $v !== null && $v !== ''));
                    $camposAtualizar = array_intersect($colunasPresentes, $this->colunasExportacao());
                    $produto->update(array_intersect_key($data, array_flip($camposAtualizar)));
                    $atualizados++;
                } else {
                    if (empty($data['codigo_produto'])) {
                        $data['codigo_produto'] = $this->gerarCodigoProduto($nome, $tipoId == 1 ? 'P' : 'S', $empresaId);
                    }
                    if (!$utilizadorId) {
                        $utilizadorId = $request->input('utilizador_id');
                    }
                    $data['utilizador_id'] = $utilizadorId;

                    $produto = DB::transaction(function () use ($data) {
                        $novo = Produto::create($data);

                        $armazens = Armazem::where('empresa_id', $data['empresa_id'])->get();
                        foreach ($armazens as $armz) {
                            Stock::firstOrCreate(
                                ['produto_id' => $novo->id, 'armazem_id' => $armz->id],
                                ['empresa_id' => $data['empresa_id'], 'stock_min' => 0, 'stock_ideal' => 0, 'stock_max' => 0, 'stock_atual' => 0]
                            );
                        }

                        return $novo;
                    });

                    $criados++;
                }
            } catch (\Throwable $e) {
                $erros[] = ['linha' => $numeroLinha, 'erro' => $e->getMessage()];
            }
        }

        return response()->json([
            'criados' => $criados,
            'atualizados' => $atualizados,
            'sucessos' => $criados + $atualizados,
            'erros' => $erros,
        ], 200);
    }

    private function lerLinhasArquivo($file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
                $reader->setInputEncoding(\PhpOffice\PhpSpreadsheet\Reader\Csv::GUESS_ENCODING);
            }
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable $e) {
            return [];
        }

        return $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
    }

    private function mapeamentoCustom(Request $request): array
    {
        $mapeamento = $request->input('mapeamento');

        if (is_array($mapeamento)) {
            return $mapeamento;
        }

        if (is_string($mapeamento) && $mapeamento !== '') {
            $decoded = json_decode($mapeamento, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function construirMapa(array $cabecalho, array $cabecalhoOriginal, array $mapeamentoCustom): array
    {
        if (!empty($mapeamentoCustom)) {
            $mapa = [];
            $normalizados = array_map([$this, 'normalizarColuna'], $cabecalhoOriginal);

            foreach ($mapeamentoCustom as $coluna => $campo) {
                if (empty($campo)) {
                    continue;
                }
                $colunaNorm = $this->normalizarColuna($coluna);
                $posicao = array_search($colunaNorm, $normalizados, true);

                if ($posicao === false) {
                    $posicao = array_search($this->normalizarColuna($coluna), $cabecalho, true);
                }

                if ($posicao !== false) {
                    $mapa[$campo] = $posicao;
                }
            }

            return $mapa;
        }

        return $this->mapearColunas($cabecalho);
    }

    public function importarPreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $linhas = $this->lerLinhasArquivo($request->file('file'));

        if (empty($linhas)) {
            return response()->json(['message' => 'Não foi possível ler o arquivo. Verifique o formato.'], 422);
        }

        $cabecalhos = array_values($linhas[0]);
        $cabecalhos = array_map(fn($v) => trim((string) $v), $cabecalhos);

        // Remover colunas vazias no fim
        while (end($cabecalhos) === '' && count($cabecalhos) > 0) {
            array_pop($cabecalhos);
        }

        $preview = array_slice(array_values($linhas), 1, 5);

        return response()->json([
            'cabecalhos' => $cabecalhos,
            'preview' => array_map(fn($linha) => array_values($linha), $preview),
            'total_linhas' => max(0, count(array_values($linhas)) - 1),
        ]);
    }

    public function importPresets()
    {
        $config = config('import-mappings', ['presets' => [], 'campos' => []]);

        $presets = collect($config['presets'] ?? [])->map(function ($preset, $chave) {
            return [
                'chave' => $chave,
                'titulo' => $preset['titulo'] ?? $chave,
                'mapeamento' => $preset['mapeamento'] ?? [],
            ];
        })->values();

        $campos = collect($config['campos'] ?? [])->map(function ($campo, $chave) {
            return [
                'campo' => $chave,
                'label' => $campo['label'] ?? $chave,
                'obrigatorio' => $campo['obrigatorio'] ?? false,
                'aliases' => $campo['aliases'] ?? [],
            ];
        })->values();

        return response()->json([
            'presets' => $presets,
            'campos' => $campos,
        ]);
    }

    private function normalizarColuna($valor): string
    {
        $valor = trim((string) $valor);
        $valor = mb_strtolower($valor, 'UTF-8');
        $valor = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'à', 'ê', 'ô', 'ã', 'õ', 'ç', ' '],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'o', 'a', 'o', 'c', '_'],
            $valor
        );
        return $valor;
    }

    private function mapearColunas(array $cabecalho): array
    {
        $mapa = [];
        foreach ($cabecalho as $posicao => $coluna) {
            if (in_array($coluna, ['id', 'stock_total', 'quantidade', 'preco_fixo', 'lucro_fixo'], true)) {
                continue;
            }
            $mapa[$coluna] = $posicao;
        }
        return $mapa;
    }

    private function resolverTipo($valor, int $empresaId)
    {
        $valor = is_string($valor) ? trim($valor) : $valor;
        if (is_numeric($valor)) {
            return (int) $valor;
        }
        $nome = mb_strtolower((string) $valor, 'UTF-8');
        if (str_contains($nome, 'serv')) {
            return 2;
        }
        return 1;
    }

    private function resolverPorNome(string $modelo, $valor, int $empresaId)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (int) $valor;
        }
        $model = 'App\\Models\\' . $modelo;
        $registro = $model::where('nome', 'like', '%' . trim((string) $valor) . '%')->first();
        return $registro ? $registro->id : null;
    }

    private function resolverImposto($valor, int $empresaId)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (int) $valor;
        }
        $taxa = TipoTaxaIva::where('taxa', 'like', '%' . trim((string) $valor) . '%')->first();
        return $taxa ? $taxa->id : null;
    }

    private function resolverTipoStock($valor, int $empresaId)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (int) $valor;
        }
        $tipoStock = TipoStock::where('tipo', 'like', '%' . trim((string) $valor) . '%')
            ->orWhere('sigla', 'like', '%' . trim((string) $valor) . '%')
            ->first();
        return $tipoStock ? $tipoStock->id : null;
    }

    private function simNao($valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_numeric($valor)) {
            return (int) $valor === 1;
        }
        $valor = mb_strtolower(trim((string) $valor), 'UTF-8');
        return in_array($valor, ['sim', 's', 'yes', 'true', 'verdadeiro', '1'], true);
    }

    private function ativoInativo($valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_numeric($valor)) {
            return (int) $valor === 1;
        }
        $valor = mb_strtolower(trim((string) $valor), 'UTF-8');
        return !in_array($valor, ['inativo', 'false', '0', 'no', 'nao'], true);
    }

    private function numerico($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = str_replace(['Kz', 'KZ', 'kz', ' '], '', trim((string) $valor));

        if ($valor === '' || $valor === null) {
            return 0;
        }

        // "1.234,56" (pt-BR/pt-PT: ponto como milhar, vírgula como decimal)
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }
}
