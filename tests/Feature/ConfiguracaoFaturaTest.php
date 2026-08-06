<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class ConfiguracaoFaturaTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaConfiguracoesFatura();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => 923456789,
            'morada' => 'Rua Teste, 123',
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_configuracao_fatura(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/configuracoes-fatura', [
                'empresa_id' => 1,
                'template' => 'modern',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('configuracoes_fatura', [
            'empresa_id' => 1,
            'template' => 'modern',
        ]);
    }

    public function test_validacao_template_invalido(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/configuracoes-fatura', [
                'empresa_id' => 1,
                'template' => 'invalido',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['template']);
    }

    public function test_pode_ver_configuracao_fatura(): void
    {
        DB::table('configuracoes_fatura')->insert([
            'empresa_id' => 1,
            'mostrar_logo' => 1,
            'mostrar_nif' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/configuracoes-fatura/1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['empresa_id' => 1]);
    }

    public function test_retorna_404_quando_configuracao_nao_existe(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/configuracoes-fatura/999');

        $response->assertStatus(404);
    }

    public function test_pode_atualizar_configuracao_fatura(): void
    {
        $configId = DB::table('configuracoes_fatura')->insertGetId([
            'empresa_id' => 1,
            'mostrar_logo' => 1,
            'mostrar_nif' => 1,
            'num_via' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/configuracoes-fatura/1", [
                'mostrar_logo' => false,
                'mostrar_nif' => false,
                'num_via' => 3,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('configuracoes_fatura', [
            'id' => $configId,
            'mostrar_logo' => 0,
            'mostrar_nif' => 0,
            'num_via' => 3,
        ]);
    }

    public function test_pode_atualizar_template(): void
    {
        DB::table('configuracoes_fatura')->insert([
            'empresa_id' => 1,
            'template' => 'classic',
        ]);

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/configuracoes-fatura/1", [
                'template' => 'minimal',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('configuracoes_fatura', [
            'empresa_id' => 1,
            'template' => 'minimal',
        ]);
    }
}
