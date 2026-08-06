<?php

namespace Tests\Feature\Stock;

use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\LoteProduto;
use App\Models\Produto;
use App\Models\Stock;
use App\Services\DocumentoCompraService;
use App\Services\DocumentoService;
use App\Services\StockService;
use App\Services\ValidadeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class StockComValidadeTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaLotesProduto();
        $this->criarTabelaDocumentos();
        $this->criarTabelaItensDocumento();
        $this->criarTabelaSeries();
        $this->criarTabelaInfoGuia();
        $this->criarTabelaDocumentoRelacoes();
        $this->criarTabelaMeiosPagamentoDocumento();
        $this->criarTabelaImpostosDocumento();
        $this->criarTabelaBancos();
        $this->criarTabelaContas();
        $this->criarTabelaClientes();
        $this->criarTabelaDocumentosCompra();
        $this->criarTabelaItensDocumentoCompra();
        $this->criarTabelaImpostosDocumentoCompra();
        $this->criarTabelaConfiguracoesFatura();
        $this->criarTabelaNotificacoesValidade();
        $this->criarTabelaConfigValidacaoProduto();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaUnidades();

        // Auth user
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

        DB::table('permissoes')->insert([
            'id' => 1,
            'nome' => 'all',
        ]);

        DB::table('perfil_permissao')->insert([
            'perfil_id' => 1,
            'permissao_id' => 1,
        ]);

        DB::table('utilizadores')->insert([
            'id' => 1,
            'nome_pessoal' => 'Admin Teste',
            'nome_de_utilizador' => 'admin',
            'email' => 'admin@stock.com',
            'senha' => bcrypt('password'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'perfil_id' => 1,
            'empresa_id' => 1,
            'must_change_password' => 0,
        ]);

        // Base data
        DB::table('armazens')->insert([
            'id' => 1,
            'nome' => 'Armazém Principal',
            'empresa_id' => 1,
        ]);

        DB::table('tipos_taxa_iva')->insert([
            'id' => 1,
            'codigo' => 'NOR',
            'descricao' => 'IVA 14%',
            'taxa' => 14.00,
            'empresa_id' => 1,
        ]);

        DB::table('series')->insert([
            'id' => 1,
            'nome' => 'FT A',
            'prefixo' => 'FT',
            'ano' => '2026',
            'tipo_documento' => 'FT',
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);

        // Produto com validade
        DB::table('produtos')->insert([
            'id' => 1,
            'nome' => 'Produto Validade Teste',
            'preco_venda' => 100.00,
            'preco_custo' => 50.00,
            'movimenta_stock' => 1,
            'controla_validade' => 1,
            'empresa_id' => 1,
            'estado' => 1,
            'stock_atual' => 100,
            'stock' => 0,
        ]);

        // Produto sem validade
        DB::table('produtos')->insert([
            'id' => 2,
            'nome' => 'Produto Simples',
            'preco_venda' => 30.00,
            'preco_custo' => 15.00,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'empresa_id' => 1,
            'estado' => 1,
            'stock_atual' => 50,
            'stock' => 0,
        ]);

        // Stock para produto sem validade
        DB::table('stocks')->insert([
            'id' => 1,
            'produto_id' => 2,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 50,
            'stock_min' => 5,
        ]);

        // Lotes para produto com validade
        DB::table('lotes_produto')->insert([
            'id' => 1,
            'produto_id' => 1,
            'armazem_id' => 1,
            'codigo_lote' => 'LOTE-001',
            'data_validade' => Carbon::today()->addMonths(3),
            'qtd_atual' => 40,
            'qtd_inicial' => 40,
            'quantidade_actual' => 40,
            'quantidade_inicial' => 40,
            'status' => 'activo',
        ]);
        DB::table('lotes_produto')->insert([
            'id' => 2,
            'produto_id' => 1,
            'armazem_id' => 1,
            'codigo_lote' => 'LOTE-002',
            'data_validade' => Carbon::today()->addMonths(6),
            'qtd_atual' => 60,
            'qtd_inicial' => 60,
            'quantidade_actual' => 60,
            'quantidade_inicial' => 60,
            'status' => 'activo',
        ]);
    }

    /** @test */
    public function documento_service_update_stock_com_produto_sem_validade(): void
    {
        DB::table('documentos')->insert([
            'id' => 1,
            'tipo_nome' => 'Fatura',
            'tipo_sigla' => 'FT',
            'armazem_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'num_fatura' => 'FT 2026/1',
            'total_geral' => 60.00,
            'data_emissao' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('itens_documento')->insert([
            'documento_id' => 1,
            'produto_id' => 2,
            'produto_nome' => 'Produto Simples',
            'quantidade' => 3,
            'preco_unitario' => 30.00,
            'iva_percent' => 14.00,
            'total' => 90.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documento = Documento::find(1);
        $stockAntes = Stock::where('produto_id', 2)->where('armazem_id', 1)->value('stock_atual');

        $service = app(DocumentoService::class);
        $service->updateStock($documento);

        $stockDepois = Stock::where('produto_id', 2)->where('armazem_id', 1)->value('stock_atual');
        $this->assertEquals($stockAntes - 3, $stockDepois);

        $this->assertDatabaseHas('movimentos_stock', [
            'produto_id' => 2,
            'quantidade' => 3,
            'operacao' => 'saida',
        ]);
    }

    /** @test */
    public function documento_service_update_stock_com_produto_com_validade(): void
    {
        DB::table('documentos')->insert([
            'id' => 2,
            'tipo_nome' => 'Fatura',
            'tipo_sigla' => 'FT',
            'armazem_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'num_fatura' => 'FT 2026/2',
            'total_geral' => 200.00,
            'data_emissao' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('itens_documento')->insert([
            'documento_id' => 2,
            'produto_id' => 1,
            'produto_nome' => 'Produto Validade Teste',
            'quantidade' => 10,
            'preco_unitario' => 100.00,
            'iva_percent' => 14.00,
            'total' => 1000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documento = Documento::find(2);
        $lote1Antes = DB::table('lotes_produto')->where('id', 1)->value('qtd_atual');

        $service = app(DocumentoService::class);
        $service->updateStock($documento);

        $lote1Depois = DB::table('lotes_produto')->where('id', 1)->value('qtd_atual');
        $this->assertEquals($lote1Antes - 10, $lote1Depois);

        $lote2Depois = DB::table('lotes_produto')->where('id', 2)->value('qtd_atual');
        $this->assertEquals(60, $lote2Depois);

        $item = $documento->itens()->first();
        $this->assertNotNull($item->detalhes_lote);
    }

    /** @test */
    public function documento_service_update_stock_lanca_excecao_quando_stock_insuficiente(): void
    {
        DB::table('documentos')->insert([
            'id' => 3,
            'tipo_nome' => 'Fatura',
            'tipo_sigla' => 'FT',
            'armazem_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'num_fatura' => 'FT 2026/3',
            'total_geral' => 5000.00,
            'data_emissao' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('itens_documento')->insert([
            'documento_id' => 3,
            'produto_id' => 2,
            'produto_nome' => 'Produto Simples',
            'quantidade' => 999,
            'preco_unitario' => 30.00,
            'iva_percent' => 14.00,
            'total' => 29970.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documento = Documento::find(3);
        $service = app(DocumentoService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $service->updateStock($documento);
    }

    /** @test */
    public function documento_compra_service_update_stock_entrada_com_lote(): void
    {
        DB::table('documentos_compra')->insert([
            'id' => 1,
            'tipo_sigla' => 'FC',
            'armazem_id' => 1,
            'empresa_id' => 1,
            'utilizador_id' => 1,
            'num_fatura' => 'FC 2026/1',
            'total_geral' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('itens_documento_compra')->insert([
            'documento_compra_id' => 1,
            'produto_id' => 1,
            'produto_nome' => 'Produto Validade Teste',
            'quantidade' => 30,
            'preco_custo' => 50.00,
            'iva_percent' => 14.00,
            'total' => 1500.00,
            'total_sem_desconto' => 1500.00,
            'lote_data_validade' => Carbon::today()->addYear()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentoCompra = DocumentoCompra::find(1);
        $lotesAntes = DB::table('lotes_produto')->where('produto_id', 1)->sum('qtd_atual');

        $service = app(DocumentoCompraService::class);
        $service->updateStock($documentoCompra);

        $lotesDepois = DB::table('lotes_produto')->where('produto_id', 1)->sum('qtd_atual');
        $this->assertEquals($lotesAntes + 30, $lotesDepois);

        $this->assertDatabaseHas('movimentos_stock', [
            'produto_id' => 1,
            'operacao' => 'entrada',
        ]);
    }

    /** @test */
    public function validade_service_seleciona_lotes_fefo_corretamente(): void
    {
        $service = app(ValidadeService::class);

        $lotesSelecionados = $service->selecionarLotesParaVenda(1, 30);

        $this->assertCount(1, $lotesSelecionados);
        $this->assertEquals('LOTE-001', $lotesSelecionados[0]['codigo_lote']);
        $this->assertEquals(30, $lotesSelecionados[0]['quantidade']);
    }

    /** @test */
    public function validade_service_seleciona_lotes_de_dois_lotes_quando_necessario(): void
    {
        $service = app(ValidadeService::class);

        $lotesSelecionados = $service->selecionarLotesParaVenda(1, 70);

        $this->assertCount(2, $lotesSelecionados);
        $this->assertEquals('LOTE-001', $lotesSelecionados[0]['codigo_lote']);
        $this->assertEquals(40, $lotesSelecionados[0]['quantidade']);
        $this->assertEquals('LOTE-002', $lotesSelecionados[1]['codigo_lote']);
        $this->assertEquals(30, $lotesSelecionados[1]['quantidade']);
    }

    /** @test */
    public function validade_service_lanca_excecao_quando_stock_insuficiente(): void
    {
        $service = app(ValidadeService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $service->selecionarLotesParaVenda(1, 999);
    }

    /** @test */
    public function stock_service_dar_entrada_com_lote(): void
    {
        $service = app(StockService::class);

        $resultado = $service->darEntrada(1, 20, 50.00, [
            'codigo_lote' => 'LOTE-NOVO-001',
            'data_validade' => Carbon::today()->addYear()->format('Y-m-d'),
        ]);

        $this->assertTrue($resultado);
        $this->assertDatabaseHas('lotes_produto', [
            'codigo_lote' => 'LOTE-NOVO-001',
            'qtd_atual' => 20,
            'status' => 'activo',
        ]);
    }

    /** @test */
    public function stock_service_dar_entrada_sem_lote_para_produto_sem_validade(): void
    {
        $service = app(StockService::class);

        $stockAntes = DB::table('produtos')->where('id', 2)->value('stock');

        $resultado = $service->darEntrada(2, 15, 30.00);

        $this->assertTrue($resultado);
        $stockDepois = DB::table('produtos')->where('id', 2)->value('stock');
        $this->assertEquals($stockAntes + 15, $stockDepois);
    }

    protected function criarTabelaConfigValidacaoProduto(): void
    {
        $this->dropTabela('config_validacao_produtos');
        DB::statement('CREATE TABLE config_validacao_produtos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            produto_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}
