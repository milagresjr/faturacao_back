<?php

namespace App\Services;

use App\Models\Documento;
use Carbon\Carbon;

class AgtHashService
{
    public function calcular(int $documentoId): string
    {
        $documento = Documento::findOrFail($documentoId);

        $invoiceDate = Carbon::parse($documento->data_emissao)->format('Y-m-d');
        $systemEntryDate = Carbon::parse($documento->created_at)->format('Y-m-d\TH:i:s');
        $grossTotal = number_format($documento->total_geral, 2, '.', '');

        $hashAnterior = Documento::where('empresa_id', $documento->empresa_id)
            ->where('tipo_sigla', $documento->tipo_sigla)
            ->whereYear('data_emissao', $documento->data_emissao->year)
            ->where('id', '<', $documento->id)
            ->orderBy('id', 'desc')
            ->value('hash') ?? '';

        $mensagem = $invoiceDate . ';' .
            $systemEntryDate . ';' .
            $documento->num_fatura . ';' .
            $grossTotal . ';' .
            $hashAnterior;

        $privateKey = openssl_pkey_get_private(
            file_get_contents(storage_path('app/keys/ChavePrivada.pem'))
        );

        openssl_sign($mensagem, $assinatura, $privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($assinatura);
    }
}
