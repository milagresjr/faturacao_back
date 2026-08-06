<?php

namespace Tests\Unit\Services;

use App\Services\ValidadeService;
use PHPUnit\Framework\TestCase;

class ValidadeServiceTest extends TestCase
{
    /**
     * Teste de instanciacao do servico.
     * Os testes funcionais completos do ValidadeService estao em
     * tests/Feature/Stock/StockComValidadeTest.php com base de dados real.
     */
    public function test_pode_instanciar_servico(): void
    {
        $service = new ValidadeService();
        $this->assertInstanceOf(ValidadeService::class, $service);
    }
}
