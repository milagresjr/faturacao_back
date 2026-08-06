<?php

namespace Tests\Unit\Services;

use App\Services\DocumentoService;
use PHPUnit\Framework\TestCase;

class DocumentoServiceTest extends TestCase
{
    /**
     * Teste de instanciacao do servico.
     * Os testes funcionais completos do DocumentoService estao em
     * tests/Feature/Stock/StockComValidadeTest.php com base de dados real.
     */
    public function test_pode_instanciar_servico(): void
    {
        $service = new DocumentoService();
        $this->assertInstanceOf(DocumentoService::class, $service);
    }
}
