<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoCompra;
use App\Models\ImpostoDocumentoCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaturaCompraController extends Controller
{
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');
        $tipo = $request->query('tipo'); // Tipo de documento
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $status = $request->query('status'); // pago, por_pagar, vencido
        $entidadeId = $request->query('entidade_id'); // cliente
        $valorMin = $request->query('valor_min');
        $valorMax = $request->query('valor_max');

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
        if ($entidadeId) {
            $documentoQuery->where('fornecedor_id', $entidadeId);
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
            ->with([
                'itens',
                'impostosDocumento',
             //   'documentosRelacionados', // documentos que este documento referencia
            //    'relacionadoEm',          // documentos que referenciam este documento
            ])
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function store(Request $request)
    {
        // Validação dos dados recebidos
        $validated = Validator::make($request->all(), [
            // Dados do documento
            'tipo_fatura' => 'nullable|string',
            'sigla_fatura' => 'nullable|string',
            'tipo_cor' => 'nullable|string',

            'empresa_id' => 'nullable|integer',
            'empresa_nome' => 'required|string',
            'empresa_nif' => 'required|integer',
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
            'itens.*.produto_nome' => 'required|string',
            'itens.*.codigo_produto' => 'nullable|string',
            'itens.*.preco_custo' => 'required|numeric',
            'itens.*.preco_venda' => 'nullable|numeric',
            'itens.*.descricao' => 'nullable|string',
            'itens.*.quantidade' => 'nullable|integer',
            'itens.*.desconto_percent' => 'nullable|numeric',
            'itens.*.desconto_fixo' => 'nullable|numeric',
            'itens.*.iva_percent' => 'nullable|numeric',
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


        // Criação do documento
        $documento = DocumentoCompra::create([
            'tipo_nome' => '',
            'tipo_sigla' => '',
            //'tipo_cor' => $request['tipo_cor'],

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

            'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',

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
                $desconto = $item['preco_venda'] * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            // Calcula o total do item (sem IVA)
            $totalSemDesconto = $item['preco_custo'] * $item['quantidade'];
            $totalItem = $totalSemDesconto - $desconto;

            $itens[] = [
                'documento_compra_id' => $documento->id,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_custo' => $item['preco_custo'],
                // 'descricao' => $item['descricao'],
                'quantidade' => $item['quantidade'],
                'desconto_percent' => $item['desconto_percent'],
                'desconto_fixo' => $item['desconto_fixo'],
                'iva_percent' => $taxaIva ?? 0,
                // 'imposto_taxa_id' => $idImpostoTaxa,
                // 'codigo_iva' => $codigoIva ?? '',
                // 'motivo_isencao' => $motivoIsencaoDescricao,
                'total_sem_desconto' => $totalSemDesconto,
                'total' => $totalItem,
                // Adicione outros campos conforme necessário
            ];
        }

        $documento->itens()->createMany($itens);

        return response()->json([
            'message' => 'Documento de compra criado com sucesso.',
            'documento' => $documento->load('itens')
        ], 201);
    }
}
