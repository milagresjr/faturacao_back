<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaDebitoService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
        private CalculoImpostoService $impostoService,
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

        if ($faturaOriginal->estado === 'cancelada') {
            return response()->json([
                'message' => 'Não é possível criar nota de débito para uma fatura cancelada.',
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
            'tipo_nome' => 'Nota de Débito',
            'tipo_sigla' => 'ND',
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
            'tipo_relacao' => 'NOTA_DE_DEBITO_FATURA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nota de Débito criada com sucesso.',
            'documento' => $documento->load('itens'),
        ], 201);
    }
}
