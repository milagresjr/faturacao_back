<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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
            'forma_pagamento' => 'required|string',
            'movimenta_stock' => 'required|boolean',

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
            'itens.*.codigo_produto' => 'required|string',
            'itens.*.preco_venda' => 'required|numeric',
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

        $totalSemDesconto = 0;
        $descontoTotal = 0;
        $totalImpostos = 0;

        foreach ($request->itens as $item) {
            $precoBruto = $item['preco_venda'] * $item['quantidade'];
            // Verifica se o desconto é fixo (> 0) ou percentual
            if (isset($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            } else {
                $desconto = ($item['desconto_percent'] / 100) * $precoBruto;
            }
            $subtotal = $precoBruto - $desconto;
            if($item['iva_percent'] == 'auto'){
                $item['iva_percent'] = 14; // Definindo IVA automático como 14%
            } elseif (!is_numeric($item['iva_percent'])) {
                $item['iva_percent'] = 0; // Se não for numérico, assume-se isento
            }
            $iva = ($item['iva_percent'] / 100) * $subtotal;

            $totalSemDesconto += $subtotal;
            $descontoTotal += $desconto;
            $totalImpostos += $iva;
        }

        $totalGeral = $totalSemDesconto + $totalImpostos;

        // Criação do documento
        $documento = Documento::create([
            'tipo_nome' => $request['tipo_fatura'],
            'tipo_sigla' => $request['sigla_fatura'],
            //'tipo_cor' => $request['tipo_cor'],

            'num_fatura' => 'FR BX2025/22',
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

            'taxa_iva' => '14',
            'valor_iva' => '0',
            'retencao' => '0',

            'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',

            'desconto_total' => $descontoTotal,
            'valor_transporte' => $request['valor_transporte'],
            'total_sem_desconto' => $totalSemDesconto,
            'total_impostos' => $totalImpostos,
            'total_geral' => $totalGeral,

            'utilizador_id' => $request['utilizador_id'],
            'utilizador' => $request['utilizador']
        ]);

        // Criação dos itens
        $itens = [];
        foreach ($request['itens'] as $item) {
            $itens[] = [
                'documento_id' => $documento->id,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_unitario' => $item['preco_venda'],
                'quantidade' => $item['quantidade'],
                'desconto_percent' => $item['desconto_percent'],
                'desconto_fixo' => $item['desconto_fixo'],
                'iva_percent' => $item['iva_percent'] ?? 0,
                'total' => ($item['preco_venda'] * $item['quantidade']),
                // Adicione outros campos conforme necessário
            ];
        }

        $documento->itens()->createMany($itens);

        return response()->json([
            'message' => 'Documento criado com sucesso.',
            'documento' => $documento->load('itens')
        ], 201);
    }

    public function gerarPdf(string $id)
    {

        $documento = Documento::with('itens')->find($id);

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        $quadroImposto = [];

        foreach ($documento->itens as $item) {
            $taxa = $item->iva_percent ?? 0;

            $subtotal = $item->preco_unitario * $item->quantidade;

            if (!isset($quadroImposto[$taxa])) {
                $quadroImposto[$taxa] = [
                    'incidencia' => 0,
                    'imposto' => 0,
                ];
            }

            $quadroImposto[$taxa]['incidencia'] += $subtotal;
            $quadroImposto[$taxa]['imposto'] += ($taxa / 100) * $subtotal;
        }

        $pdf = Pdf::loadView('pdf.documento', compact(['documento','quadroImposto']))
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
            $y1 = $canvas->get_height() - 70;
            $y2 = $y1 + 12; // 12px abaixo do texto1

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
        //
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
