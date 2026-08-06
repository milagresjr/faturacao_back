<?php

namespace Tests\Feature\Empresa;

use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class EmpresaTest extends TestCase
{
    use DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaConfiguracoesFatura();
        $this->criarTabelaMotivoIsencao();
        $this->criarTabelaTipoStock();
        $this->criarTabelaFiliais();
        $this->criarTabelaArmazens();
        $this->criarTabelaCaixas();
        $this->criarTabelaSeries();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => '923456789',
            'morada' => 'Rua Teste, 123',
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    public function test_pode_criar_empresa_via_api(): void
    {
        $response = $this->postJson('/api/empresas', [
            'nome' => 'Nova Empresa SA',
            'email' => 'nova_' . uniqid() . '@empresa.com',
            'nif' => '999999999',
            'telefone' => '912345678',
            'regime_tributario' => 'regime_geral',
            'senha' => 'password123',
            'senha_confirmation' => 'password123',
            'nome_de_utilizador' => 'user_' . uniqid(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('empresas', [
            'email' => $response->json('email'),
        ]);
    }

    public function test_pode_listar_empresas(): void
    {
        DB::table('empresas')->insert([
            'id' => 2,
            'nome' => 'Empresa Original',
            'email' => 'original@empresa.com',
            'nif' => '111111111',
            'telefone' => '911111111',
            'morada' => 'Rua Original, 123',
        ]);

        $response = $this->withHeaders($this->headersComToken($this->token))
            ->getJson('/api/empresas');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Empresa Original']);
    }

    public function test_pode_atualizar_empresa(): void
    {
        $response = $this->withHeaders($this->headersComToken($this->token))
            ->patchJson('/api/empresas/1', [
                'nome' => 'Empresa Atualizada',
                'telefone' => '923456789',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('empresas', [
            'id' => 1,
            'nome' => 'Empresa Atualizada',
        ]);
    }
}
