<?php

namespace Tests\Feature\Cliente;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaTipoCliente();
        $this->criarTabelaClientes();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => '923456789',
            'morada' => 'Rua Teste, 123',
        ]);

        DB::table('tipo_clientes')->insertOrIgnore([
            'id' => 1,
            'descricao' => 'Cliente Normal',
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_cliente(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/clientes', [
                'nome' => 'Cliente Novo',
                'nif' => '123123123',
                'email' => 'cliente.novo@teste.com',
                'telefone' => '923000000',
                'endereco' => 'Rua Nova, 789',
                'tipo_cliente_id' => 1,
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(201);
    }

    public function test_pode_listar_clientes(): void
    {
        DB::table('clientes')->insert([
            'nome' => 'Cliente Lista',
            'nif' => '111111111',
            'email' => 'lista@teste.com',
            'telefone' => '923111111',
            'endereco' => 'Rua Lista, 123',
            'tipo_cliente_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/clientes?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Cliente Lista']);
    }

    public function test_pode_ver_cliente_individual(): void
    {
        DB::table('clientes')->insert([
            'nome' => 'Cliente Ver',
            'nif' => '222222222',
            'email' => 'ver@teste.com',
            'telefone' => '923222222',
            'endereco' => 'Rua Ver, 456',
            'tipo_cliente_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/clientes/{$id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Cliente Ver']);
    }

    public function test_pode_atualizar_cliente(): void
    {
        DB::table('clientes')->insert([
            'nome' => 'Cliente Update',
            'nif' => '333333333',
            'email' => 'update@teste.com',
            'telefone' => '923333333',
            'endereco' => 'Rua Update, 789',
            'tipo_cliente_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/clientes/{$id}", [
                'nome' => 'Cliente Atualizado',
                'nif' => '333333334',
                'email' => 'update.novo@teste.com',
                'telefone' => '923333334',
                'tipo_cliente_id' => 1,
                'utilizador_id' => 1,
                'estado' => 1,
                'empresa_id' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('clientes', ['id' => $id, 'nome' => 'Cliente Atualizado']);
    }

    public function test_criar_cliente_com_email_duplicado_mesma_empresa_falha(): void
    {
        DB::table('clientes')->insert([
            'nome' => 'Cliente Original',
            'nif' => '444444444',
            'email' => 'duplicado@teste.com',
            'telefone' => '923444444',
            'endereco' => 'Rua Original, 123',
            'tipo_cliente_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/clientes', [
                'nome' => 'Cliente Duplicado',
                'nif' => '555555555',
                'email' => 'duplicado@teste.com',
                'telefone' => '923555555',
                'endereco' => 'Rua Duplicada, 456',
                'tipo_cliente_id' => 1,
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(422);
    }
}
