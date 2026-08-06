<?php

namespace Tests\Feature\Stock;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class MovimentoStockTest extends TestCase
{
    use DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaDocumentos();
        $this->criarTabelaItensDocumento();
        $this->criarTabelaSeries();
        $this->criarTabelaClientes();
        $this->criarTabelaInfoGuia();
        $this->criarTabelaDocumentoRelacoes();
        $this->criarTabelaMeiosPagamentoDocumento();
        $this->criarTabelaImpostosDocumento();
        $this->criarTabelaBancos();
        $this->criarTabelaContas();
        $this->criarTabelaLotesProduto();
        $this->criarTabelaDocumentosInterno();
        $this->criarTabelaItensDocumentoInterno();
        $this->criarTabelaDocumentosCompra();
        $this->criarTabelaItensDocumentoCompra();
        $this->criarTabelaImpostosDocumentoCompra();
        $this->criarTabelaFiliais();
        $this->criarTabelaCaixas();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaUnidades();
        $this->criarTabelaConfiguracoesFatura();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => 923456789,
            'morada' => 'Rua Teste, 123',
        ]);

        DB::table('armazens')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Armazém Principal',
            'empresa_id' => 1,
            'predefinido' => 1,
        ]);
        DB::table('armazens')->insertOrIgnore([
            'id' => 2,
            'nome' => 'Armazém Secundário',
            'empresa_id' => 1,
        ]);

        DB::table('produtos')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produto Stock Teste',
            'preco_venda' => 50.00,
            'preco_custo' => 25.00,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'empresa_id' => 1,
            'estado' => 1,
            'stock_atual' => 10,
        ]);
        DB::table('produtos')->insertOrIgnore([
            'id' => 2,
            'nome' => 'Produto Com Validade',
            'preco_venda' => 80.00,
            'preco_custo' => 40.00,
            'movimenta_stock' => 1,
            'controla_validade' => 1,
            'empresa_id' => 1,
            'estado' => 1,
            'stock_atual' => 50,
        ]);

        DB::table('stocks')->insertOrIgnore([
            'id' => 1,
            'produto_id' => 1,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 10,
            'stock_min' => 2,
        ]);

        DB::table('lotes_produto')->insertOrIgnore([
            'id' => 1,
            'produto_id' => 2,
            'armazem_id' => 1,
            'codigo_lote' => 'LOTE-001',
            'data_validade' => Carbon::today()->addMonths(6),
            'qtd_atual' => 50,
            'quantidade_actual' => 50,
            'qtd_inicial' => 50,
            'status' => 'activo',
        ]);

        DB::table('tipos_taxa_iva')->insertOrIgnore([
            'id' => 1,
            'codigo' => 'IVA14',
            'descricao' => 'IVA 14%',
            'taxa' => 14.00,
            'empresa_id' => 1,
        ]);

        DB::table('series')->insertOrIgnore([
            'id' => 1,
            'nome' => 'FT A',
            'tipo_documento' => 'FT',
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_registar_entrada_stock_manual(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/movimento-stock', [
                'empresa_id' => 1,
                'armazem_id' => 1,
                'tipo_movimento' => 'entrada',
                'itens' => [
                    [
                        'produto_id' => 1,
                        'quantidade' => 5,
                        'observacao' => 'Entrada manual de teste',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Movimentação registrada com sucesso',
        ]);

        $this->assertDatabaseHas('movimentos_stock', [
            'produto_id' => 1,
            'quantidade' => 5,
            'operacao' => 'entrada',
        ]);

        $stock = DB::table('stocks')
            ->where('produto_id', 1)
            ->where('armazem_id', 1)
            ->first();
        $this->assertEquals(15, $stock->stock_atual);
    }

    public function test_pode_registar_saida_stock_manual(): void
    {
        DB::table('stocks')
            ->where('produto_id', 1)
            ->where('armazem_id', 1)
            ->update(['stock_atual' => 20]);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/movimento-stock', [
                'empresa_id' => 1,
                'armazem_id' => 1,
                'tipo_movimento' => 'saida',
                'itens' => [
                    [
                        'produto_id' => 1,
                        'quantidade' => 3,
                        'observacao' => 'Saida manual de teste',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Movimentação registrada com sucesso',
        ]);

        $this->assertDatabaseHas('movimentos_stock', [
            'produto_id' => 1,
            'quantidade' => 3,
            'operacao' => 'saida',
        ]);
    }

    public function test_validacao_tipo_movimento_invalido(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/movimento-stock', [
                'empresa_id' => 1,
                'armazem_id' => 1,
                'tipo_movimento' => 'invalido',
                'itens' => [
                    [
                        'produto_id' => 1,
                        'quantidade' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tipo_movimento']);
    }

    public function test_pode_alterar_stock_minimo(): void
    {
        $response = $this->withHeaders($this->headers())
            ->patchJson('/api/alterar-stock-minimo/1/1', [
                'stock_min' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Stock mínimo atualizado com sucesso']);
        $this->assertDatabaseHas('stocks', [
            'id' => 1,
            'stock_min' => 5,
        ]);
    }

    public function test_pode_listar_movimentos_stock(): void
    {
        $this->test_pode_registar_entrada_stock_manual();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/movimento-stock');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
        ]);
    }

    public function test_pode_listar_movimentos_com_filtro_empresa(): void
    {
        $this->test_pode_registar_entrada_stock_manual();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/movimento-stock?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
        ]);
    }
}
