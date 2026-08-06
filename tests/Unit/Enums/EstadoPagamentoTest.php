<?php

namespace Tests\Unit\Enums;

use App\Enums\EstadoPagamento;
use PHPUnit\Framework\TestCase;

class EstadoPagamentoTest extends TestCase
{
    public function test_deve_ter_todos_os_casos_esperados(): void
    {
        $casos = EstadoPagamento::cases();

        $this->assertCount(4, $casos);
        $this->assertContains(EstadoPagamento::NAO_PAGO, $casos);
        $this->assertContains(EstadoPagamento::PARCIALMENTE_PAGO, $casos);
        $this->assertContains(EstadoPagamento::PAGO, $casos);
        $this->assertContains(EstadoPagamento::REEMBOLSADO, $casos);
    }

    public function test_cada_caso_deve_ter_um_valor_string(): void
    {
        $this->assertEquals('nao_pago', EstadoPagamento::NAO_PAGO->value);
        $this->assertEquals('parcialmente_pago', EstadoPagamento::PARCIALMENTE_PAGO->value);
        $this->assertEquals('pago', EstadoPagamento::PAGO->value);
        $this->assertEquals('reembolsado', EstadoPagamento::REEMBOLSADO->value);
    }
}
