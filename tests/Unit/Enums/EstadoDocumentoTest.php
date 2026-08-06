<?php

namespace Tests\Unit\Enums;

use App\Enums\EstadoDocumento;
use PHPUnit\Framework\TestCase;

class EstadoDocumentoTest extends TestCase
{
    public function test_deve_ter_todos_os_casos_esperados(): void
    {
        $casos = EstadoDocumento::cases();

        $this->assertCount(6, $casos);
        $this->assertContains(EstadoDocumento::RASCUNHO, $casos);
        $this->assertContains(EstadoDocumento::EMITIDO, $casos);
        $this->assertContains(EstadoDocumento::ANULADO, $casos);
        $this->assertContains(EstadoDocumento::CANCELADO, $casos);
        $this->assertContains(EstadoDocumento::ARQUIVADO, $casos);
        $this->assertContains(EstadoDocumento::TRANSFORMADO, $casos);
    }

    public function test_cada_caso_deve_ter_um_valor_string(): void
    {
        $this->assertEquals('rascunho', EstadoDocumento::RASCUNHO->value);
        $this->assertEquals('emitido', EstadoDocumento::EMITIDO->value);
        $this->assertEquals('anulado', EstadoDocumento::ANULADO->value);
        $this->assertEquals('cancelado', EstadoDocumento::CANCELADO->value);
        $this->assertEquals('arquivado', EstadoDocumento::ARQUIVADO->value);
        $this->assertEquals('transformado', EstadoDocumento::TRANSFORMADO->value);
    }
}
