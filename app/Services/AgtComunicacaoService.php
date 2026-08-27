<?php

namespace App\Services;

use App\Models\ComunicacaoAgt;
use App\Models\ConfiguracaoAgt;
use App\Models\Documento;
use App\Models\SerieAgt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgtComunicacaoService
{
    private const BASE_URLS = [
        'testes' => 'https://sifphml.minfin.gov.ao',
        'producao' => 'https://sifp.minfin.gov.ao',
    ];

    public function __construct(
        private AgtHashService $hashService,
    ) {}

    public function enviarDocumento(Documento $documento): array
    {
        $config = ConfiguracaoAgt::where('empresa_id', $documento->empresa_id)->first();

        if (!$config) {
            return [
                'success' => false,
                'message' => 'Configuração AGT não encontrada.',
            ];
        }

        if (!$config->comunicacao_ativa) {
            return [
                'success' => false,
                'message' => 'Comunicação AGT desativada.',
            ];
        }

        $username = config('services.agt.username');
        $password = config('services.agt.password');

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Credenciais AGT não configuradas no servidor. Contacte o suporte.',
            ];
        }

        $submissionUuid = (string) Str::uuid();
        $payload = $this->montarPayload($documento, $config, $submissionUuid);

        $comunicacao = ComunicacaoAgt::create([
            'empresa_id' => $documento->empresa_id,
            'documento_id' => $documento->id,
            'tipo_comunicacao' => 'envio_documento',
            'status' => 'pendente',
            'submission_uuid' => $submissionUuid,
            'request_payload' => json_encode($payload),
            'tentativas' => 0,
        ]);

        try {
            $resposta = $this->enviarParaAGT($payload, $config);

            $comunicacao->update([
                'status' => $resposta['status'],
                'response_payload' => json_encode($resposta['raw'] ?? $resposta),
                'request_id' => $resposta['request_id'] ?? null,
                'codigo_validacao_agt' => $resposta['codigo_validacao'] ?? null,
                'codigo_erro' => $resposta['codigo_erro'] ?? null,
                'tentativas' => $comunicacao->tentativas + 1,
                'ultima_tentativa' => now(),
            ]);

            return [
                'success' => $comunicacao->status === 'confirmado' || $comunicacao->status === 'enviado',
                'comunicacao' => $comunicacao,
            ];
        } catch (\Throwable $th) {
            Log::error('Erro ao enviar documento para AGT', [
                'documento_id' => $documento->id,
                'error' => $th->getMessage(),
            ]);

            $comunicacao->update([
                'status' => 'erro',
                'response_payload' => $th->getMessage(),
                'codigo_erro' => (string) $th->getCode(),
                'tentativas' => $comunicacao->tentativas + 1,
                'ultima_tentativa' => now(),
            ]);

            return [
                'success' => false,
                'message' => $th->getMessage(),
                'comunicacao' => $comunicacao,
            ];
        }
    }

    private function montarPayload(Documento $documento, ConfiguracaoAgt $config, string $submissionUuid): array
    {
        $itens = $documento->itens()->get();

        $lines = $itens->map(function ($item, $index) {
            $taxes = [];

            $taxPercentage = (float) ($item->iva_percent ?? 0);
            $taxContribution = round($item->total * $taxPercentage / (100 + $taxPercentage), 2);
            $netAmount = round($item->total - $taxContribution, 2);

            if ($taxPercentage > 0) {
                $taxes[] = [
                    'taxType' => 'IVA',
                    'taxCountryRegion' => 'AO',
                    'taxCode' => $item->codigo_iva ?: 'NOR',
                    'taxPercentage' => $taxPercentage,
                    'taxContribution' => $taxContribution,
                ];
            }

            return [
                'lineNumber' => $index + 1,
                'productCode' => $item->produto_codigo ?? '',
                'productDescription' => $item->produto_nome ?? $item->descricao ?? '',
                'quantity' => (float) $item->quantidade,
                'unitOfMeasure' => 'UN',
                'unitPrice' => (float) $item->preco_unitario,
                'unitPriceBase' => (float) $item->preco_unitario,
                'debitAmount' => 0,
                'creditAmount' => (float) $item->total,
                'taxes' => $taxes,
                'settlementAmount' => (float) ($item->desconto_fixo ?? 0),
            ];
        });

        $grossTotal = (float) $documento->total_geral;
        $totalIva = (float) $documento->valor_iva;
        $netTotal = round($grossTotal - $totalIva, 2);

        $softwareInfoDetail = [
            'productId' => 'SoftSeven Faturacao',
            'productVersion' => '1.0.0',
            'softwareValidationNumber' => $config->numero_validacao_software ?? '',
        ];

        $jwsSoftwareSignature = $this->hashService->signSoftwareInfo($config->numero_validacao_software);

        $documentNo = $this->obterDocumentNo($documento);

        $documentPayload = [
            'documentNo' => $documentNo,
            'documentStatus' => 'N',
            'jwsDocumentSignature' => $this->hashService->signDocument($documento),
            'documentDate' => $documento->data_emissao->format('Y-m-d'),
            'documentType' => $documento->tipo_sigla,
            'eacCode' => '',
            'systemEntryDate' => $documento->created_at->format('Y-m-d\TH:i:s'),
            'customerTaxID' => $documento->cliente_nif ?? '',
            'customerCountry' => 'AO',
            'companyName' => $documento->cliente_nome ?? '',
            'lines' => $lines->toArray(),
            'documentTotals' => [
                'taxPayable' => $totalIva,
                'netTotal' => $netTotal,
                'grossTotal' => $grossTotal,
            ],
            'withholdingTaxList' => [],
        ];

        return [
            'schemaVersion' => '1.2',
            'submissionUUID' => $submissionUuid,
            'taxRegistrationNumber' => $documento->empresa_nif,
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => [
                'softwareInfoDetail' => $softwareInfoDetail,
                'jwsSoftwareSignature' => $jwsSoftwareSignature,
            ],
            'numberOfEntries' => 1,
            'documents' => [$documentPayload],
        ];
    }

    /**
     * Resolve the AGT documentNo format: {tipo} {seriesCode}/{sequencial}
     * Falls back to the original num_fatura if no active series_agt is found.
     */
    private function obterDocumentNo(Documento $documento): string
    {
        $ano = $documento->data_emissao->format('Y');

        $serie = SerieAgt::daEmpresa($documento->empresa_id)
            ->porTipo($documento->tipo_sigla)
            ->porAno($ano)
            ->ativas()
            ->where('contingency_indicator', 'N')
            ->first();

        if (!$serie) {
            $serie = SerieAgt::daEmpresa($documento->empresa_id)
                ->porTipo($documento->tipo_sigla)
                ->porAno($ano)
                ->ativas()
                ->first();
        }

        if (!$serie) {
            return $documento->num_fatura;
        }

        $sequencial = $this->extrairSequencial($documento->num_fatura);

        return "{$documento->tipo_sigla} {$serie->series_code}/{$sequencial}";
    }

    /**
     * Extract the sequential number from num_fatura (e.g., "FT SFSV2025/1" → "1").
     */
    private function extrairSequencial(string $numFatura): string
    {
        $parts = explode('/', $numFatura);

        return end($parts);
    }

    private function enviarParaAGT(array $payload, ConfiguracaoAgt $config): array
    {
        $baseUrl = self::BASE_URLS[$config->ambiente] ?? self::BASE_URLS['testes'];

        $response = Http::withBasicAuth(config('services.agt.username'), config('services.agt.password'))
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post("{$baseUrl}/sigt/fe/v1/registarFactura", $payload);

        $body = $response->json();

        if ($response->failed()) {
            $errorCode = $body['errorList'][0]['idError'] ?? null;
            $errorMsg = $body['errorList'][0]['descriptionError'] ?? 'Erro HTTP ' . $response->status();

            throw new \RuntimeException(
                "AGT retornou erro HTTP {$response->status()}: {$errorMsg}",
                $response->status()
            );
        }

        $errorList = $body['errorList'] ?? [];

        if (!empty($errorList)) {
            return [
                'status' => 'erro',
                'request_id' => $body['requestID'] ?? null,
                'codigo_erro' => $errorList[0]['idError'] ?? null,
                'message' => $errorList[0]['descriptionError'] ?? 'Erro desconhecido',
                'raw' => $body,
            ];
        }

        return [
            'status' => 'enviado',
            'request_id' => $body['requestID'] ?? null,
            'raw' => $body,
        ];
    }

    public function consultarEstado(int $comunicacaoId): ?ComunicacaoAgt
    {
        $comunicacao = ComunicacaoAgt::find($comunicacaoId);

        if (!$comunicacao) {
            return null;
        }

        if ($comunicacao->status !== 'enviado' || !$comunicacao->request_id) {
            return $comunicacao;
        }

        $config = ConfiguracaoAgt::where('empresa_id', $comunicacao->empresa_id)->first();

        if (!$config || !config('services.agt.username') || !config('services.agt.password')) {
            return $comunicacao;
        }

        try {
            $resultado = $this->consultarNaAGT($comunicacao, $config);

            $comunicacao->update([
                'status' => $resultado['status'],
                'response_payload' => json_encode($resultado['raw'] ?? $resultado),
                'codigo_validacao_agt' => $resultado['codigo_validacao'] ?? $comunicacao->codigo_validacao_agt,
                'codigo_erro' => $resultado['codigo_erro'] ?? null,
                'ultima_tentativa' => now(),
            ]);
        } catch (\Throwable $th) {
            Log::error('Erro ao consultar estado na AGT', [
                'comunicacao_id' => $comunicacaoId,
                'error' => $th->getMessage(),
            ]);
        }

        return $comunicacao->fresh();
    }

    private function consultarNaAGT(ComunicacaoAgt $comunicacao, ConfiguracaoAgt $config): array
    {
        $baseUrl = self::BASE_URLS[$config->ambiente] ?? self::BASE_URLS['testes'];

        $payload = [
            'schemaVersion' => '1.2',
            'submissionUUID' => $comunicacao->submission_uuid,
            'taxRegistrationNumber' => $comunicacao->documento->empresa_nif ?? '',
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => [
                'softwareInfoDetail' => [
                    'productId' => 'SoftSeven Faturacao',
                    'productVersion' => '1.0.0',
                    'softwareValidationNumber' => $config->numero_validacao_software ?? '',
                ],
                'jwsSoftwareSignature' => $this->hashService->signSoftwareInfo($config->numero_validacao_software),
            ],
            'requestID' => $comunicacao->request_id,
        ];

        $response = Http::withBasicAuth(config('services.agt.username'), config('services.agt.password'))
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post("{$baseUrl}/sigt/fe/v1/obterEstado", $payload);

        $body = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('Erro ao consultar AGT: HTTP ' . $response->status());
        }

        $resultCode = $body['resultCode'] ?? null;

        if ($resultCode === '0') {
            $validDoc = collect($body['documentStatusList'] ?? [])->firstWhere('documentStatus', 'V');

            return [
                'status' => 'confirmado',
                'codigo_validacao' => $validDoc['documentNo'] ?? null,
                'raw' => $body,
            ];
        }

        if ($resultCode === '8') {
            return [
                'status' => 'enviado',
                'raw' => $body,
            ];
        }

        if ($resultCode === '7') {
            return [
                'status' => 'enviado',
                'raw' => $body,
            ];
        }

        $errorList = $body['requestErrorList'] ?? [];
        $docErrors = collect($body['documentStatusList'] ?? [])->pluck('errorList')->flatten();

        $firstError = $errorList[0] ?? $docErrors->first() ?? null;

        return [
            'status' => 'erro',
            'codigo_erro' => $firstError['errorCode'] ?? null,
            'message' => $firstError['errorDescription'] ?? 'Documento inválido',
            'raw' => $body,
        ];
    }

    public function reenviar(int $comunicacaoId): array
    {
        $comunicacao = ComunicacaoAgt::findOrFail($comunicacaoId);
        $documento = Documento::find($comunicacao->documento_id);

        if (!$documento) {
            return ['success' => false, 'message' => 'Documento não encontrado.'];
        }

        return $this->enviarDocumento($documento);
    }
}
