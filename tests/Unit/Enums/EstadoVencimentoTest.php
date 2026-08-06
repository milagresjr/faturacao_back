<?php

namespace Tests\Unit\Enums;

use App\Enums\EstadoVencimento;
use PHPUnit\Framework\TestCase;

class EstadoVencimentoTest extends TestCase
{
    public function test_deve_ter_todos_os_casos_esperados(): void
    {
        $casos = EstadoVencimento::cases();

        $this->assertCount(3, $casos);
        $this->assertContains(EstadoVencimento::NO_PRAZO, $casos);
        $this->assertContains(EstadoVencimento::VENCIDO, $casos);
        $this->assertContains(EstadoVencimento::EM_ATRASO, $casos);
    }

    public function test_cada_caso_deve_ter_um_valor_string(): void
    {
        $this->assertEquals('no_prazo', EstadoVencimento::NO_PRAZO->value);
        $this->assertEquals('vencido', EstadoVencimento::VENCIDO->value);
        $this->assertEquals('em_atraso', EstadoVencimento::EM_ATRASO->value);
    }
}
