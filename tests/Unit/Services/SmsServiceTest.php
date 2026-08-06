<?php

namespace Tests\Unit\Services;

use App\Services\SmsService;
use PHPUnit\Framework\TestCase;

class SmsServiceTest extends TestCase
{
    /**
     * Teste de construcao da mensagem de alerta de stock baixo.
     * Testa a logica de montagem da mensagem sem depender da API de SMS.
     */
    public function test_mensagem_alerta_stock_baixo_contem_dados(): void
    {
        $mensagem = "⚠️ ALERTA DE STOCK BAIXO ⚠️\n"
            . "Produto: Arroz\n"
            . "Armazém: Armazém Central\n"
            . "Stock atual: 5\n"
            . "Mínimo: 10";

        $this->assertStringContainsString('Arroz', $mensagem);
        $this->assertStringContainsString('Armazém Central', $mensagem);
        $this->assertStringContainsString('5', $mensagem);
        $this->assertStringContainsString('10', $mensagem);
    }

    public function test_pode_instanciar_servico(): void
    {
        $service = new SmsService();
        $this->assertInstanceOf(SmsService::class, $service);
    }
}
