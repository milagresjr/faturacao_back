<?php

namespace Tests\Unit\Models;

use App\Models\LoteProduto;
use PHPUnit\Framework\TestCase;

class LoteProdutoTest extends TestCase
{
    public function test_fillable_contem_campos_esperados(): void
    {
        $lote = new LoteProduto();

        $fillable = $lote->getFillable();

        $this->assertContains('produto_id', $fillable);
        $this->assertContains('codigo_lote', $fillable);
        $this->assertContains('data_validade', $fillable);
        $this->assertContains('qtd_atual', $fillable);
        $this->assertContains('qtd_inicial', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('armazem_id', $fillable);
        $this->assertContains('observacao', $fillable);
    }

    public function test_table_foi_definida_como_lotes_produto(): void
    {
        $lote = new LoteProduto();

        $this->assertEquals('lotes_produto', $lote->getTable());
    }

    public function test_dar_baixa_lanca_excecao_quantidade_insuficiente(): void
    {
        $lote = new LoteProduto();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Quantidade insuficiente no lote');

        $lote->darBaixa(999);
    }
}
