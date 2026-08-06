<?php

namespace Tests\Feature\Serie;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class SerieTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
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

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_serie(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/series', [
                'nome' => 'FT 2026',
                'ano' => '2026',
                'prefixo' => 'FT',
                'tipo_documento' => 'factura',
                'empresa_id' => 1,
                'ativo' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('series', [
            'nome' => 'FT 2026',
            'tipo_documento' => 'factura',
            'empresa_id' => 1,
        ]);
    }

    public function test_pode_listar_series(): void
    {
        DB::table('series')->insert([
            'nome' => 'FT A',
            'ano' => '2026',
            'prefixo' => 'FT',
            'tipo_documento' => 'factura',
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/series?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'FT A']);
    }

    public function test_pode_atualizar_serie(): void
    {
        DB::table('series')->insert([
            'nome' => 'FT Original',
            'ano' => '2026',
            'prefixo' => 'FT',
            'tipo_documento' => 'factura',
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/series/{$id}", [
                'nome' => 'FT Alterada',
                'ano' => '2026',
                'prefixo' => 'FT',
                'tipo_documento' => 'factura',
                'empresa_id' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('series', [
            'id' => $id,
            'nome' => 'FT Alterada',
        ]);
    }

    public function test_pode_definir_serie_como_padrao(): void
    {
        DB::table('series')->insert([
            'nome' => 'FT Antiga', 'ano' => '2026', 'prefixo' => 'FT', 'tipo_documento' => 'factura', 'empresa_id' => 1, 'padrao' => 1, 'ativo' => 1,
        ]);
        $serie1Id = DB::getPdo()->lastInsertId();
        DB::table('series')->insert([
            'nome' => 'FT Nova', 'ano' => '2026', 'prefixo' => 'FT', 'tipo_documento' => 'factura', 'empresa_id' => 1, 'padrao' => 0, 'ativo' => 1,
        ]);
        $serie2Id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/series/{$serie2Id}/definir-padrao");

        $response->assertStatus(200);
        $this->assertDatabaseHas('series', ['id' => $serie2Id, 'padrao' => 1]);
        $this->assertDatabaseHas('series', ['id' => $serie1Id, 'padrao' => 0]);
    }

    public function test_pode_alternar_serie_ativa(): void
    {
        DB::table('series')->insert([
            'nome' => 'FT Ativa',
            'ano' => '2026',
            'prefixo' => 'FT',
            'tipo_documento' => 'factura',
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/series/{$id}/definir-ativo");

        $response->assertStatus(200);
        $this->assertDatabaseHas('series', ['id' => $id, 'ativo' => 0]);
    }

    public function test_pode_listar_series_por_tipo_documento(): void
    {
        DB::table('series')->insert(['nome' => 'FT A', 'ano' => '2026', 'prefixo' => 'FT', 'tipo_documento' => 'factura', 'empresa_id' => 1, 'padrao' => 1, 'ativo' => 1]);
        DB::table('series')->insert(['nome' => 'NC A', 'ano' => '2026', 'prefixo' => 'NC', 'tipo_documento' => 'nota_credito', 'empresa_id' => 1, 'padrao' => 1, 'ativo' => 1]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/tipo-documento/series?empresa_id=1&tipo_documento=factura');

        $response->assertStatus(200);
    }
}
