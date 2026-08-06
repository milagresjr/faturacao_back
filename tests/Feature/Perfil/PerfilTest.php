<?php

namespace Tests\Feature\Perfil;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaModuloPermissao();

        DB::table('modulo_permissoes')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produtos',
        ]);

        DB::table('permissoes')->insertOrIgnore([
            ['id' => 1, 'nome' => 'criar_produto', 'modulo_id' => 1],
            ['id' => 2, 'nome' => 'editar_produto', 'modulo_id' => 1],
            ['id' => 3, 'nome' => 'eliminar_produto', 'modulo_id' => 1],
            ['id' => 4, 'nome' => 'criar_documento', 'modulo_id' => 1],
            ['id' => 5, 'nome' => 'anular_documento', 'modulo_id' => 1],
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_perfil(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/perfis', [
                'nome' => 'Gestor',
                'descricao' => 'Perfil gestor',
                'empresa_id' => 1,
                'permissoes' => [1, 2, 3],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('perfis', [
            'nome' => 'Gestor',
            'empresa_id' => 1,
        ]);

        $perfilId = DB::table('perfis')->where('nome', 'Gestor')->value('id');
        $permissoes = DB::table('perfil_permissao')
            ->where('perfil_id', $perfilId)
            ->pluck('permissao_id')
            ->toArray();
        $this->assertCount(3, $permissoes);
    }

    public function test_pode_listar_perfis(): void
    {
        DB::table('perfis')->insert([
            'nome' => 'Vendedor',
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/perfis/list/empresa?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Vendedor']);
    }

    public function test_pode_atualizar_perfil_com_permissoes(): void
    {
        DB::table('perfis')->insert([
            'nome' => 'Perfil Original',
            'empresa_id' => 1,
            'estado' => 1,
        ]);
        $perfilId = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/perfis/{$perfilId}", [
                'nome' => 'Perfil Atualizado',
                'descricao' => 'Atualizado',
                'empresa_id' => 1,
                'estado' => 1,
                'permissoes' => [1, 2],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('perfis', [
            'id' => $perfilId,
            'nome' => 'Perfil Atualizado',
        ]);
    }

    public function test_pode_listar_permissoes(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/permissoes');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'criar_produto']);
    }
}
