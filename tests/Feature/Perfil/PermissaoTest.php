<?php

namespace Tests\Feature\Perfil;

use App\Models\Utilizador;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class PermissaoTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelaEmpresas();
        $this->criarTabelaPerfis();
        $this->criarTabelaPermissoes();
        $this->criarTabelaPerfilPermissao();
        $this->criarTabelaUtilizadores();
        $this->criarTabelaPersonalAccessTokens();
        $this->criarTabelaModuloPermissao();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => 923456789,
            'morada' => 'Rua Teste, 123',
        ]);
    }

    public function test_utilizador_tem_permissao_quando_perfil_contem_permissao(): void
    {
        // Arrange: criar perfil com permissoes
        $perfil = \App\Models\Perfil::create([
            'nome' => 'Admin',
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        $permissao = \App\Models\Permissao::create(['nome' => 'criar_documento']);
        $perfil->permissoes()->attach($permissao->id);

        // Criar utilizador com este perfil
        $utilizador = Utilizador::create([
            'nome_pessoal' => 'João Permissao',
            'nome_de_utilizador' => 'joao.perm',
            'email' => 'joao.perm@teste.com',
            'senha' => bcrypt('password'),
            'nivel_acesso' => 'user',
            'estado' => 1,
            'perfil_id' => $perfil->id,
            'empresa_id' => 1,
        ]);

        // Assert: verificar permissao
        $this->assertTrue($utilizador->temPermissao('criar_documento'));
        $this->assertFalse($utilizador->temPermissao('eliminar_empresa'));
    }

    public function test_is_admin_retorna_true_para_admin(): void
    {
        // Arrange
        $admin = Utilizador::create([
            'nome_pessoal' => 'Admin',
            'nome_de_utilizador' => 'admin.sistema',
            'email' => 'admin.sistema@teste.com',
            'senha' => bcrypt('password'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'empresa_id' => 1,
        ]);

        $user = Utilizador::create([
            'nome_pessoal' => 'User',
            'nome_de_utilizador' => 'user.normal',
            'email' => 'user.normal@teste.com',
            'senha' => bcrypt('password'),
            'nivel_acesso' => 'user',
            'estado' => 1,
            'empresa_id' => 1,
        ]);

        // Assert
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
    }
}
