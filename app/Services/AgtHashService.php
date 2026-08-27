<?php

namespace App\Services;

use App\Models\Documento;

class AgtHashService
{
    private string $softwareProductId = 'SoftSeven Faturacao';
    private string $softwareProductVersion = '1.0.0';

    /**
     * Assinar informações do software (jwsSoftwareSignature).
     * Usa a chave privada do software (gerada pela SoftSeven).
     */
    public function signSoftwareInfo(?string $softwareValidationNumber = null): string
    {
        $payload = [
            'productId' => $this->softwareProductId,
            'productVersion' => $this->softwareProductVersion,
            'softwareValidationNumber' => $softwareValidationNumber ?? '',
        ];

        return $this->sign($payload, $this->getSoftwarePrivateKey());
    }

    /**
     * Assinar um documento individual (jwsDocumentSignature).
     * Usa a chave privada do software.
     */
    public function signDocument(Documento $documento): string
    {
        $payload = [
            'documentNo' => $documento->num_fatura,
            'taxRegistrationNumber' => $documento->empresa_nif,
            'documentType' => $documento->tipo_sigla,
            'documentDate' => $documento->data_emissao->format('Y-m-d'),
            'customerTaxID' => $documento->cliente_nif ?? '',
            'customerCountry' => 'AO',
            'companyName' => $documento->empresa_nome ?? '',
            'documentTotals' => [
                'taxPayable' => (float) $documento->valor_iva,
                'netTotal' => (float) $documento->total_sem_desconto,
                'grossTotal' => (float) $documento->total_geral,
            ],
        ];

        return $this->sign($payload, $this->getSoftwarePrivateKey());
    }

    /**
     * Assinar o corpo do pedido (jwsSignature).
     * Usa a chave privada do contribuinte (descarregada do portal AGT).
     */
    public function signRequest(array $payload, string $privateKeyPem): string
    {
        return $this->sign($payload, $privateKeyPem);
    }

    /**
     * Calcular hash de integridade (para campo hash do documento).
     * Mantido por compatibilidade com o modelo existente.
     */
    public function calcular(int $documentoId): string
    {
        $documento = Documento::findOrFail($documentoId);

        $mensagem = $documento->empresa_nif . ';' .
            $documento->num_fatura . ';' .
            $documento->tipo_sigla . ';' .
            $documento->data_emissao->format('Y-m-d') . ';' .
            $documento->total_geral;

        return hash('sha256', $mensagem);
    }

    private function sign(array $payload, string $privateKeyPem): string
    {
        $header = json_encode(['typ' => 'JWS', 'alg' => 'RS256'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payloadJson);

        $signingInput = $base64Header . '.' . $base64Payload;

        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKey) {
            throw new \RuntimeException('Chave privada inválida ou não encontrada.');
        }

        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($privateKey);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function getSoftwarePrivateKey(): string
    {
        $path = storage_path('app/keys/ChavePrivada.pem');

        if (!file_exists($path)) {
            throw new \RuntimeException(
                'Chave privada do software não encontrada. ' .
                'Gere com: openssl genrsa -out ChavePrivada.pem 4096'
            );
        }

        return file_get_contents($path);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
