<?php

namespace Tests\Feature\Fornecedor;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class FornecedorTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();

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

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_fornecedor(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fornecedores', [
                'nome' => 'Fornecedor Novo',
                'telefone' => '923000000',
                'email' => 'fornecedor@teste.com',
                'endereco' => 'Rua Fornecedor, 123',
                'nif' => '999999990',
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(201);
    }

    public function test_pode_listar_fornecedores(): void
    {
        DB::table('fornecedores')->insert([
            'nome' => 'Fornecedor Lista',
            'telefone' => '923111111',
            'email' => 'lista@fornecedor.com',
            'endereco' => 'Rua Lista, 123',
            'nif' => '999999991',
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/fornecedores?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Fornecedor Lista']);
    }

    public function test_pode_atualizar_fornecedor(): void
    {
        DB::table('fornecedores')->insert([
            'nome' => 'Fornecedor Update',
            'telefone' => '923333333',
            'email' => 'update@fornecedor.com',
            'endereco' => 'Rua Update, 789',
            'nif' => '999999993',
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/fornecedores/{$id}", [
                'nome' => 'Fornecedor Atualizado',
                'telefone' => '923333333',
                'nif' => '999999993',
                'empresa_id' => 1,
                'utilizador_id' => 1,
                'estado' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('fornecedores', ['id' => $id, 'nome' => 'Fornecedor Atualizado']);
    }

    public function test_criar_fornecedor_com_nif_duplicado_falha(): void
    {
        DB::table('fornecedores')->insert([
            'nome' => 'Fornecedor Original',
            'telefone' => '923444444',
            'email' => 'original@fornecedor.com',
            'endereco' => 'Rua Original, 123',
            'nif' => '999999994',
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fornecedores', [
                'nome' => 'Fornecedor Duplicado',
                'telefone' => '923555555',
                'nif' => '999999994',
                'endereco' => 'Rua Duplicada, 456',
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(422);
    }
}
