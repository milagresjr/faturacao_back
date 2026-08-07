<?php

namespace App\Services;

use App\Models\Conta;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use App\Models\TipoTaxaIva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaCreditoService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
        private CalculoImpostoService $impostoService,
        private ReciboService $reciboService,
    ) {}

    public function criar(array $dados)
    {
        $faturaOriginal = Documento::find($dados['documento_id']);
        if (!$faturaOriginal) {
            return response()->json([
                'message' => 'Fatura não encontrada.',
                'error' => 'INVOICE_NOT_FOUND'
            ], 404);
        }

        $notaCreditoExistente = DB::table('documento_relacoes')
            ->where('documento_relacionado_id', $dados['documento_id'])
            ->where('tipo_relacao', 'NOTA_DE_CREDITO_FATURA')
            ->exists();

        if ($notaCreditoExistente) {
            return response()->json([
                'message' => 'Não é permitido criar múltiplas notas de crédito para a mesma fatura.',
                'error' => 'DUPLICATE_CREDIT_NOTE',
            ], 422);
        }

        if ($faturaOriginal->estado === 'cancelada') {
            return response()->json([
                'message' => 'Não é possível criar nota de crédito para uma fatura cancelada.',
                'error' => 'CANCELLED_INVOICE'
            ], 422);
        }

        $empresa = Empresa::find($dados['empresa_id']);
        if (!$empresa || !$empresa->nif) {
            return response()->json([
                'message' => 'A empresa não tem NIF cadastrado.',
                'error' => 'MISSING_NIF'
            ], 422);
        }

        $totalCreditado = DB::table('documento_relacoes')
            ->join('documentos', 'documento_relacoes.documento_id', '=', 'documentos.id')
            ->where('documento_relacoes.documento_relacionado_id', $dados['documento_id'])
            ->where('documento_relacoes.tipo_relacao', 'NOTA_DE_CREDITO_FATURA')
            ->where('documentos.estado', 'emitido')
            ->sum('documentos.total_geral');

        if ($totalCreditado >= $faturaOriginal->total_geral) {
            return response()->json([
                'message' => 'Esta fatura já foi totalmente creditada.',
                'error' => 'FULLY_CREDITED_INVOICE',
            ], 422);
        }

        $quadroResult = $this->impostoService->calcularQuadroImposto($dados['itens']);
        $quadroImposto = $quadroResult['quadro'];
        $totalLiquido = $quadroResult['total_liquido'];

        $totais = $this->impostoService->calcularTotais(
            $dados['itens'],
            $dados['desconto_tipo'] ?? null,
            $dados['desconto_total'] ?? null,
            $quadroImposto,
            $totalLiquido
        );

        $numFatura = $this->numeroService->gerarNumeroDocumento($dados['serie_id']);

        $documento = Documento::create([
            'tipo_nome' => 'Nota de Crédito',
            'tipo_sigla' => 'NC',
            'template' => $faturaOriginal->template,
            'num_fatura' => $numFatura,
            'via' => 'original',
            'serie_id' => $dados['serie_id'],
            'empresa_id' => $faturaOriginal->empresa_id,
            'empresa_nome' => $faturaOriginal->empresa_nome,
            'empresa_nif' => $faturaOriginal->empresa_nif,
            'empresa_telefone' => $faturaOriginal->empresa_telefone,
            'empresa_email' => $faturaOriginal->empresa_email,
            'empresa_endereco' => $faturaOriginal->empresa_endereco,
            'empresa_logo' => $faturaOriginal->empresa_logo,
            'cliente_id' => $faturaOriginal->cliente_id,
            'cliente_nome' => $faturaOriginal->cliente_nome,
            'cliente_nif' => $faturaOriginal->cliente_nif,
            'cliente_telefone' => $faturaOriginal->cliente_telefone,
            'cliente_email' => $faturaOriginal->cliente_email,
            'cliente_endereco' => $faturaOriginal->cliente_endereco,
            'caixa' => $faturaOriginal->caixa,
            'data_emissao' => $dados['data_emissao'],
            'data_vencimento' => $faturaOriginal->data_vencimento,
            'forma_pagamento' => $faturaOriginal->forma_pagamento,
            'movimenta_stock' => $faturaOriginal->movimenta_stock,
            'taxa_iva' => '0',
            'valor_iva' => '0',
            'estado' => 'emitido',
            'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',
            'desconto_total' => $totais['desconto_itens_total'] + $totais['desconto_geral'],
            'valor_transporte' => $faturaOriginal->valor_transporte,
            'total_sem_desconto' => $totais['total_sem_desconto'],
            'total_impostos' => $totais['total_impostos'],
            'total_geral' => $totais['total_final'],
            'utilizador_id' => $dados['utilizador_id'],
            'utilizador' => $dados['utilizador'],
        ]);

        $bancos = Conta::with('banco')
            ->where('empresa_id', $dados['empresa_id'])
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

        foreach (($dados['meiosPagamento'] ?? []) as $meio) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documento->id,
                'descricao' => $meio['descricao'],
                'valor' => $meio['valor'],
            ]);
        }

        foreach ($quadroImposto as $value) {
            ImpostoDocumento::create([
                'documento_id' => $documento->id,
                'taxa' => round($value['taxa'], 2),
                'codigo' => $value['codigo'],
                'isento' => $value['codigo'] === 'ISENTO' ? 1 : 0,
                'motivo_isencao' => $value['motivo_isencao'],
                'incidencia' => round($value['incidencia'], 2),
                'imposto' => round($value['imposto'], 2),
                'total' => round($value['incidencia'] + $value['imposto'], 2),
            ]);
        }

        $itens = $this->impostoService->montarItensParaInsercao($dados['itens'], $documento->id);
        $documento->itens()->createMany($itens);

        DB::table('documento_relacoes')->insert([
            'documento_id' => $documento->id,
            'documento_relacionado_id' => $dados['documento_id'],
            'tipo_relacao' => 'NOTA_DE_CREDITO_FATURA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dataRecibo = [
            'tipo_fatura' => 'Recibo',
            'sigla_fatura' => 'RC',
            'total_geral' => $totais['total_final'],
            'documento_relacionado_id' => $documento->id,
            'empresa_id' => $documento->empresa_id,
            'empresa_nome' => $documento->empresa_nome,
            'empresa_nif' => $documento->empresa_nif,
            'cliente_id' => $documento->cliente_id,
            'cliente_nome' => $documento->cliente_nome,
            'cliente_nif' => $documento->cliente_nif,
            'meiosPagamento' => $dados['meiosPagamento'],
            'utilizador_id' => $dados['utilizador_id'],
            'utilizador' => $dados['utilizador'],
            'caixa' => $documento->caixa,
            'data_emissao' => $documento->data_emissao,
            'data_vencimento' => $documento->data_vencimento,
        ];

        $recibo = $this->reciboService->criar($dataRecibo, 'RECIBO_NOTA_DE_CREDITO');

        return response()->json([
            'message' => 'Nota de Crédito e Recibo criados com sucesso.',
            'documento' => $documento->load('itens'),
            'documento_recibo' => $recibo->original['documento'] ?? '',
        ], 201);
    }
}
