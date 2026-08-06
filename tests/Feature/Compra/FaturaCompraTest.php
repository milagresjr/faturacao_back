<?php

namespace Tests\Feature\Compra;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class FaturaCompraTest extends TestCase
{
    use DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaFornecedores();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaDocumentosCompra();
        $this->criarTabelaItensDocumentoCompra();
        $this->criarTabelaOtherItensDocumentoCompra();
        $this->criarTabelaImpostosDocumentoCompra();
        $this->criarTabelaPagamentosDocumentoCompra();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaLotesProduto();
        $this->criarTabelaConfiguracoesFatura();
        $this->criarTabelaFiliais();
        $this->criarTabelaCaixas();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => 923456789,
            'morada' => 'Rua Teste, 123',
        ]);

        DB::table('fornecedores')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Fornecedor Compra',
            'nif' => '111222333',
            'email' => 'compra@fornecedor.com',
            'telefone' => '923111222',
            'empresa_id' => 1,
        ]);

        DB::table('armazens')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Armazém Compras',
            'empresa_id' => 1,
        ]);

        DB::table('produtos')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produto Compra',
            'preco_venda' => 100.00,
            'preco_custo' => 60.00,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'empresa_id' => 1,
            'estado' => 1,
            'stock_atual' => 0,
        ]);

        DB::table('tipos_taxa_iva')->insertOrIgnore([
            'id' => 1,
            'codigo' => 'IVA14',
            'descricao' => 'IVA 14%',
            'taxa' => 14.00,
            'empresa_id' => 1,
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_pode_criar_fatura_compra(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fatura-compra', [
                'empresa_id' => 1,
                'tipo_fatura' => 'Fatura de Compra',
                'sigla_fatura' => 'FC',
                'fornecedor_id' => 1,
                'fornecedor_nome' => 'Fornecedor Compra',
                'fornecedor_nif' => '111222333',
                'armazem_id' => 1,
                'data_fatura' => now()->format('Y-m-d'),
                'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
                'num_fatura' => 'FC-001',
                'utilizador_id' => 1,
                'utilizador' => 'admin.teste',
                'itens' => [
                    [
                        'produto_id' => 1,
                        'produto_nome' => 'Produto Compra',
                        'quantidade' => 10,
                        'preco_custo' => 60.00,
                        'iva_percent' => 14.00,
                        'codigo_produto' => null,
                        'desconto_percent' => 0,
                        'desconto_fixo' => 0,
                        'lote_id' => null,
                        'lote' => null,
                        'codigo_lote' => null,
                        'lote_codigo_barras' => null,
                        'lote_data_validade' => null,
                    ],
                ],
                'other_itens' => [],
                'total_geral' => 684.00,
                'paga' => true,
                'valor_pago' => 684.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'documento' => ['id', 'itens'],
        ]);
        $this->assertDatabaseHas('documentos_compra', [
            'fornecedor_nome' => 'Fornecedor Compra',
            'total_geral' => 684.00,
        ]);
    }

    public function test_validacao_campos_obrigatorios(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fatura-compra', [
                'empresa_id' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'fornecedor_nome',
            'fornecedor_nif',
            'data_fatura',
            'data_vencimento',
            'itens',
        ]);
    }

    public function test_validacao_itens_vazio(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/fatura-compra', [
                'empresa_id' => 1,
                'fornecedor_nome' => 'Fornecedor Compra',
                'fornecedor_nif' => '111222333',
                'data_fatura' => now()->format('Y-m-d'),
                'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
                'itens' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['itens']);
    }

    public function test_pode_listar_faturas_compra(): void
    {
        $this->test_pode_criar_fatura_compra();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/fatura-compra?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
        ]);
    }

    public function test_pode_ver_fatura_compra_individual(): void
    {
        $this->test_pode_criar_fatura_compra();
        $doc = DB::table('documentos_compra')->first();

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/fatura-compra/{$doc->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $doc->id]);
    }
}
