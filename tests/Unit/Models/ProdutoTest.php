<?php

namespace Tests\Unit\Models;

use App\Models\Produto;
use PHPUnit\Framework\TestCase;

class ProdutoTest extends TestCase
{
    public function test_precisa_controlar_validade_retorna_true_quando_controla_validade(): void
    {
        $produto = new Produto(['controla_validade' => true]);

        $this->assertTrue($produto->precisaControlarValidade());
    }

    public function test_precisa_controlar_validade_retorna_false_quando_nao_controla(): void
    {
        $produto = new Produto(['controla_validade' => false]);

        $this->assertFalse($produto->precisaControlarValidade());
    }

    public function test_fillable_contem_campos_criticos(): void
    {
        $produto = new Produto();

        $fillable = $produto->getFillable();

        $this->assertContains('nome', $fillable);
        $this->assertContains('preco_custo', $fillable);
        $this->assertContains('preco_venda', $fillable);
        $this->assertContains('controla_validade', $fillable);
        $this->assertContains('movimenta_stock', $fillable);
        $this->assertContains('codigo_produto', $fillable);
        $this->assertContains('empresa_id', $fillable);
    }

    public function test_table_foi_definida_como_produtos(): void
    {
        $produto = new Produto();

        $this->assertEquals('produtos', $produto->getTable());
    }

    public function test_casts_boolean_para_controla_validade_e_movimenta_stock(): void
    {
        $produto = new Produto();

        $casts = $produto->getCasts();

        $this->assertEquals('boolean', $casts['controla_validade']);
        $this->assertEquals('boolean', $casts['movimenta_stock']);
    }
}
