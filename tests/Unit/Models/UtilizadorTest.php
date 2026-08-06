<?php

namespace Tests\Unit\Models;

use App\Models\Utilizador;
use PHPUnit\Framework\TestCase;

class UtilizadorTest extends TestCase
{
    public function test_is_admin_retorna_true_quando_nivel_acesso_admin(): void
    {
        $utilizador = new Utilizador(['nivel_acesso' => 'admin']);

        $this->assertTrue($utilizador->isAdmin());
    }

    public function test_is_admin_retorna_false_quando_nivel_acesso_nao_admin(): void
    {
        $utilizador = new Utilizador(['nivel_acesso' => 'user']);

        $this->assertFalse($utilizador->isAdmin());
    }

    public function test_fillable_contem_campos_esperados(): void
    {
        $utilizador = new Utilizador();

        $fillable = $utilizador->getFillable();

        $this->assertContains('nome_pessoal', $fillable);
        $this->assertContains('nome_de_utilizador', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('senha', $fillable);
        $this->assertContains('nivel_acesso', $fillable);
        $this->assertContains('remember_token', $fillable);
        $this->assertContains('perfil_id', $fillable);
        $this->assertContains('empresa_id', $fillable);
        $this->assertContains('must_change_password', $fillable);
    }

    public function test_table_foi_definida_como_utilizadores(): void
    {
        $utilizador = new Utilizador();

        $this->assertEquals('utilizadores', $utilizador->getTable());
    }
}
