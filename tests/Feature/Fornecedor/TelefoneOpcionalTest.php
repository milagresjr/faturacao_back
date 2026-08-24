<?php

namespace Tests\Feature\Fornecedor;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class TelefoneOpcionalTest extends TestCase
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

        // Limpa resíduos de execuções anteriores para garantir determinismo
        DB::table('fornecedores')->whereIn('email', ['semtele@teste.com', 'teleazio@teste.com', 'removetele@teste.com'])->delete();

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_criar_fornecedor_sem_telefone(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fornecedores', [
                'nome' => 'Fornecedor Sem Telefone',
                'email' => 'semtele@teste.com',
                'endereco' => 'Rua Sem Telefone, 1',
                'nif' => '888888880',
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertNull(DB::table('fornecedores')->where('nif', '888888880')->value('telefone'));
    }

    public function test_criar_fornecedor_com_telefone_vazio(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fornecedores', [
                'nome' => 'Fornecedor Telefone Vazio',
                'telefone' => '',
                'email' => 'teleazio@teste.com',
                'endereco' => 'Rua Telefone Vazio, 2',
                'nif' => '888888881',
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ]);

        $response->assertStatus(201);
        // O middleware ConvertEmptyStringsToNull converte "" para null
        $this->assertNull(DB::table('fornecedores')->where('nif', '888888881')->value('telefone'));
    }

    public function test_atualizar_fornecedor_removendo_telefone(): void
    {
        $criado = $this->withHeaders($this->headers())
            ->postJson('/api/fornecedores', [
                'nome' => 'Fornecedor Remover Telefone',
                'telefone' => '923777777',
                'email' => 'removetele@teste.com',
                'endereco' => 'Rua Remover, 3',
                'nif' => '888888882',
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ])->assertStatus(201);

        $id = $criado->json('id');

        $this->withHeaders($this->headers())
            ->putJson("/api/fornecedores/{$id}", [
                'nome' => 'Fornecedor Remover Telefone',
                'telefone' => '',
                'email' => 'removetele@teste.com',
                'endereco' => 'Rua Remover, 3',
                'nif' => '888888882',
                'estado' => 1,
                'empresa_id' => 1,
                'utilizador_id' => 1,
            ])->assertStatus(200);

        $this->assertNull(DB::table('fornecedores')->where('id', $id)->value('telefone'));
    }
}
