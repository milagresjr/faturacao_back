<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use Illuminate\Support\Facades\DB;

class DocumentoTransformService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
        private AgtHashService $hashService,
        private CalculoImpostoService $impostoService,
    ) {}

    public function transformar(array $dados, int $id)
    {
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
        $troco = $this->impostoService->calcularTroco($dados['meiosPagamento'] ?? [], $totais['total_final']);

        $totalEntregueCalc = 0;
        foreach (($dados['meiosPagamento'] ?? []) as $mp) {
            $totalEntregueCalc += (float) $mp['valor'];
        }

        try {
            DB::beginTransaction();

            $documentoOrigem = Documento::with(['itens', 'impostosDocumento', 'meiosPagamento'])->findOrFail($id);

            if ($documentoOrigem->estado === 'transformado') {
                return response()->json([
                    'message' => 'Este documento já foi transformado.',
                ], 400);
            }

            $infoGuiaId = null;
            if (in_array($dados['tipo_sigla'] ?? '', ['GT', 'GR'])) {
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

            $estadoPagamento = $this->impostoService->determinarEstadoPagamento($totais['total_final'], $totalEntregueCalc);

            $numFatura = $this->numeroService->gerarNumeroDocumento($dados['serie_id']);

            $novoDocumento = Documento::create([
                'tipo_nome' => $dados['tipo_nome_destino'],
                'tipo_sigla' => $dados['tipo_destino'],
                'estado_documento' => $dados['estado_documento'] ?? 'emitido',
                'estado_pagamento' => $estadoPagamento,
                'estado_vencimento' => $dados['estado_vencimento'] ?? 'no_prazo',
                'num_fatura' => $numFatura,
                'via' => 'original',
                'empresa_id' => $documentoOrigem->empresa_id,
                'empresa_nome' => $documentoOrigem->empresa_nome,
                'empresa_nif' => $documentoOrigem->empresa_nif,
                'empresa_telefone' => $documentoOrigem->empresa_telefone,
                'empresa_email' => $documentoOrigem->empresa_email,
                'empresa_endereco' => $documentoOrigem->empresa_endereco,
                'cliente_id' => $documentoOrigem->cliente_id,
                'cliente_nome' => $documentoOrigem->cliente_nome,
                'cliente_nif' => $documentoOrigem->cliente_nif,
                'cliente_telefone' => $documentoOrigem->cliente_telefone,
                'cliente_email' => $documentoOrigem->cliente_email,
                'cliente_endereco' => $documentoOrigem->cliente_endereco,
                'caixa' => $dados['caixa'] ?? $documentoOrigem->caixa,
                'data_emissao' => $dados['data_emissao'] ?? now(),
                'data_vencimento' => $dados['data_vencimento'] ?? $documentoOrigem->data_vencimento,
                'forma_pagamento' => $dados['forma_pagamento'] ?? $documentoOrigem->forma_pagamento,
                'movimenta_stock' => $dados['movimenta_stock'] ?? $documentoOrigem->movimenta_stock,
                'taxa_iva' => $documentoOrigem->taxa_iva,
                'valor_iva' => $documentoOrigem->valor_iva,
                'retencao' => $retencao,
                'estado' => 'emitido',
                'hash' => 'TRANSFORM-' . uniqid(),
                'desconto_tipo' => $dados['desconto_tipo'] ?? $documentoOrigem->desconto_tipo,
                'desconto_total' => $totais['desconto_itens_total'] + $totais['desconto_geral'],
                'valor_transporte' => $documentoOrigem->valor_transporte,
                'total_sem_desconto' => $totais['total_sem_desconto'],
                'total_impostos' => $totais['total_impostos'],
                'total_geral' => $totais['total_final'],
                'troco' => $troco,
                'utilizador_id' => $dados['utilizador_id'] ?? $documentoOrigem->utilizador_id,
                'utilizador' => $dados['utilizador'] ?? $documentoOrigem->utilizador,
                'info_guia_id' => $infoGuiaId,
                'documento_origem_id' => $documentoOrigem->id,
            ]);

            foreach (($dados['meiosPagamento'] ?? []) as $meio) {
                MeioPagamentoDocumento::create([
                    'documento_id' => $novoDocumento->id,
                    'descricao' => $meio['descricao'],
                    'valor' => $meio['valor'],
                ]);
            }

            foreach ($quadroImposto as $value) {
                ImpostoDocumento::create([
                    'documento_id' => $novoDocumento->id,
                    'taxa' => round($value['taxa'], 2),
                    'codigo' => $value['codigo'],
                    'isento' => $value['codigo'] === 'ISENTO' ? 1 : 0,
                    'motivo_isencao' => $value['motivo_isencao'],
                    'incidencia' => round($value['incidencia'], 2),
                    'imposto' => round($value['imposto'], 2),
                    'total' => round($value['incidencia'] + $value['imposto'], 2),
                ]);
            }

            $itens = $this->impostoService->montarItensParaInsercao($dados['itens'], $novoDocumento->id);
            $novoDocumento->itens()->createMany($itens);

            $documentoOrigem->update(['estado_documento' => 'transformado']);

            $hash = $this->hashService->calcular($novoDocumento->id);
            $novoDocumento->update(['hash' => $hash]);

            DB::commit();

            return response()->json([
                'message' => "Documento {$documentoOrigem->tipo_sigla} transformado em {$dados['tipo_destino']} com sucesso.",
                'documento_origem' => $documentoOrigem,
                'documento_novo' => $novoDocumento->load(['itens', 'impostosDocumento']),
            ]);
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Erro ao transformar documento.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
