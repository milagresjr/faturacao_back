<?php

namespace Tests\Feature\Produto;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class ProdutoTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaCategoriaProduto();
        $this->criarTabelaSubCategorias();
        $this->criarTabelaMarcas();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaUnidades();
        $this->criarTabelaMotivoIsencao();
        $this->criarTabelaTipoProduto();
        $this->criarTabelaTipoStock();
        $this->criarTabelaFornecedores();
        $this->criarTabelaFiliais();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaLotesProduto();
        $this->criarTabelaItensDocumento();

        $this->token = $this->autenticarComoAdmin();

        DB::table('tipo_produtos')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produto Acabado',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        DB::table('filiais')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Filial Principal',
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        DB::table('armazens')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Armazém Principal',
            'filial_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
        ]);

        DB::table('tipos_taxa_iva')->insertOrIgnore([
            'id' => 1,
            'codigo' => 'NOR',
            'descricao' => 'IVA Normal 14%',
            'taxa' => 14.00,
            'empresa_id' => 1,
        ]);
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    private function produtoPayload(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Produto Teste',
            'preco_custo' => 25.00,
            'preco_venda' => 75.00,
            'preco_final' => 75.00,
            'margem_lucro' => 200,
            'valor_iva' => 10.50,
            'tipo_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'movimenta_stock' => true,
        ], $extra);
    }

    public function test_pode_criar_produto(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/produtos', $this->produtoPayload(['nome' => 'Produto Novo Teste']));

        $response->assertStatus(201);
    }

    public function test_pode_listar_produtos(): void
    {
        DB::table('produtos')->insert([
            'nome' => 'Produto Listagem',
            'preco_venda' => 50.00,
            'preco_custo' => 20.00,
            'preco_final' => 50.00,
            'margem_lucro' => 150,
            'valor_iva' => 7.00,
            'tipo_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'estado' => 1,
        ]);

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/produtos?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Produto Listagem']);
    }

    public function test_pode_ver_detalhes_produto(): void
    {
        DB::table('produtos')->insert([
            'nome' => 'Produto Detalhe',
            'preco_venda' => 100.00,
            'preco_custo' => 40.00,
            'preco_final' => 100.00,
            'margem_lucro' => 150,
            'valor_iva' => 14.00,
            'tipo_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/produtos/{$id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['nome' => 'Produto Detalhe']);
    }

    public function test_pode_alternar_estado_produto(): void
    {
        DB::table('produtos')->insert([
            'nome' => 'Produto Estado',
            'preco_venda' => 60.00,
            'preco_custo' => 20.00,
            'preco_final' => 60.00,
            'margem_lucro' => 200,
            'valor_iva' => 8.40,
            'tipo_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/produtos/{$id}/change-estado");

        $response->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $id, 'estado' => 0]);
    }

    public function test_pode_atualizar_produto(): void
    {
        DB::table('produtos')->insert([
            'nome' => 'Produto Original',
            'preco_venda' => 80.00,
            'preco_custo' => 30.00,
            'preco_final' => 80.00,
            'margem_lucro' => 166,
            'valor_iva' => 11.20,
            'tipo_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'estado' => 1,
        ]);
        $id = DB::getPdo()->lastInsertId();

        $response = $this->withHeaders($this->headers())
            ->putJson("/api/produtos/{$id}", [
                'nome' => 'Produto Alterado',
                'preco_venda' => 90.00,
                'preco_custo' => 35.00,
                'preco_final' => 90.00,
                'margem_lucro' => 157,
                'valor_iva' => 12.60,
                'tipo_id' => 1,
                'empresa_id' => 1,
                'utilizador_id' => 1,
                'movimenta_stock' => 1,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('produtos', ['id' => $id, 'nome' => 'Produto Alterado']);
    }
}
