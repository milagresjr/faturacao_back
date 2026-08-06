<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class CrudBasicosTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaMarcas();
        $this->criarTabelaCategoriaProduto();
        $this->criarTabelaSubCategorias();
        $this->criarTabelaTipoProduto();
        $this->criarTabelaTipoStock();
        $this->criarTabelaUnidades();
        $this->criarTabelaArmazens();
        $this->criarTabelaFiliais();
        $this->criarTabelaCaixas();
        $this->criarTabelaBancos();
        $this->criarTabelaContas();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaTipoCliente();
        $this->criarTabelaMotivoIsencao();

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

        $token = 'test-auth-token-' . uniqid();

        DB::table('utilizadores')->insert([
            'id' => 1,
            'nome_pessoal' => 'Admin Teste',
            'nome_de_utilizador' => 'admin.teste',
            'email' => 'admin@teste.com',
            'senha' => bcrypt('password123'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'perfil_id' => 1,
            'empresa_id' => 1,
            'must_change_password' => 0,
            'remember_token' => $token,
        ]);

        $this->token = $token;
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_crud_marcas(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/marcas', [
            'nome' => 'Marca Teste',
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'descricao' => 'Descricao da marca',
            'estado' => true,
        ]);
        $response->assertStatus(201);
        $id = $response->json('id');

        $response = $this->withHeaders($this->headers())->getJson('/api/marcas?empresa_id=1');
        $response->assertStatus(200);

        $response = $this->withHeaders($this->headers())->putJson("/api/marcas/{$id}", [
            'nome' => 'Marca Atualizada',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('marcas', ['id' => $id, 'nome' => 'Marca Atualizada']);
    }

    public function test_crud_categorias(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/categoria-produtos', [
            'nome' => 'Categoria Teste',
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'descricao' => 'Descricao da categoria',
            'estado' => true,
        ]);
        $response->assertStatus(201);

        $response = $this->withHeaders($this->headers())->getJson('/api/categoria-produtos?empresa_id=1');
        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'CATEGORIA TESTE']);
    }

    public function test_crud_armazens(): void
    {
        DB::table('filiais')->insert([
            'id' => 1,
            'nome' => 'Filial Principal',
            'telefone' => '923456789',
            'endereco' => 'Endereco filial',
            'nif' => '987654321',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/armazens', [
            'nome' => 'Armazém Teste',
            'filial_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'endereco' => 'Endereco do armazem',
        ]);
        $response->assertStatus(201);

        $response = $this->withHeaders($this->headers())->getJson('/api/armazens?empresa_id=1');
        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Armazém Teste']);
    }

    public function test_crud_unidades(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/unidades', [
            'descricao' => 'Quilograma',
            'sigla' => 'kg',
            'casas_decimais' => 2,
            'predefinida' => 0,
        ]);
        $response->assertStatus(201);
        $id = $response->json('id');

        $response = $this->withHeaders($this->headers())->patchJson("/api/unidades/{$id}/definir-predefinida");
        $response->assertStatus(200);
    }

    public function test_crud_bancos(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/bancos', [
            'nome' => 'Banco Teste',
            'codigo' => '001',
            'descricao' => 'Banco de testes',
            'estado' => true,
        ]);
        $response->assertStatus(201);
    }

    public function test_crud_tipo_taxa_iva(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/tipos-taxa-iva', [
            'codigo' => 'RED',
            'descricao' => 'IVA Reduzido',
            'taxa' => 5.00,
        ]);
        $response->assertStatus(201);

        $response = $this->withHeaders($this->headers())->getJson('/api/tipos-taxa-iva');
        $response->assertStatus(200);
        $response->assertJsonFragment(['codigo' => 'RED']);
    }

    public function test_crud_tipo_produto(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/tipo-produtos', [
            'nome' => 'Produto Acabado',
            'descricao' => 'Tipo para produtos acabados',
        ]);
        $response->assertStatus(201);
    }

    public function test_crud_tipo_stock(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/tipo-stock', [
            'tipo' => 'Stock Normal',
            'sigla' => 'SN',
        ]);
        $response->assertStatus(201);
    }

    public function test_crud_motivo_isencao(): void
    {
        $response = $this->withHeaders($this->headers())->getJson('/api/motivo-isencao');
        $response->assertStatus(200);
    }

    public function test_crud_filiais(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/filiais', [
            'nome' => 'Filial Norte',
            'telefone' => '923456789',
            'endereco' => 'Rua da Filial, 123',
            'nif' => '987654321',
            'email' => 'filial@teste.com',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);
        $response->assertStatus(201);
    }

    public function test_crud_caixas(): void
    {
        DB::table('filiais')->insert([
            'id' => 1,
            'nome' => 'Filial Caixa',
            'telefone' => '923456789',
            'endereco' => 'Endereco',
            'nif' => '987654321',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        DB::table('armazens')->insert([
            'id' => 1,
            'nome' => 'Armazém Caixa',
            'filial_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/caixas', [
            'nome' => 'Caixa 1',
            'armazem_id' => 1,
            'usuario_id' => 1,
            'tipo' => 'fisico',
            'estado' => 'aberto',
        ]);
        $response->assertStatus(201);

        $response = $this->withHeaders($this->headers())->getJson('/api/caixas/armazem/1');
        $response->assertStatus(200);
    }

    public function test_crud_tipo_clientes(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/tipo-clientes', [
            'descricao' => 'Cliente VIP',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);
        $response->assertStatus(201);
    }

    public function test_crud_fornecedores(): void
    {
        $nif = '123123' . random_int(100, 999);

        $response = $this->withHeaders($this->headers())->postJson('/api/fornecedores', [
            'nome' => 'Fornecedor Teste',
            'telefone' => '923456789',
            'nif' => $nif,
            'endereco' => 'Rua do Fornecedor, 456',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);
        $response->assertStatus(201);
    }
}
