<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoCompra;
use App\Models\Empresa;
use App\Models\ImpostoDocumentoCompra;
use App\Models\PagamentoDocumentoCompra;
use App\Services\DocumentoCompraService;
use App\Services\DocumentoService;
use App\Services\LogotipoService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaturaCompraController extends Controller
{
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');
        $fornecedorId = $request->query("fornecedor_id");
        $tipo = $request->query('tipo'); // Tipo de documento
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $status = $request->query('status'); // pago, por_pagar, vencido
        $entidadeId = $request->query('entidade_id'); // cliente
        $valorMin = $request->query('valor_min');
        $valorMax = $request->query('valor_max');

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = DocumentoCompra::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery->where(function ($q) use ($search) {
                $q->where('num_fatura', 'like', '%' . $search . '%')
                    ->orWhere('fornecedor_nome', 'like', '%' . $search . '%')
                    ->orWhere('utilizador', 'like', '%' . $search . '%')
                    ->orWhere('valor_fatura', 'like', '%' . $search . '%');
            });
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn('tipo_sigla', $tipo)
                        ->orWhereIn('tipo_nome', $tipo);
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where('tipo_sigla', $tipo)
                        ->orWhere('tipo_nome', $tipo);
                });
            }
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween('data_fatura', [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate('data_fatura', '>=', $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate('data_fatura', '<=', $dataFinal);
        }

        // 👤 Filtrar por fornecedor/entidade

        if ($fornecedorId) {
            $documentoQuery->where("fornecedor_id", $fornecedorId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween('valor_fatura', [$valorMin, $valorMax]);
        } elseif ($valorMin) {
            $documentoQuery->where('valor_fatura', '>=', $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where('valor_fatura', '<=', $valorMax);
        }

        // Filtrar por status 
        if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('valor_pago', '>=', 'valor_fatura');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('valor_pago', '<', 'valor_fatura')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('valor_pago', '<', 'valor_fatura')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        }

        $documentos = $documentoQuery
            ->where('empresa_id', $idEmpresa)
            ->with([
                'itens',
                'impostosDocumento',
                'otherItens',
                'pagamentos.documento',
                //   'documentosRelacionados', // documentos que este documento referencia
                //    'relacionadoEm',          // documentos que referenciam este documento
            ])
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function store(Request $request, DocumentoCompraService $documentoCompraService)
    {
        // Validação dos dados recebidos
        $validated = Validator::make($request->all(), [
            // Dados do documento
            'tipo_fatura' => 'nullable|string',
            'sigla_fatura' => 'nullable|string',
            'tipo_cor' => 'nullable|string',

            'armazem_id' => 'nullable|integer',

            'empresa_id' => 'nullable|integer',
            'empresa_nome' => 'nullable|string',
            'empresa_nif' => 'nullable|integer',
            'empresa_telefone' => 'nullable|integer',
            'empresa_email' => 'nullable|email',
            'empresa_endereco' => 'nullable|string',

            'fornecedor_id' => 'nullable|integer',
            'fornecedor_nome' => 'required|string',
            'fornecedor_nif' => 'required|string',
            'fornecedor_telefone' => 'nullable|string',
            'fornecedor_email' => 'nullable|email',
            'fornecedor_endereco' => 'nullable|string',

            'data_fatura' => 'required|date',
            'data_vencimento' => 'required|date',

            'taxa_iva' => 'nullable|numeric',
            'valor_fatura' => 'nullable|numeric',

            'obs_pagamento' => 'nullable|string',

            'desconto_total' => 'nullable|numeric',
            'valor_transporte' => 'nullable|numeric',
            'total_sem_desconto' => 'nullable|numeric',
            'total_impostos' => 'nullable|numeric',
            'total_geral' => 'nullable|numeric',

            'paga' => 'nullable|boolean',
            'local_entrega' => 'nullable|string',
            'data_recepcao' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'valor_pago' => 'nullable|numeric',
            'troco' => 'nullable|numeric',

            // Itens do documento
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.produto_nome' => 'required|string',
            'itens.*.codigo_produto' => 'nullable|string',
            'itens.*.preco_custo' => 'required|numeric',
            'itens.*.preco_venda' => 'nullable|numeric',
            'itens.*.descricao' => 'nullable|string',
            'itens.*.quantidade' => 'nullable|integer',
            'itens.*.desconto_percent' => 'nullable|numeric',
            'itens.*.desconto_fixo' => 'nullable|numeric',
            'itens.*.iva_percent' => 'nullable|numeric',
            //CAMPOS DE LOTE
            'itens.*.lote_id' => 'nullable|integer',
            'itens.*.lote' => 'nullable|string',
            'itens.*.codigo_lote' => 'nullable|string',
            'itens.*.lote_codigo_barras' => 'nullable|string',
            'itens.*.lote_data_validade' => 'nullable|date',

            //Outros itens do documento
            'other_itens' => 'nullable|array',
            'other_itens.*.nome' => 'required|String',
            'other_itens.*.descricao' => 'nullable|String',
            'other_itens.*.preco_custo' => 'required|numeric',
            'other_itens.*.quantidade' => 'nullable|integer',
            'other_itens.*.desconto_percent' => 'nullable|numeric',
            'other_itens.*.desconto_fixo' => 'nullable|numeric',
            'other_itens.*.iva_percent' => 'nullable|numeric',
        ], [
            // Mensagens personalizadas de validação
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser uma string.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'email' => 'O campo :attribute deve ser um email válido.',
            'date' => 'O campo :attribute deve ser uma data válida.',
            'array' => 'O campo :attribute deve ser uma lista.',
            'min' => [
                'array' => 'O campo :attribute deve ter pelo menos :min item(ns).',
            ],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação.',
                'errors' => $validated->errors(),
            ], 422);
        }

        // === QUADRO DE IMPOSTOS (corrigido) ===
        $quadro = []; // [ '14.00' => ['taxa'=>'14.00', 'incidencia'=>..., 'iva'=>..., 'total'=>...] ]
        $totalBaseAntesDescontoGeral = 0.0; // soma das bases (sem IVA) após descontos por item
        $totalIvaAntesDescontoGeral  = 0.0;
        $descontoItensTotal = 0;
        $descontoGeral = 0;
        $totalSemDesconto = 0;

        // 1) Agrupar por taxa com DESCONTOS DE ITEM aplicados (base SEM IVA)
        foreach ($request->itens as $item) {
            $qtd        = (float)($item['quantidade'] ?? 0);
            $preco      = (float)($item['preco_custo'] ?? 0);
            $taxaIva    = (float)($item['iva_percent'] ?? 0); // ex.: 14
            $desPct     = isset($item['desconto_percent']) ? (float)$item['desconto_percent'] : 0.0;
            $desFixo    = isset($item['desconto_fixo']) ? (float)$item['desconto_fixo'] : 0.0;

            $precoBruto = $qtd * $preco;

            // desconto do item
            $descontoItem = 0.0;
            if ($desPct > 0) {
                $descontoItem = round($precoBruto * ($desPct / 100), 2);
            } elseif ($desFixo > 0) {
                $descontoItem = round($desFixo * $qtd, 2);
            }

            $descontoItensTotal += $descontoItem;

            $totalSemDesconto += $precoBruto - $descontoItem;


            // BASE SEM IVA do item (já com desconto do item)
            $baseItem = max(0, round($precoBruto - $descontoItem, 2));
            $ivaItem  = round($baseItem * ($taxaIva / 100), 2);
            $totItem  = round($baseItem + $ivaItem, 2);

            $k = number_format($taxaIva, 2, '.', ''); // "0.00", "14.00", etc.
            if (!isset($quadro[$k])) {
                $quadro[$k] = ['taxa' => $k, 'incidencia' => 0.0, 'iva' => 0.0, 'total' => 0.0];
            }

            $quadro[$k]['incidencia'] += $baseItem; // SEM IVA
            $quadro[$k]['iva']        += $ivaItem;
            $quadro[$k]['total']      += $totItem;

            $totalBaseAntesDescontoGeral += $baseItem;
            $totalIvaAntesDescontoGeral  += $ivaItem;
        }

        // 2) Aplicar DESCONTO GERAL corretamente
        $descontoGeralTipo  = $request['desconto_tipo'] ?? null;    // 'percentual' | 'fixo' | null
        $descontoGeralValor = (float)($request['desconto_total'] ?? 0);

        // Total bruto COM IVA (antes do desconto geral)
        $totalBrutoComIva = $totalBaseAntesDescontoGeral + $totalIvaAntesDescontoGeral;


        // Percentual: reduzir a BASE de cada grupo pela mesma percentagem
        if ($descontoGeralTipo === 'percentual' && $descontoGeralValor > 0) {
            $p = $descontoGeralValor / 100;
            //$descontoGeral = $totalSemDesconto * ($descontoGeralValor / 100);
            $descontoGeral = round($totalBrutoComIva * ($descontoGeralValor / 100), 2);
            foreach ($quadro as &$linha) {
                // reduzir a incidência (SEM IVA)
                $linha['incidencia'] = round($linha['incidencia'] * (1 - $p), 2);
                // recalcular IVA e Total
                $tx = (float)$linha['taxa'];
                $linha['iva']   = round($linha['incidencia'] * ($tx / 100), 2);
                $linha['total'] = round($linha['incidencia'] + $linha['iva'], 2);
            }
            unset($linha);
        }
        // Fixo: distribuir o valor do desconto pela BASE dos grupos proporcionalmente
        elseif ($descontoGeralTipo === 'fixo' && $descontoGeralValor > 0) {
            $descontoGeral = round($descontoGeralValor, 2);
            $baseTotal = array_sum(array_column($quadro, 'incidencia'));
            if ($baseTotal > 0) {
                $keys = array_keys($quadro);
                $last = end($keys);
                $acum = 0.0;

                foreach ($keys as $k) {
                    $linha = &$quadro[$k];
                    $parte = $descontoGeralValor * ($linha['incidencia'] / $baseTotal);
                    if ($k !== $last) {
                        $parte = round($parte, 2);
                        $acum += $parte;
                    } else {
                        // corrige resíduos de arredondamento
                        $parte = round($descontoGeralValor - $acum, 2);
                    }

                    $linha['incidencia'] = max(0, round($linha['incidencia'] - $parte, 2));
                    $tx = (float)$linha['taxa'];
                    $linha['iva']   = round($linha['incidencia'] * ($tx / 100), 2);
                    $linha['total'] = round($linha['incidencia'] + $linha['iva'], 2);

                    unset($linha);
                }
            }
        }

        // 3) (Opcional) ordenar por taxa
        uksort($quadro, fn($a, $b) => (float)$a <=> (float)$b);

        // 4) Se quiseres devolver como LISTA (igual ao quadro da imagem)
        $quadroImpostos = array_values(array_map(function ($l) {
            return [
                'taxa'       => $l['taxa'],
                'incidencia' => $l['incidencia'],
                'iva'        => $l['iva'],
                'total'      => $l['total'],
            ];
        }, $quadro));


        // IVA cheio (antes do desconto geral) — como aparece no resumo da fatura
        $totalImpostos = $totalIvaAntesDescontoGeral;

        // Desconto total (itens + desconto global aplicado sobre total com IVA)
        $desconto_total = $descontoItensTotal + $descontoGeral;

        $totalFinal = ($totalSemDesconto - $descontoItensTotal) - $descontoGeral;

        $retencao = 0;
        $troco = 0;


        try {
            DB::beginTransaction();

            // Criação do documento
            $documento = DocumentoCompra::create([
                'tipo_nome' => '',
                'tipo_sigla' => '',
                //'tipo_cor' => $request['tipo_cor'],

                'armazem_id' => $request['armazem_id'],

                'num_fatura' => $request['num_fatura'],
                'via' => 'original',

                'empresa_id' => $request['empresa_id'],
                'empresa_nome' => $request['empresa_nome'],
                'empresa_nif' => $request['empresa_nif'],
                'empresa_telefone' => $request['empresa_telefone'],
                'empresa_email' => $request['empresa_email'],
                'empresa_endereco' => $request['empresa_endereco'],

                'fornecedor_id' => $request['fornecedor_id'] ?? null,
                'fornecedor_nome' => $request['fornecedor_nome'],
                'fornecedor_nif' => $request['fornecedor_nif'],
                'fornecedor_telefone' => $request['fornecedor_telefone'],
                'fornecedor_email' => $request['fornecedor_email'],
                'fornecedor_endereco' => $request['fornecedor_endereco'],

                'data_fatura' => $request['data_fatura'],
                'data_vencimento' => $request['data_vencimento'],

                'obs_pagamento' => $request['obs_pagamento'],

                'taxa_iva' => '0',
                'valor_fatura' => $request['valor_fatura'],
                'retencao' => $retencao,

                'hash' => Str::random(40),

                'desconto_total' => $desconto_total,
                'total_sem_desconto' => $totalSemDesconto,
                'total_impostos' => $totalImpostos,
                'total_geral' => $request['total_geral'], //$trotalFinal,
                'troco' => $troco,

                'utilizador_id' => $request['utilizador_id'],
                'utilizador' => $request['utilizador'],

                'local_entrega' => $request['local_entrega'],
                'data_recepcao' => $request['data_recepcao'],
                'observacoes' => $request['observacoes'],
                'paga' => $request['paga'],
                'valor_pago' => $request['valor_pago'],

            ]);


            foreach ($quadroImpostos as $value) {
                $value['incidencia'] = round($value['incidencia'], 2);
                $value['iva'] = round($value['iva'], 2);
                //$value['liquido'] = round($value['liquido'], 2);

                ImpostoDocumentoCompra::create([
                    'documento_compra_id' => $documento->id,
                    'taxa' => $value['taxa'],
                    'incidencia' => $value['incidencia'],
                    'imposto' => $value['iva'],
                    //'liquido' => $value['liquido'],
                    'total' => $value['incidencia'] + $value['iva'],
                ]);
            }

            // Criação dos itens
            $itens = [];
            foreach ($request['itens'] as $item) {

                $taxaIva = $item['iva_percent'];

                $desconto = 0;
                if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                    $desconto = $item['preco_custo'] * ($item['desconto_percent'] / 100);
                } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                    $desconto = $item['desconto_fixo'];
                }

                // Total sem IVA
                $totalSemDesconto = $item['preco_custo'] * $item['quantidade'];
                $totalSemIva = $totalSemDesconto - $desconto;

                // IVA do item
                $valorIva = $totalSemIva * ($taxaIva / 100);

                // Total com IVA
                $totalComIva = $totalSemIva + $valorIva;

                $loteDataValidade = $item['lote_data_validade']
                    ? Carbon::parse($item['lote_data_validade'])->format('Y-m-d')
                    : null;

                $itens[] = [
                    'documento_compra_id' => $documento->id,
                    'produto_id' => $item['produto_id'],
                    'produto_nome' => $item['produto_nome'],
                    'produto_codigo' => $item['codigo_produto'],
                    'preco_custo' => $item['preco_custo'],
                    // 'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'desconto_percent' => $item['desconto_percent'],
                    'desconto_fixo' => $item['desconto_fixo'],
                    'iva_percent' => $taxaIva ?? 0,
                    'total_sem_desconto' => $totalSemDesconto,
                    'valor_imposto' => $valorIva,
                    'total_sem_imposto' => $totalSemIva,
                    'total' => $totalComIva,
                    //LOTES
                    'lote_id' => $item['lote_id'],
                    'lote' => $item['lote'],
                    'codigo_lote' => $item['codigo_lote'],
                    'lote_codigo_barras' => $item['lote_codigo_barras'],
                    'lote_data_validade' => $loteDataValidade,
                ];
            }

            //Cricao de outros itens
            $otherItens = [];
            foreach ($request['other_itens'] as $item) {


                $taxaIva = $item['iva_percent'];

                $desconto = 0;
                if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                    $desconto = $item['preco_custo'] * ($item['desconto_percent'] / 100);
                } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                    $desconto = $item['desconto_fixo'];
                }

                // Calcula o total do item (sem IVA)
                // Total sem IVA
                $totalSemDesconto = $item['preco_custo'] * $item['quantidade'];
                $totalSemIva = $totalSemDesconto - $desconto;

                // IVA do item
                $valorIva = $totalSemIva * ($taxaIva / 100);

                // Total com IVA
                $totalComIva = $totalSemIva + $valorIva;

                $otherItens[] = [
                    'documento_compra_id' => $documento->id,
                    'nome' => $item['nome'],
                    'preco_custo' => $item['preco_custo'],
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'desconto_percent' => $item['desconto_percent'],
                    'desconto_fixo' => $item['desconto_fixo'],
                    'iva_percent' => $taxaIva ?? 0,
                    'total_sem_desconto' => $totalSemDesconto,
                    'valor_imposto' => $valorIva,
                    'total_sem_imposto' => $totalSemIva,
                    'total' => $totalComIva,
                ];
            }

            //Cadastra os itens do documento
            $documento->itens()->createMany($itens);

            //Cadastra outros itens do documento
            $documento->otherItens()->createMany($otherItens);

            //Se a fatura foi paga, cadastrar o pagamento
            if ($request->boolean('paga')) {
                PagamentoDocumentoCompra::create([
                    "documento_compra_id" => $documento->id,
                    "observacao" => "",
                    "data_pagamento" => now(),
                    "valor" => $request['valor_pago'],
                ]);
            }

            //Atualiza o stock de cada produto da fatura
            $documentoCompraService->updateStock($documento->load("itens"));

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Erro ao criar o documento.",
                    "error" => $th->getMessage(),
                ],
                500,
            );
        }

        return response()->json([
            'message' => 'Documento de compra criado com sucesso.',
            'documento' => $documento->load(['itens', 'otherItens', 'pagamentos.documento'])
        ], 201);
    }

    public function show($id)
    {
        $documento = DocumentoCompra::with(['itens', 'impostosDocumento', 'otherItens', 'pagamentos.documento'])->find($id);

        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        return response()->json($documento);
    }

    public function destroy(string $id)
    {
        $documento = DocumentoCompra::with(['itens', 'otherItens', 'impostosDocumento', 'pagamentos'])->find($id);

        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        try {
            DB::beginTransaction();

            // Apagar relacionamentos (pagamentos, impostos, itens, outros itens)
            if ($documento->movimentosStock()->exists()) {
                $documento->movimentosStock()->delete();
            }

            if ($documento->pagamentos()->exists()) {
                $documento->pagamentos()->delete();
            }
            if ($documento->impostosDocumento()->exists()) {
                $documento->impostosDocumento()->delete();
            }
            if ($documento->itens()->exists()) {
                $documento->itens()->delete();
            }
            if ($documento->otherItens()->exists()) {
                $documento->otherItens()->delete();
            }

            // Apagar documento
            $documento->delete();

            DB::commit();

            return response()->json(['message' => 'Documento removido com sucesso.'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao remover o documento.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function pdfRelatorioDocumentoCompra(Request $request)
    {
        $fornecedorId = $request->query("fornecedor_id");
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = DocumentoCompra::query();

        // 👤 Filtrar por fornecedor
        if ($fornecedorId) {
            $documentoQuery->where("fornecedor_id", $fornecedorId);
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_fatura", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_fatura", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_fatura", "<=", $dataFinal);
        }

        $documentos = $documentoQuery
            ->where("empresa_id", $idEmpresa)
            ->with([
                "itens",
                "impostosDocumento",
            ])
            ->orderByDesc("id")
            ->get();

        $totalGeral = $documentos->sum("total_geral");

        $dadosEmpresa = Empresa::find($idEmpresa);

        $logoData = app(LogotipoService::class)->carregar($idEmpresa);
        $src = $logoData['src'];
        $dadosPersonalizacaoFatura = $logoData['dadosPersonalizacaoFatura'];

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.relatorio-documento-compra",
            compact([
                "documentos",
                "dataInicial",
                "dataFinal",
                "totalGeral",
                "dadosEmpresa",
                "src",
                "dadosPersonalizacaoFatura",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = "relatorio"; //str_replace([' ', '/'], '_', $documento['num_fatura']);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }
}
