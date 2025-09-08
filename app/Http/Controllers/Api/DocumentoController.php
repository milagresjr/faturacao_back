<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\BancoDocumento;
use App\Models\Conta;
use App\Models\Documento;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use App\Models\TipoTaxaIva;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DocumentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery->where('num_fatura', 'like', '%' . $search . '%')
                ->orWhere('cliente_nome', 'like', '%' . $search . '%')
                ->orWhere('utilizador', 'like', '%' . $search . '%')
                ->orWhere('total_geral', 'like', '%' . $search . '%');
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
            $documentoQuery->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate('data_emissao', '>=', $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate('data_emissao', '<=', $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where('entidade_id', $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween('total_geral', [$valorMin, $valorMax]);
        } elseif ($valorMin) {
            $documentoQuery->where('total_geral', '>=', $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where('total_geral', '<=', $valorMax);
        }

        // Filtrar por status 
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        $documentos = $documentoQuery
            ->with([
                'itens',
                'meiosPagamento',
                'impostosDocumento',
                'documentosRelacionados', // documentos que este documento referencia
                'relacionadoEm',          // documentos que referenciam este documento
            ])
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json($documentos);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $numFatura = $this->gerarNumeroDocumento(
            $request->input('sigla_fatura'),
            $request->input('empresa_id')
        );

        // Validação dos dados recebidos
        $validated = Validator::make($request->all(), [
            // Dados do documento
            'tipo_fatura' => 'required|string',
            'sigla_fatura' => 'required|string',
            'tipo_cor' => 'nullable|string',

            'empresa_id' => 'nullable|integer',
            'empresa_nome' => 'required|string',
            'empresa_nif' => 'required|integer',
            'empresa_telefone' => 'nullable|integer',
            'empresa_email' => 'nullable|email',
            'empresa_endereco' => 'nullable|string',

            'cliente_id' => 'nullable|integer',
            'cliente_nome' => 'required|string',
            'cliente_nif' => 'required|string',
            'cliente_telefone' => 'nullable|string',
            'cliente_email' => 'nullable|email',
            'cliente_endereco' => 'nullable|string',

            'caixa' => 'required|string',
            'data_emissao' => 'required|date',
            'data_vencimento' => 'required|date',
            'is_apronto' => 'nullable|boolean',
            'movimenta_stock' => 'required|boolean',

            'taxa_iva' => 'nullable|numeric',
            'valor_iva' => 'nullable|numeric',

            'desconto_total' => 'nullable|numeric',
            'valor_transporte' => 'nullable|numeric',
            'total_sem_desconto' => 'nullable|numeric',
            'total_impostos' => 'nullable|numeric',
            'total_geral' => 'nullable|numeric',

            'meiosPagamento' => 'nullable|array',
            'meiosPagamento.*.descricao' => 'nullable|string',
            'meiosPagamento.*.valor' => 'nullable|numeric',

            // Itens do documento
            'itens' => 'required|array|min:1',
            'itens.*.produto_nome' => 'required|string',
            'itens.*.codigo_produto' => 'required|string',
            'itens.*.preco_venda' => 'required|numeric',
            'itens.*.descricao' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer',
            'itens.*.desconto_percent' => 'required|numeric',
            'itens.*.desconto_fixo' => 'required|numeric',
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

        // Construção do quadro por taxas (mantendo também o 'liquido' por grupo)
        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item['iva_percent']);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item['motivo_isencao_id'] ?? '';
            $motivo = '';

            if ($codigo === 'ISENTO' && $motivoIsencaoId) {
                $motivo = DB::table('motivo_isencao')->where('id', $motivoIsencaoId)->value('motivo');
            }

            $subtotalBruto = $item['preco_venda'] * $item['quantidade'];

            $desconto = 0;
            if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $subtotalBruto * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + ($taxaIva / 100)), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . '|' . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    'taxa' => $taxaIva,
                    'codigo' => $codigo,
                    'motivo_isencao' => $motivo,
                    'incidencia' => 0.0, // base
                    'imposto' => 0.0,
                    'liquido' => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]['incidencia'] += $base;
            $quadroImposto[$chave]['imposto'] += $imposto;
            $quadroImposto[$chave]['liquido'] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;
        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {

            $precoBruto = $item['preco_venda'] * $item['quantidade'];

            // Desconto do item
            $desconto = 0;
            if ($item['desconto_percent'] !== null && $item['desconto_percent'] > 0) {
                $desconto = $precoBruto * ($item['desconto_percent'] / 100);
            } elseif ($item['desconto_fixo'] !== null && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'] * $item['quantidade'];
            }

            $subtotalComIva = $precoBruto - $desconto;

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request['desconto_tipo'] === 'percentual') {
            $descontoGeral = $totalSemDesconto * ($request['desconto_total'] / 100);
        } elseif ($request['desconto_tipo'] === 'fixo') {
            $descontoGeral = $request['desconto_total']; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha['liquido'] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha['liquido'] = round($linha['liquido'] - $descontoLinha, 2);

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha['incidencia'] = round($linha['liquido'] / (1 + ($linha['taxa'] / 100)), 2);
                $linha['imposto'] = round($linha['liquido'] - $linha['incidencia'], 2);

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, 'liquido'));
            $totalBase = array_sum(array_column($quadroImposto, 'incidencia'));
            $totalImposto = array_sum(array_column($quadroImposto, 'imposto'));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal = ($totalSemDesconto - $descontoItensTotal) - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, 'imposto'));

        $retencao = 0;
        //Calcular retencao na fonte nos servicos
        foreach ($request['itens'] as $item) {
            if (isset($item['tipo_produto']) && $item['tipo_produto'] === 'S') {
                if ($item['preco_venda'] > 20000) {
                    $retencao += ($item['preco_venda'] * 0.06); // 5% de retenção
                }
            }
        }

        $totalEntregue = 0;

        foreach ($request->input('meiosPagamento') as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento['valor'];
        }

        $troco = $totalEntregue - $totalFinal;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }


        // Criação do documento
        $documento = Documento::create([
            'tipo_nome' => $request['tipo_fatura'],
            'tipo_sigla' => $request['sigla_fatura'],
            //'tipo_cor' => $request['tipo_cor'],

            'num_fatura' => $numFatura,
            'via' => 'original',

            'empresa_id' => $request['empresa_id'],
            'empresa_nome' => $request['empresa_nome'],
            'empresa_nif' => $request['empresa_nif'],
            'empresa_telefone' => $request['empresa_telefone'],
            'empresa_email' => $request['empresa_email'],
            'empresa_endereco' => $request['empresa_endereco'],

            'cliente_id' => $request['cliente_id'] ?? null,
            'cliente_nome' => $request['cliente_nome'],
            'cliente_nif' => $request['cliente_nif'],
            'cliente_telefone' => $request['cliente_telefone'],
            'cliente_email' => $request['cliente_email'],
            'cliente_endereco' => $request['cliente_endereco'],

            'caixa' => $request['caixa'],
            'data_emissao' => $request['data_emissao'],
            'data_vencimento' => $request['data_vencimento'],
            'forma_pagamento' => $request['forma_pagamento'],
            'movimenta_stock' => $request['movimenta_stock'],

            'taxa_iva' => '0',
            'valor_iva' => '0',
            'retencao' => $retencao,

            'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',

            'desconto_total' => $descontoItensTotal + $descontoGeral,
            'valor_transporte' => $request['valor_transporte'],
            'total_sem_desconto' => $totalSemDesconto,
            'total_impostos' => $totalImpostos,
            'total_geral' => $totalFinal,
            'troco' => $troco,

            'utilizador_id' => $request['utilizador_id'],
            'utilizador' => $request['utilizador']
        ]);


        $bancos = Conta::with('banco')
            ->where('empresa_id', $request->input('empresa_id'))
            ->where('estado', true)
            ->get();

        foreach ($bancos as $banco) {
            DB::table('bancos_documento')->insert([
                'documento_id' => $documento->id,
                'sigla' => $banco['banco']->sigla,
                'descricao' => $banco['banco']->descricao,
                'numero_conta' => $banco->numero_conta,
                'iban' => $banco->iban,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($request->input('meiosPagamento') as $meioPagamento) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documento->id,
                'descricao' => $meioPagamento['descricao'],
                'valor' => $meioPagamento['valor'],
            ]);
        }

        foreach ($quadroImposto as $value) {
            $value['incidencia'] = round($value['incidencia'], 2);
            $value['imposto'] = round($value['imposto'], 2);
            $value['liquido'] = round($value['liquido'], 2);

            ImpostoDocumento::create([
                'documento_id' => $documento->id,
                'taxa' => $value['taxa'],
                'codigo' => $value['codigo'],
                'isento' => $value['codigo'] === 'ISENTO' ? 1 : 0,
                'motivo_isencao' => $value['motivo_isencao'],
                'incidencia' => $value['incidencia'],
                'imposto' => $value['imposto'],
                //'liquido' => $value['liquido'],
                'total' => $value['incidencia'] + $value['imposto'],
            ]);
        }

        // Criação dos itens
        $itens = [];
        foreach ($request['itens'] as $item) {
            $idImpostoTaxa = $item['iva_percent'];
            $taxaIva = TipoTaxaIva::find($idImpostoTaxa)->taxa;
            $codigoIva = TipoTaxaIva::find($idImpostoTaxa)->codigo;
            //$motivoIsencaoCodigo = null;
            $motivoIsencaoDescricao = null;

            if ($codigoIva === 'ISENTO') {
                $motivo = DB::table('motivo_isencao')
                    ->where('id', $item['motivo_isencao_id']) // <-- aqui
                    ->first();
                if ($motivo) {
                    $codigoIva = $motivo->codigo;
                    $motivoIsencaoDescricao = $motivo->motivo;
                }
            }

            $desconto = 0;
            if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $item['preco_venda'] * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            // Calcula o total do item (sem IVA)
            $totalSemDesconto = $item['preco_venda'] * $item['quantidade'];
            $totalItem = $totalSemDesconto - $desconto;

            $itens[] = [
                'documento_id' => $documento->id,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_unitario' => $item['preco_venda'],
                'descricao' => $item['descricao'],
                'quantidade' => $item['quantidade'],
                'desconto_percent' => $item['desconto_percent'],
                'desconto_fixo' => $item['desconto_fixo'],
                'iva_percent' => $taxaIva ?? 0,
                'imposto_taxa_id' => $idImpostoTaxa,
                'codigo_iva' => $codigoIva ?? '',
                'motivo_isencao' => $motivoIsencaoDescricao,
                'total_sem_desconto' => $totalSemDesconto,
                'total' => $totalItem,
                // Adicione outros campos conforme necessário
            ];
        }

        $documento->itens()->createMany($itens);

        // Criar Recibo caso o tipo de vencimento for a prazo
        if ($request->has('is_apronto') && $request->input('is_apronto') === '1') {
            // return "hgfthft";
            // Se for um APRONTO, relaciona com o documento relacionado
            $request->merge([
                'tipo_nome' => 'Recibo',
                'tipo_sigla' => 'RG',
                'total_geral' => $totalFinal,
                'documento_relacionado_id' => $documento->id,
            ]);

            $data = [
                'tipo_fatura' => 'Recibo', // "RECIBO"
                'sigla_fatura' => "RG",
                'total_geral' => $totalFinal,
                'documento_relacionado_id' => $documento->id,
                'empresa_id' => $documento->empresa_id,
                'empresa_nome' => $documento->empresa_nome,
                'cliente_id' => $documento->cliente_id,
                'cliente_nome' => $documento->cliente_nome,
                'meiosPagamento' => $request->input('meiosPagamento'),
                'utilizador_id' => $request->input('utilizador_id'),
                'utilizador' => $request->input('utilizador'),
                'caixa' => $documento->caixa,
                'data_emissao' => $documento->data_emissao,
                'data_vencimento' => $documento->data_vencimento,
            ];

            $recibo = $this->storeRecibo(new Request($data));

            return response()->json([
                'message' => 'Factura e Recibo criados com sucesso.',
                'documento' => $documento->load('itens'),
                'documento_recibo' => $recibo->documento ?? '',
            ], 201);
        }

        return response()->json([
            'message' => 'Documento criado com sucesso.',
            'documento' => $documento->load('itens')
        ], 201);
    }

    public function storeNotaCredito(Request $request)
    {
        $numFatura = $this->gerarNumeroDocumento(
            'NC',
            $request->input('empresa_id')
        );


        // Validação dos dados recebidos
        $validated = Validator::make($request->all(), [
            // Dados do documento
            'tipo_fatura' => 'nullable|string',
            'sigla_fatura' => 'nullable|string',
            'tipo_cor' => 'nullable|string',

            'documento_id' => 'required|integer',

            'data_emissao' => 'required|date',

            'desconto_total' => 'nullable|numeric',
            'total_sem_desconto' => 'nullable|numeric',
            'total_impostos' => 'nullable|numeric',
            'total_geral' => 'nullable|numeric',

            'motivo_emissao' => 'required!string',

            'meiosPagamento' => 'required|array',
            'meiosPagamento.*.descricao' => 'required|string',
            'meiosPagamento.*.valor' => 'required|numeric',

            // Itens do documento
            'itens' => 'required|array|min:1',
            'itens.*.produto_nome' => 'required|string',
            'itens.*.codigo_produto' => 'required|string',
            'itens.*.preco_venda' => 'required|numeric',
            'itens.*.descricao' => 'nullable|string',
            'itens.*.quantidade' => 'required|integer',
            'itens.*.desconto_percent' => 'required|numeric',
            'itens.*.desconto_fixo' => 'required|numeric',
            'itens.*.imposto_taxa_id' => 'required|integer',
            'itens.*.iva_percent' => 'nullable|numeric',

            'utilizador_id' => 'required|integer',
            'utilizador' => 'required|string'
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


        // Construção do quadro por taxas (mantendo também o 'liquido' por grupo)
        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item['imposto_taxa_id']);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item['motivo_isencao_id'] ?? '';
            $motivo = '';

            if ($codigo === 'ISENTO' && $motivoIsencaoId) {
                $motivo = DB::table('motivo_isencao')->where('id', $motivoIsencaoId)->value('motivo');
            }

            $subtotalBruto = $item['preco_venda'] * $item['quantidade'];

            $desconto = 0;
            if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $subtotalBruto * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + ($taxaIva / 100)), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . '|' . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    'taxa' => $taxaIva,
                    'codigo' => $codigo,
                    'motivo_isencao' => $motivo,
                    'incidencia' => 0.0, // base
                    'imposto' => 0.0,
                    'liquido' => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]['incidencia'] += $base;
            $quadroImposto[$chave]['imposto'] += $imposto;
            $quadroImposto[$chave]['liquido'] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;
        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {

            $precoBruto = $item['preco_venda'] * $item['quantidade'];

            // Desconto do item
            $desconto = 0;
            if ($item['desconto_percent'] !== null && $item['desconto_percent'] > 0) {
                $desconto = $precoBruto * ($item['desconto_percent'] / 100);
            } elseif ($item['desconto_fixo'] !== null && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'] * $item['quantidade'];
            }

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request['desconto_tipo'] === 'percentual') {
            $descontoGeral = $totalSemDesconto * ($request['desconto_total'] / 100);
        } elseif ($request['desconto_tipo'] === 'fixo') {
            $descontoGeral = $request['desconto_total']; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha['liquido'] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha['liquido'] = round($linha['liquido'] - $descontoLinha, 2);

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha['incidencia'] = round($linha['liquido'] / (1 + ($linha['taxa'] / 100)), 2);
                $linha['imposto'] = round($linha['liquido'] - $linha['incidencia'], 2);

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, 'liquido'));
            $totalBase = array_sum(array_column($quadroImposto, 'incidencia'));
            $totalImposto = array_sum(array_column($quadroImposto, 'imposto'));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal = ($totalSemDesconto - $descontoItensTotal) - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, 'imposto'));


        $idDocumentoPai = $request['documento_id'];

        $dadosDocPai = Documento::where('id', $idDocumentoPai)->first();

        // Criação do documento
        $documento = Documento::create([
            'tipo_nome' => 'Nota de Crédito', // $request['tipo_fatura'],
            'tipo_sigla' => 'NC', // $request['sigla_fatura'],
            //'tipo_cor' => $request['tipo_cor'],

            'num_fatura' => $numFatura,
            'via' => 'original',

            'empresa_id' => $dadosDocPai->empresa_id,
            'empresa_nome' => $dadosDocPai->empresa_nome,
            'empresa_nif' => $dadosDocPai->empresa_nif,
            'empresa_telefone' => $dadosDocPai->empresa_telefone,
            'empresa_email' => $dadosDocPai->empresa_email,
            'empresa_endereco' => $dadosDocPai->empresa_endereco,

            'cliente_id' => $dadosDocPai->cliente_id,
            'cliente_nome' => $dadosDocPai->cliente_nome,
            'cliente_nif' => $dadosDocPai->cliente_nif,
            'cliente_telefone' => $dadosDocPai->cliente_telefone,
            'cliente_email' => $dadosDocPai->cliente_email,
            'cliente_endereco' => $dadosDocPai->cliente_endereco,

            'caixa' => $dadosDocPai->caixa,
            'data_emissao' => $dadosDocPai->data_emissao,
            'data_vencimento' => $dadosDocPai->data_vencimento,
            'forma_pagamento' => $dadosDocPai->forma_pagamento,
            'movimenta_stock' => $dadosDocPai->movimenta_stock,

            'taxa_iva' => '0',
            'valor_iva' => '0',
            //'retencao' => $retencao,

            'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',

            'desconto_total' => $descontoItensTotal + $descontoGeral,
            'valor_transporte' => $dadosDocPai->valor_transporte,
            'total_sem_desconto' => $totalSemDesconto,
            'total_impostos' => $totalImpostos,
            'total_geral' => $totalFinal,

            'utilizador_id' => $request['utilizador_id'],
            'utilizador' => $request['utilizador']
        ]);


        $bancos = Conta::with('banco')
            ->where('empresa_id', $request->input('empresa_id'))
            ->where('estado', true)
            ->get();

        foreach ($bancos as $banco) {
            DB::table('bancos_documento')->insert([
                'documento_id' => $documento->id,
                'sigla' => $banco['banco']->sigla,
                'descricao' => $banco['banco']->descricao,
                'numero_conta' => $banco->numero_conta,
                'iban' => $banco->iban,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($request->input('meiosPagamento') as $meioPagamento) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documento->id,
                'descricao' => $meioPagamento['descricao'],
                'valor' => $meioPagamento['valor'],
            ]);
        }

        foreach ($quadroImposto as $value) {
            $value['incidencia'] = round($value['incidencia'], 2);
            $value['imposto'] = round($value['imposto'], 2);
            $value['liquido'] = round($value['liquido'], 2);

            ImpostoDocumento::create([
                'documento_id' => $documento->id,
                'taxa' => $value['taxa'],
                'codigo' => $value['codigo'],
                'isento' => $value['codigo'] === 'ISENTO' ? 1 : 0,
                'motivo_isencao' => $value['motivo_isencao'],
                'incidencia' => $value['incidencia'],
                'imposto' => $value['imposto'],
                //'liquido' => $value['liquido'],
                'total' => $value['incidencia'] + $value['imposto'],
            ]);
        }

        // Criação dos itens
        $itens = [];
        foreach ($request['itens'] as $item) {
            $taxaIva = TipoTaxaIva::find($item['imposto_taxa_id'])->taxa;
            $codigoIva = TipoTaxaIva::find($item['imposto_taxa_id'])->codigo;
            //$motivoIsencaoCodigo = null;
            $motivoIsencaoDescricao = null;

            if ($codigoIva === 'ISENTO') {
                $motivo = DB::table('motivo_isencao')
                    ->where('id', $item['motivo_isencao_id']) // <-- aqui
                    ->first();
                if ($motivo) {
                    $codigoIva = $motivo->codigo;
                    $motivoIsencaoDescricao = $motivo->motivo;
                }
            }

            $desconto = 0;
            if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $item['preco_venda'] * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            // Calcula o total do item (sem IVA)
            $totalSemDesconto = $item['preco_venda'] * $item['quantidade'];
            $totalItem = $totalSemDesconto - $desconto;

            $itens[] = [
                'documento_id' => $documento->id,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_unitario' => $item['preco_venda'],
                'descricao' => $item['descricao'],
                'quantidade' => $item['quantidade'],
                'desconto_percent' => $item['desconto_percent'],
                'desconto_fixo' => $item['desconto_fixo'],
                'imposto_taxa_id' => $item['imposto_taxa_id'],
                'iva_percent' => $taxaIva ?? 0,
                'codigo_iva' => $codigoIva ?? '',
                'motivo_isencao' => $motivoIsencaoDescricao,
                'motivo_isencao_id' => $item['motivo_isencao_id'],
                'total_sem_desconto' => $totalSemDesconto,
                'total' => $totalItem,
            ];
        }

        $documento->itens()->createMany($itens);

        // Criar relação Nota de credito -> fatura
        DB::table('documento_relacoes')->insert([
            'documento_id' => $documento->id,
            'documento_relacionado_id' => $request['documento_id'],
            'tipo_relacao' => 'NOTA_DE_CREDITO_FATURA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Criar Recibo
        // Se for um APRONTO, relaciona com o documento relacionado
        $request->merge([
            'tipo_nome' => 'Recibo',
            'tipo_sigla' => 'RC',
            'total_geral' => $totalFinal,
            'documento_relacionado_id' => $documento->id,
        ]);

        $data = [
            'tipo_fatura' => 'Recibo', // "RECIBO"
            'sigla_fatura' => "RC",
            'total_geral' => $totalFinal,
            'documento_relacionado_id' => $documento->id,
            'empresa_id' => $documento->empresa_id,
            'empresa_nome' => $documento->empresa_nome,
            'empresa_nif' => $documento->empresa_nif,
            'cliente_id' => $documento->cliente_id,
            'cliente_nome' => $documento->cliente_nome,
            'cliente_nif' => $documento->cliente_nif,
            'meiosPagamento' => $request->input('meiosPagamento'),
            'utilizador_id' => $request->input('utilizador_id'),
            'utilizador' => $request->input('utilizador'),
            'caixa' => $documento->caixa,
            'data_emissao' => $documento->data_emissao,
            'data_vencimento' => $documento->data_vencimento,
        ];

        $recibo = $this->storeRecibo(new Request($data), 'RECIBO_NOTA_DE_CREDITO');

        return response()->json([
            'message' => 'Nota de Crédito e Recibo criados com sucesso.',
            'documento' => $documento->load('itens'),
            'documento_recibo' => $recibo->documento ?? '',
        ], 201);


        return response()->json([
            'message' => 'Documento criado com sucesso.',
            'documento' => $documento->load('itens')
        ], 201);
    }

    public function storeRecibo(Request $request, $tipoRelacao = 'RECIBO_FATURA')
    {
        //dd($request->all());
        $validated = Validator::make($request->all(), [
            'tipo_fatura' => 'required|string', // "RECIBO"
            'sigla_fatura' => 'required|string', // "RC"
            'data_emissao' => 'required|date',
            'total_geral' => 'required|numeric',
            'meiosPagamento' => 'required|array|min:1',
            'meiosPagamento.*.descricao' => 'required|string',
            'meiosPagamento.*.valor' => 'required|numeric',
            'documento_relacionado_id' => 'required|integer|exists:documentos,id', // fatura associada
            'utilizador_id' => 'required|integer',
            'utilizador' => 'required|string'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação.',
                'errors' => $validated->errors(),
            ], 422);
        }

        // Gerar número do recibo
        $numRecibo = $this->gerarNumeroDocumento(
            $request->sigla_fatura,
            $request->empresa_id
        );

        $totalEntregue = 0;

        foreach ($request->input('meiosPagamento') as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento['valor'];
        }

        $troco = $totalEntregue - $request->total_geral;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }

        // Criar recibo
        $documento = Documento::create([
            'tipo_nome' => $request->tipo_fatura,
            'tipo_sigla' => $request->sigla_fatura,
            'num_fatura' => $numRecibo,
            'via' => 'Original',
            'empresa_id' => $request->empresa_id,
            'empresa_nome' => $request->empresa_nome,
            'empresa_nif' => $request->empresa_nif,
            'cliente_id' => $request->cliente_id,
            'cliente_nome' => $request->cliente_nome,
            'cliente_nif' => $request->cliente_nif,
            'caixa' => $request->caixa ?? 'CAIXA PRINCIPAL',
            'data_emissao' => $request->data_emissao,
            'movimenta_stock' => false,
            'total_geral' => $request->total_geral,
            'troco' => $troco,
            'hash' => 'rfsuhihuhuycgygyfyukgeyggfavdyvd',
            'utilizador_id' => $request->utilizador_id,
            'utilizador' => $request->utilizador
        ]);

        // Criar relação recibo
        DB::table('documento_relacoes')->insert([
            'documento_id' => $documento->id,
            'documento_relacionado_id' => $request->documento_relacionado_id,
            'tipo_relacao' => $tipoRelacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Meios de pagamento
        foreach ($request->meiosPagamento as $meio) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documento->id,
                'descricao' => $meio['descricao'],
                'valor' => $meio['valor'],
            ]);
        }

        return response()->json([
            'message' => 'Recibo criado com sucesso.',
            'documento' => $documento,
        ], 201);
    }

    public function storeFaturaCompra(Request $request)
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

            'cliente_id' => 'nullable|integer',
            'cliente_nome' => 'required|string',
            'cliente_nif' => 'required|string',
            'cliente_telefone' => 'nullable|string',
            'cliente_email' => 'nullable|email',
            'cliente_endereco' => 'nullable|string',

            'data_emissao' => 'required|date',
            'data_vencimento' => 'required|date',

            'taxa_iva' => 'nullable|numeric',
            'valor_iva' => 'nullable|numeric',

            'desconto_total' => 'nullable|numeric',
            'valor_transporte' => 'nullable|numeric',
            'total_sem_desconto' => 'nullable|numeric',
            'total_impostos' => 'nullable|numeric',
            'total_geral' => 'nullable|numeric',

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

        // Construção do quadro por taxas (mantendo também o 'liquido' por grupo)
        /*      $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            //$tipo = TipoTaxaIva::find($item['iva_percent']);
            $taxaIva = $item['iva_percent'];
            // $codigo = $tipo->codigo;
            // $motivoIsencaoId = $item['motivo_isencao_id'] ?? '';
            // $motivo = '';

            // if ($codigo === 'ISENTO' && $motivoIsencaoId) {
            //     $motivo = DB::table('motivo_isencao')->where('id', $motivoIsencaoId)->value('motivo');
            // }

            $subtotalBruto = $item['preco_custo'] * $item['quantidade'];

            $desconto = 0;
            if (isset($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $subtotalBruto * ($item['desconto_percent'] / 100);
            } elseif (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + ($taxaIva / 100)), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    'taxa' => $taxaIva,
                    'incidencia' => 0.0, // base
                    'imposto' => 0.0,
                    'liquido' => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]['incidencia'] += $base;
            $quadroImposto[$chave]['imposto'] += $imposto;
            $quadroImposto[$chave]['liquido'] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;

        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {

            $precoBruto = $item['preco_custo'] * $item['quantidade'];

            // Desconto do item
            $desconto = 0;
            if ($item['desconto_percent'] !== null && $item['desconto_percent'] > 0) {
                $desconto = $precoBruto * ($item['desconto_percent'] / 100);
            } elseif ($item['desconto_fixo'] !== null && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'] * $item['quantidade'];
            }

            $subtotalComIva = $precoBruto - $desconto;

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request['desconto_tipo'] === 'percentual') {
            $descontoGeral = $totalSemDesconto * ($request['desconto_total'] / 100);
        } elseif ($request['desconto_tipo'] === 'fixo') {
            $descontoGeral = $request['desconto_total']; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha['liquido'] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha['liquido'] = round($linha['liquido'] - $descontoLinha, 2);

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha['incidencia'] = round($linha['liquido'] / (1 + ($linha['taxa'] / 100)), 2);
                $linha['imposto'] = round($linha['liquido'] - $linha['incidencia'], 2);

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, 'liquido'));
            $totalBase = array_sum(array_column($quadroImposto, 'incidencia'));
            $totalImposto = array_sum(array_column($quadroImposto, 'imposto'));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal = ($totalSemDesconto - $descontoItensTotal) - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, 'imposto'));

        $retencao = 0;
        $troco = 0;
*/

        // === QUADRO DE IMPOSTOS (corrigido) ===
        // Resultado: cada linha tem { taxa, incidencia, iva, total } igual ao quadro da fatura

        $quadro = []; // [ '14.00' => ['taxa'=>'14.00', 'incidencia'=>..., 'iva'=>..., 'total'=>...] ]
        $totalBaseAntesDescontoGeral = 0.0; // soma das bases (sem IVA) após descontos por item
        $totalIvaAntesDescontoGeral  = 0.0;
        $descontoItensTotal = 0;
        $descontoGeral = 0;
        $totalSemDesconto = 0;
        // $totalImpostos = 0;


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
        $documento = Documento::create([
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

            'cliente_id' => $request['cliente_id'] ?? null,
            'cliente_nome' => $request['cliente_nome'],
            'cliente_nif' => $request['cliente_nif'],
            'cliente_telefone' => $request['cliente_telefone'],
            'cliente_email' => $request['cliente_email'],
            'cliente_endereco' => $request['cliente_endereco'],

            'data_emissao' => $request['data_emissao'],
            'data_vencimento' => $request['data_vencimento'],

            'taxa_iva' => '0',
            'valor_iva' => '0',
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
            'paga' => $request['paga']
        ]);


        foreach ($quadroImpostos as $value) {
            $value['incidencia'] = round($value['incidencia'], 2);
            $value['iva'] = round($value['iva'], 2);
            //$value['liquido'] = round($value['liquido'], 2);

            ImpostoDocumento::create([
                'documento_id' => $documento->id,
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

            //$motivoIsencaoCodigo = null;
            $motivoIsencaoDescricao = null;

            // if ($codigoIva === 'ISENTO') {
            //     $motivo = DB::table('motivo_isencao')
            //         ->where('id', $item['motivo_isencao_id']) // <-- aqui
            //         ->first();
            //     if ($motivo) {
            //         $codigoIva = $motivo->codigo;
            //         $motivoIsencaoDescricao = $motivo->motivo;
            //     }
            // }

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
                'documento_id' => $documento->id,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_unitario' => $item['preco_custo'],
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

    public function gerarNumeroDocumento(string $tipoSigla, string $empresId): string
    {
        $ano = Carbon::now()->year;

        $empresa = DB::table('empresas')->find($empresId);

        // Conta quantos documentos desse tipo e ano já existem
        $contador = DB::table('documentos')
            ->where('tipo_sigla', $tipoSigla) // campo tipo como 'FR', por exemplo
            ->where('empresa_id', $empresId) // campo empresa_id
            ->whereYear('created_at', $ano)
            ->count();

        $sequencial = $contador + 1;

        // Formato final: FR T11P2025/2
        return "{$tipoSigla} {$empresa->indicativo_fatura}{$ano}/{$sequencial}";
    }

    public function gerarPdf(string $id)
    {

        $documento = Documento::with([
            'documentosRelacionados',
            'relacionadoEm',
            'itens'
        ])->find($id);

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        $bancos = BancoDocumento::where('documento_id', $id)->get();

        $meiosPagamento = MeioPagamentoDocumento::where('documento_id', $id)->get();

        $quadroImposto = ImpostoDocumento::where('documento_id', $id)
            ->get();

        $quadroImpostoAgrupado = [];

        foreach ($quadroImposto as $linha) {
            $taxa = $linha['taxa'];
            if (!isset($quadroImpostoAgrupado[$taxa])) {
                $quadroImpostoAgrupado[$taxa] = [
                    'taxa' => $taxa,
                    'codigo' => $linha['codigo'],
                    'incidencia' => 0,
                    'imposto' => 0,
                    'motivos' => [],
                ];
            }

            $quadroImpostoAgrupado[$taxa]['incidencia'] += $linha['incidencia'];
            $quadroImpostoAgrupado[$taxa]['imposto'] += $linha['imposto'];

            if (
                !empty($linha['motivo_isencao']) &&
                !in_array($linha['motivo_isencao'], $quadroImpostoAgrupado[$taxa]['motivos'])
            ) {
                $quadroImpostoAgrupado[$taxa]['motivos'][] = $linha['motivo_isencao'];
            }
        }

        // Depois pode juntar motivos numa string
        foreach ($quadroImpostoAgrupado as &$linha) {
            $linha['motivos'] = implode('; ', $linha['motivos']);
        }
        unset($linha);

        usort($quadroImpostoAgrupado, function ($a, $b) {
            //return (float)$a['taxa'] <=> (float)$b['taxa']; // crescente
            return (float)$b['taxa'] <=> (float)$a['taxa']; // decrescente
        });


        //  return $quadroImpostoAgrupado;

        $pdf = Pdf::loadView('pdf.documento', compact(['documento', 'quadroImpostoAgrupado', 'bancos', 'meiosPagamento']))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 96,
                'debugPng' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
            ]);

        $pdf->getDomPDF()->get_canvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text1 = "Conteúdo do rodapé";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font('Helvetica', 'normal');
            $size = 10;

            // Posição inicial à esquerda, com margem de 40px
            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12; // 12px abaixo do texto1

            // Desenha uma linha horizontal acima do rodapé
            $lineY = $y1 - 5; // 5px acima do primeiro texto
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0], // cor
                1          // espessura
            );


            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = str_replace([' ', '/'], '_', $documento['num_fatura']);

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->stream();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'Inline; filename=' . $filename . '.pdf',
        ]);
    }

    public function gerarPdfFaturaCompra(string $id)
    {
        $documento = Documento::with([
            'documentosRelacionados',
            'relacionadoEm',
            'itens'
        ])->find($id);

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        $bancos = BancoDocumento::where('documento_id', $id)->get();

        $meiosPagamento = MeioPagamentoDocumento::where('documento_id', $id)->get();

        $quadroImposto = ImpostoDocumento::where('documento_id', $id)
            ->get();

        $quadroImpostoAgrupado = [];

        foreach ($quadroImposto as $linha) {
            $taxa = $linha['taxa'];
            if (!isset($quadroImpostoAgrupado[$taxa])) {
                $quadroImpostoAgrupado[$taxa] = [
                    'taxa' => $taxa,
                    'codigo' => $linha['codigo'],
                    'incidencia' => 0,
                    'imposto' => 0,
                    'motivos' => [],
                ];
            }

            $quadroImpostoAgrupado[$taxa]['incidencia'] += $linha['incidencia'];
            $quadroImpostoAgrupado[$taxa]['imposto'] += $linha['imposto'];

            if (
                !empty($linha['motivo_isencao']) &&
                !in_array($linha['motivo_isencao'], $quadroImpostoAgrupado[$taxa]['motivos'])
            ) {
                $quadroImpostoAgrupado[$taxa]['motivos'][] = $linha['motivo_isencao'];
            }
        }

        // Depois pode juntar motivos numa string
        foreach ($quadroImpostoAgrupado as &$linha) {
            $linha['motivos'] = implode('; ', $linha['motivos']);
        }
        unset($linha);

        usort($quadroImpostoAgrupado, function ($a, $b) {
            //return (float)$a['taxa'] <=> (float)$b['taxa']; // crescente
            return (float)$b['taxa'] <=> (float)$a['taxa']; // decrescente
        });


        //  return $quadroImpostoAgrupado;

        $pdf = Pdf::loadView('pdf.documento-compra', compact(['documento', 'quadroImpostoAgrupado', 'bancos', 'meiosPagamento']))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 96,
                'debugPng' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
            ]);

        $pdf->getDomPDF()->get_canvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text1 = "Conteúdo do rodapé";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font('Helvetica', 'normal');
            $size = 10;

            // Posição inicial à esquerda, com margem de 40px
            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12; // 12px abaixo do texto1

            // Desenha uma linha horizontal acima do rodapé
            $lineY = $y1 - 5; // 5px acima do primeiro texto
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0], // cor
                1          // espessura
            );


            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = str_replace([' ', '/'], '_', $documento['num_fatura']);

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->stream();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'Inline; filename=' . $filename . '.pdf',
        ]);
    }

    public function gerarPdfRecibo(string $id)
    {
        $documento = Documento::with([
            'documentosRelacionados',
            'relacionadoEm',
        ])->find($id);

        $docRelacionado = $documento->relacionadoEm->first();

        $pagamentos = MeioPagamentoDocumento::where('documento_id', $id)->first();

        $valorPago = $pagamentos->valor;

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(['message' => 'Documento de recibo não encontrado.'], 404);
        }

        $bancos = BancoDocumento::where('documento_id', $id)->get();

        $meiosPagamento = MeioPagamentoDocumento::where('documento_id', $id)->get();

        $pdf = Pdf::loadView('pdf.recibo', compact(['documento', 'docRelacionado', 'bancos', 'meiosPagamento', 'valorPago']))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'isPhpEnabled' => true,
                'dpi' => 96,
                'debugPng' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
            ]);

        $pdf->getDomPDF()->get_canvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text1 = "Conteúdo do rodapé";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font('Helvetica', 'normal');
            $size = 10;

            // Posição inicial à esquerda, com margem de 40px
            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12; // 12px abaixo do texto1

            // Desenha uma linha horizontal acima do rodapé
            $lineY = $y1 - 5; // 5px acima do primeiro texto
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0], // cor
                1          // espessura
            );


            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->stream();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'Inline; filename=fatura-recibo.pdf',
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        // Verifica se o documento existe
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        // Return a list of all Caixa records
        $doc = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento', 'documentosRelacionados', 'relacionadoEm'])
            ->where('id', $id)
            ->first();

        return response()->json($doc);
    }

    public function anularDocumento(string $id)
    {
        $doc = Documento::findOrFail($id);

        // regra: só pode anular se ainda não tiver nota de crédito associada
        if ($doc->estado !== 'emitido') {
            return response()->json(['erro' => 'Não pode anular este documento.'], 400);
        }

        $doc->estado = 'anulado';
        $doc->save();

        return response()->json(['sucesso' => 'Documento anulado com sucesso.']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
