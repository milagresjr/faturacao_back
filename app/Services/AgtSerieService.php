<?php

namespace App\Services;

use App\Models\ConfiguracaoAgt;
use App\Models\SerieAgt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgtSerieService
{
    private const BASE_URLS = [
        'testes' => 'https://sifphml.minfin.gov.ao',
        'producao' => 'https://sifp.minfin.gov.ao',
    ];

    public function __construct(
        private AgtHashService $hashService,
    ) {}

    /**
     * Listar séries AGT de uma empresa.
     */
    public function listar(int $empresaId, array $filtros = [])
    {
        $query = SerieAgt::daEmpresa($empresaId)->orderByDesc('id');

        if (!empty($filtros['tipo_documento'])) {
            $query->porTipo($filtros['tipo_documento']);
        }

        if (!empty($filtros['series_year'])) {
            $query->porAno($filtros['series_year']);
        }

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        $perPage = $filtros['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Solicitar criação de série à AGT.
     */
    public function solicitar(array $dados, int $empresaId): array
    {
        $config = ConfiguracaoAgt::where('empresa_id', $empresaId)->first();

        if (!$config) {
            throw new \RuntimeException('Configuração AGT não encontrada.');
        }

        if (!$config->comunicacao_ativa) {
            throw new \RuntimeException('Comunicação AGT desativada.');
        }

        $username = config('services.agt.username');
        $password = config('services.agt.password');

        if (empty($username) || empty($password)) {
            throw new \RuntimeException('Credenciais AGT não configuradas no servidor.');
        }

        $submissionUuid = (string) Str::uuid();

        $payload = $this->montarPayloadSolicitar($dados, $config, $submissionUuid);

        $serieAgt = SerieAgt::create([
            'empresa_id' => $empresaId,
            'armazem_id' => $dados['armazem_id'] ?? null,
            'series_code' => 'PENDENTE',
            'tipo_documento' => $dados['tipo_documento'],
            'series_year' => $dados['series_year'],
            'contingency_indicator' => $dados['contingency_indicator'] ?? 'N',
            'authorized_quantity' => 0,
            'status' => 'pendente',
            'solicitud_payload' => json_encode($payload),
        ]);

        try {
            $resposta = $this->enviarSolicitacao($payload, $config);

            if (isset($resposta['seriesFEResult'])) {
                $seriesResult = $resposta['seriesFEResult'];

                $serieAgt->update([
                    'series_code' => $seriesResult['seriesCode'],
                    'authorized_quantity' => $seriesResult['authorizedQuantity'] ?? 0,
                    'first_document_no' => $seriesResult['firstDocumentNo'] ?? '1',
                    'last_document_no' => $seriesResult['lastDocumentNo'] ?? null,
                    'status' => 'ativa',
                    'resposta_payload' => json_encode($resposta),
                ]);

                return [
                    'success' => true,
                    'serie' => $serieAgt,
                ];
            }

            $errorList = $resposta['errorList'] ?? [];
            $errorMsg = $errorList[0] ?? 'Erro desconhecido ao solicitar série.';

            $serieAgt->update([
                'status' => 'erro',
                'resposta_payload' => json_encode($resposta),
            ]);

            return [
                'success' => false,
                'message' => is_array($errorMsg) ? ($errorMsg['descriptionError'] ?? json_encode($errorMsg)) : $errorMsg,
                'serie' => $serieAgt,
            ];
        } catch (\Throwable $th) {
            Log::error('Erro ao solicitar série AGT', [
                'empresa_id' => $empresaId,
                'error' => $th->getMessage(),
            ]);

            $serieAgt->update([
                'status' => 'erro',
                'resposta_payload' => json_encode(['error' => $th->getMessage()]),
            ]);

            throw $th;
        }
    }

    /**
     * Listar séries existentes na AGT e sincronizar com a BD local.
     */
    public function sincronizar(int $empresaId, array $filtros = []): array
    {
        $config = ConfiguracaoAgt::where('empresa_id', $empresaId)->first();

        if (!$config) {
            throw new \RuntimeException('Configuração AGT não encontrada.');
        }

        $username = config('services.agt.username');
        $password = config('services.agt.password');

        if (empty($username) || empty($password)) {
            throw new \RuntimeException('Credenciais AGT não configuradas.');
        }

        $payload = [
            'schemaVersion' => '1.2',
            'taxRegistrationNumber' => $config->empresa->nif ?? '',
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => [
                'softwareInfoDetail' => [
                    'productId' => 'SoftSeven Faturacao',
                    'productVersion' => '1.0.0',
                    'softwareValidationNumber' => $config->numero_validacao_software ?? '',
                ],
                'jwsSoftwareSignature' => $this->hashService->signSoftwareInfo($config->numero_validacao_software),
            ],
            'jwsSignature' => $this->hashService->signRequest(
                ['taxRegistrationNumber' => $config->empresa->nif ?? ''],
                $config->certificado_digital ?? ''
            ),
        ];

        if (!empty($filtros['seriesCode'])) {
            $payload['seriesCode'] = $filtros['seriesCode'];
        }

        if (!empty($filtros['series_year'])) {
            $payload['seriesYear'] = $filtros['series_year'];
        }

        if (!empty($filtros['documentType'])) {
            $payload['documentType'] = $filtros['documentType'];
        }

        $baseUrl = self::BASE_URLS[$config->ambiente] ?? self::BASE_URLS['testes'];

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post("{$baseUrl}/sigt/fe/v1/listarSeries", $payload);

        $body = $response->json();

        if ($response->failed()) {
            throw new \RuntimeException('Erro ao listar séries na AGT: HTTP ' . $response->status());
        }

        $seriesInfo = $body['seriresInfo'] ?? $body['seriesInfo'] ?? [];

        $sincronizadas = 0;
        $existentes = 0;

        foreach ($seriesInfo as $serieInfo) {
            $seriesCode = $serieInfo['seriesCode'] ?? null;

            if (!$seriesCode) {
                continue;
            }

            $existente = SerieAgt::where('empresa_id', $empresaId)
                ->where('series_code', $seriesCode)
                ->first();

            if ($existente) {
                $existente->update([
                    'status' => $serieInfo['seriesStatus'] ?? $existente->status,
                    'authorized_quantity' => $serieInfo['authorizedQuantity'] ?? $existente->authorized_quantity,
                ]);
                $existentes++;
                continue;
            }

            SerieAgt::create([
                'empresa_id' => $empresaId,
                'series_code' => $seriesCode,
                'tipo_documento' => $serieInfo['documentType'] ?? 'FT',
                'series_year' => $serieInfo['seriesYear'] ?? now()->format('Y'),
                'contingency_indicator' => $serieInfo['contingencyIndicator'] ?? 'N',
                'authorized_quantity' => $serieInfo['authorizedQuantity'] ?? 0,
                'first_document_no' => $serieInfo['firstDocumentNo'] ?? '1',
                'last_document_no' => $serieInfo['lastDocumentNo'] ?? null,
                'status' => $serieInfo['seriesStatus'] ?? 'ativa',
                'resposta_payload' => json_encode($serieInfo),
            ]);
            $sincronizadas++;
        }

        return [
            'success' => true,
            'total_encontradas' => count($seriesInfo),
            'sincronizadas' => $sincronizadas,
            'existentes_atualizadas' => $existentes,
        ];
    }

    /**
     * Toggle status (ativa/inativa).
     */
    public function toggleAtivo(int $id): SerieAgt
    {
        $serie = SerieAgt::findOrFail($id);

        $novoStatus = $serie->status === 'ativa' ? 'inativa' : 'ativa';
        $serie->update(['status' => $novoStatus]);

        return $serie->fresh();
    }

    private function montarPayloadSolicitar(array $dados, ConfiguracaoAgt $config, string $submissionUuid): array
    {
        $payload = [
            'schemaVersion' => '1.2',
            'submissionUUID' => $submissionUuid,
            'taxRegistrationNumber' => $config->empresa->nif ?? '',
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => [
                'softwareInfoDetail' => [
                    'productId' => 'SoftSeven Faturacao',
                    'productVersion' => '1.0.0',
                    'softwareValidationNumber' => $config->numero_validacao_software ?? '',
                ],
                'jwsSoftwareSignature' => $this->hashService->signSoftwareInfo($config->numero_validacao_software),
            ],
            'seriesYear' => $dados['series_year'],
            'documentType' => $dados['tipo_documento'],
            'establishmentNumber' => $dados['establishment_number'] ?? 'SEDE',
            'seriesContingencyIndicator' => $dados['contingency_indicator'] ?? 'N',
        ];

        $payload['jwsSignature'] = $this->hashService->signRequest(
            [
                'taxRegistrationNumber' => $config->empresa->nif ?? '',
                'seriesYear' => $dados['series_year'],
                'documentType' => $dados['tipo_documento'],
                'establishmentNumber' => $dados['establishment_number'] ?? 'SEDE',
                'seriesContingencyIndicator' => $dados['contingency_indicator'] ?? 'N',
            ],
            $config->certificado_digital ?? ''
        );

        return $payload;
    }

    private function enviarSolicitacao(array $payload, ConfiguracaoAgt $config): array
    {
        $baseUrl = self::BASE_URLS[$config->ambiente] ?? self::BASE_URLS['testes'];

        $response = Http::withBasicAuth(config('services.agt.username'), config('services.agt.password'))
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post("{$baseUrl}/sigt/fe/v1/solicitarSerie", $payload);

        $body = $response->json();

        if ($response->failed()) {
            $errorMsg = $body['errorList'][0] ?? 'Erro HTTP ' . $response->status();
            throw new \RuntimeException(
                "AGT retornou erro HTTP {$response->status()}: " . (is_array($errorMsg) ? json_encode($errorMsg) : $errorMsg),
                $response->status()
            );
        }

        return $body;
    }
}
