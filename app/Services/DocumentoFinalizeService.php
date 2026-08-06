<?php

namespace App\Services;

use App\Models\Conta;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use Illuminate\Support\Facades\DB;

class DocumentoFinalizeService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
        private AgtHashService $hashService,
        private CalculoImpostoService $impostoService,
        private DocumentoService $documentoService,
        private ReciboService $reciboService,
    ) {}

    public function finalizar(array $dados, int $id)
    {
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(['message' => 'Document not found!'], 404);
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

        $retencao = $this->impostoService->calcularRetencao($dados['itens']);

        $totalEntregueCalc = 0;
        foreach (($dados['meiosPagamento'] ?? []) as $mp) {
            $totalEntregueCalc += (float) $mp['valor'];
        }

        $troco = max($totalEntregueCalc - $totais['total_final'], 0);

        $estadoDoc = $dados['estado_documento'] ?? 'emitido';
        $numFatura = $estadoDoc === 'rascunho'
            ? ''
            : $this->numeroService->gerarNumeroDocumento($dados['serie_id']);

        try {
            DB::beginTransaction();

            $infoGuiaId = null;
            if (in_array($dados['sigla_fatura'] ?? '', ['GT', 'GR'])) {
                if ($documento->info_guia_id) {
                    DB::table('info_guias')
                        ->where('id', $documento->info_guia_id)
                        ->update([
                            'marca' => $dados['marca'] ?? null,
                            'matricula' => $dados['matricula'] ?? null,
                            'local_origem' => $dados['local_origem'] ?? null,
                            'local_destino' => $dados['local_destino'] ?? null,
                            'data_origem' => $dados['data_origem'] ?? null,
                            'data_destino' => $dados['data_destino'] ?? null,
                            'updated_at' => now(),
                        ]);
                    $infoGuiaId = $documento->info_guia_id;
                } else {
                    $infoGuiaId = DB::table('info_guias')->insertGetId([
                        'marca' => $dados['marca'] ?? null,
                        'matricula' => $dados['matricula'] ?? null,
                        'local_origem' => $dados['local_origem'] ?? null,
                        'local_destino' => $dados['local_destino'] ?? null,
                        'data_origem' => $dados['data_origem'] ?? null,
                        'data_destino' => $dados['data_destino'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $estadoPagamento = $this->impostoService->determinarEstadoPagamento(
                $totais['total_final'],
                $totalEntregueCalc
            );

            $documento->update([
                'tipo_nome' => $dados['tipo_fatura'],
                'tipo_sigla' => $dados['sigla_fatura'],
                'estado_documento' => $dados['estado_documento'] ?? 'emitido',
                'estado_pagamento' => $estadoPagamento,
                'estado_vencimento' => $dados['estado_vencimento'] ?? 'no_prazo',
                'num_fatura' => $numFatura,
                'via' => 'original',
                'empresa_id' => $dados['empresa_id'],
                'empresa_nome' => $dados['empresa_nome'],
                'empresa_nif' => $dados['empresa_nif'],
                'empresa_telefone' => $dados['empresa_telefone'] ?? null,
                'empresa_email' => $dados['empresa_email'] ?? null,
                'empresa_endereco' => $dados['empresa_endereco'] ?? null,
                'cliente_id' => $dados['cliente_id'] ?? null,
                'cliente_nome' => $dados['cliente_nome'],
                'cliente_nif' => $dados['cliente_nif'],
                'cliente_telefone' => $dados['cliente_telefone'] ?? null,
                'cliente_email' => $dados['cliente_email'] ?? null,
                'cliente_endereco' => $dados['cliente_endereco'] ?? null,
                'caixa' => $dados['caixa'],
                'data_emissao' => $dados['data_emissao'],
                'data_vencimento' => $dados['data_vencimento'],
                'forma_pagamento' => $dados['forma_pagamento'] ?? null,
                'movimenta_stock' => $dados['movimenta_stock'],
                'taxa_iva' => '0',
                'valor_iva' => '0',
                'retencao' => $retencao,
                'estado' => 'emitido',
                'hash' => 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd',
                'desconto_tipo' => $dados['desconto_tipo'] ?? '',
                'desconto_total' => $totais['desconto_itens_total'] + $totais['desconto_geral'],
                'valor_transporte' => $dados['valor_transporte'] ?? 0,
                'total_sem_desconto' => $totais['total_sem_desconto'],
                'total_impostos' => $totais['total_impostos'],
                'total_geral' => $totais['total_final'],
                'troco' => $troco,
                'utilizador_id' => $dados['utilizador_id'],
                'utilizador' => $dados['utilizador'],
                'info_guia_id' => $infoGuiaId,
            ]);

            DB::table('bancos_documento')->where('documento_id', $documento->id)->delete();
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

            $documento->meiosPagamento()->delete();
            foreach (($dados['meiosPagamento'] ?? []) as $meio) {
                MeioPagamentoDocumento::create([
                    'documento_id' => $documento->id,
                    'descricao' => $meio['descricao'],
                    'valor' => $meio['valor'],
                ]);
            }

            $documento->impostosDocumento()->delete();
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

            $documento->itens()->delete();
            $itens = $this->impostoService->montarItensParaInsercao($dados['itens'], $documento->id);
            $documento->itens()->createMany($itens);

            DB::commit();
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Erro ao criar o documento.',
                'error' => $th->getMessage(),
            ], 500);
        }

        if (!empty($dados['is_apronto']) && $dados['is_apronto'] === '1') {
            $dataRecibo = [
                'tipo_fatura' => 'Recibo',
                'sigla_fatura' => 'RC',
                'total_geral' => $totais['total_final'],
                'documento_relacionado_id' => $documento->id,
                'empresa_id' => $documento->empresa_id,
                'empresa_nome' => $documento->empresa_nome,
                'cliente_id' => $documento->cliente_id,
                'cliente_nome' => $documento->cliente_nome,
                'meiosPagamento' => $dados['meiosPagamento'],
                'utilizador_id' => $dados['utilizador_id'],
                'utilizador' => $dados['utilizador'],
                'caixa' => $documento->caixa,
                'data_emissao' => $documento->data_emissao,
                'data_vencimento' => $documento->data_vencimento,
            ];

            $recibo = $this->reciboService->criar($dataRecibo);

            return response()->json([
                'message' => 'Factura e Recibo criados com sucesso.',
                'documento' => $documento->load('itens'),
                'documento_recibo' => $recibo->original['documento'] ?? '',
            ], 201);
        }

        return response()->json([
            'message' => 'Documento criado com sucesso.',
            'documento' => $documento->load('itens'),
        ], 201);
    }

    public function destruirRascunho(int $id)
    {
        $documento = Documento::where('estado_documento', 'rascunho')->find($id);
        if (!$documento) {
            return response()->json(['message' => 'Document not found!'], 404);
        }

        $documento->delete();

        return response()->json([
            'message' => 'Documento excluido com sucesso!',
        ]);
    }

    public function anularDocumento(int $id)
    {
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        if ($documento->estado_documento === 'rascunho' || $documento->estado === 'rascunho') {
            return response()->json([
                'message' => 'Não é possível anular um documento em rascunho.',
            ], 400);
        }

        $documento->update([
            'estado_documento' => 'anulado',
            'estado' => 'cancelada',
        ]);

        return response()->json([
            'message' => 'Documento anulado com sucesso.',
            'documento' => $documento,
        ]);
    }
}
