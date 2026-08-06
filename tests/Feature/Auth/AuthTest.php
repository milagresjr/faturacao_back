<?php

namespace Tests\Feature\Auth;

use App\Models\Utilizador;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->criarTabelaEmpresas();
        $this->criarTabelaPerfis();
        $this->criarTabelaPermissoes();
        $this->criarTabelaPerfilPermissao();
        $this->criarTabelaUtilizadores();
        $this->criarTabelaPersonalAccessTokens();
        $this->criarTabelaPasswordResetsCustom();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Seed dados iniciais
        DB::table('empresas')->insert([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => '923456789',
            'morada' => 'Rua Teste, 123',
        ]);
        DB::table('perfis')->insert([
            'id' => 1,
            'nome' => 'Administrador',
            'estado' => 1,
            'empresa_id' => 1,
        ]);
    }

    private function criarUtilizador(array $extra = []): Utilizador
    {
        return Utilizador::create(array_merge([
            'nome_pessoal' => 'João Teste',
            'nome_de_utilizador' => 'joao.teste',
            'email' => 'joao@teste.com',
            'senha' => bcrypt('password123'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'perfil_id' => 1,
            'empresa_id' => 1,
            'must_change_password' => 0,
        ], $extra));
    }

    public function test_utilizador_pode_registar_se(): void
    {
        $response = $this->postJson('/api/register', [
            'nome_pessoal' => 'Maria Teste',
            'nome_de_utilizador' => 'maria.teste',
            'email' => 'maria@teste.com',
            'senha' => 'password123',
            'nivel_acesso' => 'user',
            'empresa_id' => 1,
            'perfil_id' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'User registered successfully']);
        $this->assertDatabaseHas('utilizadores', [
            'nome_de_utilizador' => 'maria.teste',
            'email' => 'maria@teste.com',
        ]);
    }

    public function test_utilizador_pode_fazer_login(): void
    {
        $this->criarUtilizador();

        $response = $this->postJson('/api/login', [
            'nome_de_utilizador' => 'joao.teste',
            'senha' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'utilizador' => ['id', 'nome_pessoal', 'nome_de_utilizador', 'email'],
            'token_type',
            'remember_me',
        ]);
        $response->assertJson([
            'message' => 'Login successful',
            'token_type' => 'Bearer',
        ]);
        
        // Token agora vem no cookie, não no body
        $this->assertNotEmpty($response->headers->get('Set-Cookie'));
    }

    public function test_login_falha_com_senha_errada(): void
    {
        $this->criarUtilizador();

        $response = $this->postJson('/api/login', [
            'nome_de_utilizador' => 'joao.teste',
            'senha' => 'senha_errada',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_utilizador_pode_fazer_logout(): void
    {
        $utilizador = $this->criarUtilizador();
        $token = $this->autenticarUtilizador($utilizador);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out successfully']);
    }

    private function autenticarUtilizador(Utilizador $utilizador): string
    {
        // Para testes, criamos o token diretamente (simulando login)
        $token = $utilizador->createToken('auth_token')->plainTextToken;
        return $token;
    }

    public function test_middleware_force_password_change_bloqueia_acesso(): void
    {
        $utilizador = $this->criarUtilizador(['must_change_password' => 1]);
        $token = $this->autenticarUtilizador($utilizador);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/produtos');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'É necessário alterar a senha',
            'redirect' => 'password-change',
        ]);
    }

    public function test_utilizador_pode_alterar_senha(): void
    {
        $utilizador = $this->criarUtilizador();
        $token = $this->autenticarUtilizador($utilizador);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/utilizadores/change/password', [
            'senha' => 'nova_senha_123',
            'senha_confirmation' => 'nova_senha_123',
        ]);

        $response->assertStatus(200);
    }
}
