<?php

namespace Tests\Feature\Documento;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaClientes();
        $this->criarTabelaArmazens();
        $this->criarTabelaCaixas();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaSeries();
        $this->criarTabelaConfiguracoesFatura();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaInfoGuia();
        $this->criarTabelaDocumentos();
        $this->criarTabelaItensDocumento();
        $this->criarTabelaMeiosPagamentoDocumento();
        $this->criarTabelaImpostosDocumento();
        $this->criarTabelaDocumentoRelacoes();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaBancos();
        $this->criarTabelaBancosDocumento();
        $this->criarTabelaContas();
        $this->criarTabelaUnidades();
        $this->criarTabelaLotesProduto();

        // Seed dados base
        DB::table('armazens')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Armazém Principal',
            'empresa_id' => 1,
            'predefinido' => 1,
        ]);

        DB::table('clientes')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Cliente Teste',
            'nif' => '987654321',
            'email' => 'cliente@teste.com',
            'telefone' => '912345678',
            'endereco' => 'Av. Cliente, 456',
            'tipo_cliente_id' => 1,
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        DB::table('tipos_taxa_iva')->insertOrIgnore([
            'id' => 1,
            'codigo' => 'NOR',
            'descricao' => 'IVA Normal',
            'taxa' => 14.00,
            'empresa_id' => 1,
        ]);

        DB::table('produtos')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produto Teste',
            'codigo_produto' => 'PROD001',
            'preco_venda' => 100.00,
            'preco_custo' => 50.00,
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'stock_atual' => 100,
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        DB::table('stocks')->insertOrIgnore([
            'id' => 1,
            'produto_id' => 1,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 100,
        ]);

        DB::table('series')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Factura Série A',
            'prefixo' => 'FT',
            'ano' => '2026',
            'tipo_documento' => 'factura',
            'sequencia_atual' => 0,
            'empresa_id' => 1,
            'padrao' => 1,
            'ativo' => 1,
        ]);

        DB::table('bancos')->insertOrIgnore([
            'id' => 1,
            'descricao' => 'Banco Teste',
            'sigla' => 'BT',
            'estado' => 1,
        ]);

        DB::table('contas')->insertOrIgnore([
            'id' => 1,
            'banco_id' => 1,
            'nome' => 'Conta Teste',
            'numero_conta' => '12345-6',
            'iban' => 'PT50001234567890123456789',
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    private function criarFatura(array $extra = []): int
    {
        $payload = array_merge([
            'tipo_fatura' => 'Fatura',
            'sigla_fatura' => 'FT',
            'serie_id' => 1,
            'caixa' => 'Caixa 1',
            'data_emissao' => now()->format('Y-m-d'),
            'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
            'movimenta_stock' => false,
            'cliente_nome' => 'Cliente Teste',
            'cliente_nif' => '987654321',
            'cliente_id' => 1,
            'empresa_id' => 1,
            'armazem_id' => 1,
            'itens' => [
                [
                    'produto_nome' => 'Produto Teste',
                    'codigo_produto' => 'PROD001',
                    'preco_venda' => 100.00,
                    'quantidade' => 2,
                    'desconto_percent' => 0,
                    'desconto_fixo' => 0,
                    'iva_percent' => 1,
                    'produto_id' => 1,
                    'descricao' => '',
                    'tipo_id' => null,
                ],
            ],
            'meiosPagamento' => [
                ['descricao' => 'dinheiro', 'valor' => 200.00],
            ],
            'utilizador_id' => 1,
            'utilizador' => 'Admin Teste',
        ], $extra);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/documentos', $payload);

        return $response->json('documento.id');
    }

    public function test_pode_criar_fatura_completa(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/documentos', [
                'tipo_fatura' => 'Fatura',
                'sigla_fatura' => 'FT',
                'serie_id' => 1,
                'caixa' => 'Caixa 1',
                'data_emissao' => now()->format('Y-m-d'),
                'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
                'movimenta_stock' => false,
                'cliente_nome' => 'Cliente Teste',
                'cliente_nif' => '987654321',
                'cliente_id' => 1,
                'empresa_id' => 1,
                'armazem_id' => 1,
                'itens' => [
                    [
                        'produto_nome' => 'Produto Teste',
                        'codigo_produto' => 'PROD001',
                        'preco_venda' => 100.00,
                        'quantidade' => 2,
                        'desconto_percent' => 0,
                        'desconto_fixo' => 0,
                        'iva_percent' => 1,
                        'produto_id' => 1,
                        'descricao' => '',
                        'tipo_id' => null,
                    ],
                ],
                'meiosPagamento' => [
                    ['descricao' => 'dinheiro', 'valor' => 200.00],
                ],
                'utilizador_id' => 1,
                'utilizador' => 'Admin Teste',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'documento' => ['id', 'num_fatura', 'total_geral'],
        ]);
        $this->assertDatabaseHas('documentos', [
            'tipo_sigla' => 'FT',
        ]);
    }

    public function test_pode_listar_documentos_com_filtros(): void
    {
        $this->criarFatura();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/documentos?tipo=FT&per_page=10&empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);
    }

    public function test_pode_ver_documento_individual(): void
    {
        $id = $this->criarFatura();

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/documentos/{$id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $id]);
    }

    public function test_pode_anular_documento(): void
    {
        $id = $this->criarFatura();

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/documentos/{$id}/anular");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Documento anulado com sucesso.']);
        $this->assertDatabaseHas('documentos', [
            'id' => $id,
            'estado' => 'cancelada',
        ]);
    }

    public function test_nao_pode_anular_documento_nao_emitido(): void
    {
        $id = $this->criarFatura();

        DB::table('documentos')->where('id', $id)->update(['estado' => 'rascunho']);

        $response = $this->withHeaders($this->headers())
            ->postJson("/api/documentos/{$id}/anular");

        $response->assertStatus(400);
    }

    public function test_pode_criar_nota_credito(): void
    {
        $faturaId = $this->criarFatura();

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/documentos/nota-credito', [
                'documento_id' => $faturaId,
                'serie_id' => 1,
                'data_emissao' => now()->format('Y-m-d'),
                'motivo_emissao' => 'Devolução de mercadoria',
                'empresa_id' => 1,
                'itens' => [
                    [
                        'produto_nome' => 'Produto Teste',
                        'codigo_produto' => 'PROD001',
                        'preco_venda' => 100.00,
                        'quantidade' => 1,
                        'desconto_percent' => 0,
                        'desconto_fixo' => 0,
                        'iva_percent' => 1,
                        'imposto_taxa_id' => 1,
                        'produto_id' => 1,
                        'descricao' => '',
                        'tipo_id' => null,
                    ],
                ],
                'meiosPagamento' => [
                    ['descricao' => 'dinheiro', 'valor' => 100.00],
                ],
                'utilizador_id' => 1,
                'utilizador' => 'Admin Teste',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'documento' => ['id', 'num_fatura', 'total_geral'],
        ]);
    }

    public function test_pode_finalizar_documento_rascunho(): void
    {
        $id = $this->criarFatura(['estado_documento' => 'rascunho']);

        $response = $this->withHeaders($this->headers())
            ->patchJson("/api/documentos/{$id}/finalizar", [
                'tipo_fatura' => 'Fatura',
                'sigla_fatura' => 'FT',
                'serie_id' => 1,
                'caixa' => 'Caixa 1',
                'data_emissao' => now()->format('Y-m-d'),
                'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
                'movimenta_stock' => false,
                'cliente_nome' => 'Cliente Teste',
                'cliente_nif' => '987654321',
                'cliente_id' => 1,
                'empresa_id' => 1,
                'empresa_nome' => 'Empresa Teste',
                'empresa_nif' => '123456789',
                'itens' => [
                    [
                        'produto_nome' => 'Produto Teste',
                        'codigo_produto' => 'PROD001',
                        'preco_venda' => 100.00,
                        'quantidade' => 1,
                        'desconto_percent' => 0,
                        'desconto_fixo' => 0,
                        'iva_percent' => 1,
                        'produto_id' => 1,
                        'descricao' => '',
                        'tipo_id' => null,
                    ],
                ],
                'meiosPagamento' => [
                    ['descricao' => 'dinheiro', 'valor' => 100.00],
                ],
                'utilizador_id' => 1,
                'utilizador' => 'Admin Teste',
            ]);

        $response->assertStatus(201);
    }
}

