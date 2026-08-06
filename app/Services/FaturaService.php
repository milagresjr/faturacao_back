<?php

namespace App\Services;

use App\Enums\EstadoPagamento;
use App\Models\Conta;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\MeioPagamentoDocumento;
use App\Models\TipoTaxaIva;
use App\Services\DocumentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaturaService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
        private AgtHashService $hashService,
        private CalculoImpostoService $impostoService,
        private DocumentoService $documentoService,
        private ReciboService $reciboService,
    ) {}

    public function criar(array $dados)
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

        $totalEntregue = $totais['total_final'] + $troco - ($totais['total_final'] + $troco - array_sum(array_column($dados['meiosPagamento'] ?? [], 'valor')));

        $totalEntregueCalc = 0;
        foreach (($dados['meiosPagamento'] ?? []) as $mp) {
            $totalEntregueCalc += (float) $mp['valor'];
        }

        DB::beginTransaction();
        try {
            $infoGuiaId = null;
            if (in_array($dados['sigla_fatura'] ?? '', ['GT', 'GR'])) {
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

            $estadoPagamento = $this->impostoService->determinarEstadoPagamento(
                $totais['total_final'],
                $totalEntregueCalc
            );

            $estadoDoc = $dados['estado_documento'] ?? 'emitido';
            $numFatura = $estadoDoc === 'rascunho'
                ? ''
                : $this->numeroService->gerarNumeroDocumento($dados['serie_id']);

            $documento = Documento::create([
                'tipo_nome' => $dados['tipo_fatura'],
                'tipo_sigla' => $dados['sigla_fatura'],
                'armazem_id' => $dados['armazem_id'] ?? null,
                'estado_documento' => $estadoDoc,
                'estado_pagamento' => $estadoPagamento,
                'estado_vencimento' => $dados['estado_vencimento'] ?? 'no_prazo',
                'num_fatura' => $numFatura,
                'via' => $dados['via'] ?? 'original',
                'empresa_id' => $dados['empresa_id'],
                'empresa_nome' => $dados['empresa_nome'] ?? $empresa->nome,
                'empresa_nif' => $dados['empresa_nif'] ?? $empresa->nif,
                'empresa_telefone' => $dados['empresa_telefone'] ?? $empresa->telefone,
                'empresa_email' => $dados['empresa_email'] ?? $empresa->email,
                'empresa_endereco' => $dados['empresa_endereco'] ?? $empresa->morada,
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
                'serie_id' => $dados['serie_id'],
            ]);

            $this->inserirBancosDocumento($documento->id, $dados['empresa_id']);
            $this->inserirMeiosPagamento($documento->id, $dados['meiosPagamento'] ?? []);
            $this->inserirImpostos($documento->id, $quadroImposto);

            $itens = $this->impostoService->montarItensParaInsercao($dados['itens'], $documento->id);
            $documento->itens()->createMany($itens);

            if (!empty($dados['is_apronto']) && $dados['is_apronto'] === '1') {
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

                $recibo = $this->reciboService->criar($dataRecibo);

                DB::commit();

                return response()->json([
                    'message' => 'Factura e Recibo criados com sucesso.',
                    'documento' => $documento->load('itens'),
                    'documento_recibo' => $recibo->original['documento'] ?? '',
                ], 201);
            }

            if ($documento->num_fatura) {
                $hash = $this->hashService->calcular($documento->id);
                $documento->update(['hash' => $hash]);
            }

            if (!empty($dados['movimenta_stock'])) {
                $this->documentoService->updateStock($documento->load('itens'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            try {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $rollbackError) {
                // Savepoint already released, ignore
            }
            return response()->json([
                'message' => 'Erro ao criar o documento.',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }

        return response()->json([
            'message' => 'Documento criado com sucesso.',
            'documento' => $documento->load('itens'),
        ], 201);
    }

    private function inserirBancosDocumento(int $documentoId, int $empresaId): void
    {
        $bancos = Conta::with('banco')
            ->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->get();

        foreach ($bancos as $banco) {
            DB::table('bancos_documento')->insert([
                'documento_id' => $documentoId,
                'sigla' => $banco['banco']->sigla,
                'descricao' => $banco['banco']->descricao,
                'numero_conta' => $banco->numero_conta,
                'iban' => $banco->iban,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function inserirMeiosPagamento(int $documentoId, array $meiosPagamento): void
    {
        foreach ($meiosPagamento as $meio) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documentoId,
                'descricao' => $meio['descricao'],
                'valor' => $meio['valor'],
            ]);
        }
    }

    private function inserirImpostos(int $documentoId, array $quadroImposto): void
    {
        foreach ($quadroImposto as $value) {
            ImpostoDocumento::create([
                'documento_id' => $documentoId,
                'taxa' => round($value['taxa'], 2),
                'codigo' => $value['codigo'],
                'isento' => $value['codigo'] === 'ISENTO' ? 1 : 0,
                'motivo_isencao' => $value['motivo_isencao'],
                'incidencia' => round($value['incidencia'], 2),
                'imposto' => round($value['imposto'], 2),
                'total' => round($value['incidencia'] + $value['imposto'], 2),
            ]);
        }
    }
}
