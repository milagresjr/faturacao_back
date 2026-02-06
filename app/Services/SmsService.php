<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function enviarStockBaixo($stock)
    {
        $produto = $stock->produto;   // relacionamento Produto
        $armazem = $stock->armazem;   // relacionamento Armazém

        $empresaId = $stock->empresa_id;

        $empresa = Empresa::find($empresaId);

        $mensagem = "⚠️ ALERTA DE STOCK BAIXO ⚠️\n"
            . "Produto: {$produto->nome}\n"
            . "Armazém: {$armazem->nome}\n"
            . "Stock atual: {$stock->stock_atual}\n"
            . "Mínimo: {$stock->stock_min}";

        // Envia para a API de SMS configurada
        $response = Http::withHeaders([
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
            'Authorization' => 'Token ' . trim(config('services.sms.key')),
        ])->post(config('services.sms.url'), [
            'to' => (string) $empresa->telefone,
            'from' => config('services.sms.from'),
            'message' => $mensagem,
        ]);

        // Verifica se deu certo
        if ($response->failed()) {
            // Log do erro
            Log::error('Falha ao enviar SMS', [
                'status' => $response->status(),
                'body' => $response->body(),
                'response_json' => $response->json(),
                'empresa_id' => $empresa->id,
                'telefone' => $empresa->telefone,
                'mensagem' => $mensagem,
            ]);
        } else {
            Log::info('SMS enviado com sucesso', [
                'empresa_id' => $empresa->id,
                'telefone' => $empresa->telefone,
                'mensagem' => $mensagem,
                'response' => $response->json(),
            ]);
        }
    }
}
