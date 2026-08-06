<?php

namespace Tests\Unit\Services;

use App\Services\DocumentoCompraService;
use PHPUnit\Framework\TestCase;

class DocumentoCompraServiceTest extends TestCase
{
    /**
     * Teste de instanciacao do servico.
     * Os testes funcionais completos do DocumentoCompraService estao em
     * tests/Feature/Stock/StockComValidadeTest.php com base de dados real.
     */
    public function test_pode_instanciar_servico(): void
    {
        $service = new DocumentoCompraService();
        $this->assertInstanceOf(DocumentoCompraService::class, $service);
    }
}
