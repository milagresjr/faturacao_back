<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasBase();
        $this->criarTabelaClientes();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaInfoGuia();
        $this->criarTabelaDocumentos();
        $this->criarTabelaMeiosPagamentoDocumento();
        $this->criarTabelaDocumentoRelacoes();
        $this->criarTabelaItensDocumento();
        $this->criarTabelaImpostosDocumento();
        $this->criarTabelaSeries();
        $this->criarTabelaBancos();
        $this->criarTabelaContas();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaUnidades();
        $this->criarTabelaConfiguracoesFatura();
        $this->criarTabelaMovimentosStock();

        DB::table('clientes')->insertOrIgnore([
            ['id' => 1, 'nome' => 'Cliente A', 'nif' => '111111111', 'email' => 'cliente.a@teste.com', 'empresa_id' => 1, 'estado' => 1],
        ]);
        DB::table('clientes')->insertOrIgnore([
            ['id' => 2, 'nome' => 'Cliente B', 'nif' => '222222222', 'email' => 'cliente.b@teste.com', 'empresa_id' => 1, 'estado' => 1],
        ]);

        // Documentos no mês atual para os testes de percentagem
        DB::table('documentos')->insert([
            'tipo_nome' => 'Fatura', 'tipo_sigla' => 'FT',
            'empresa_id' => 1, 'cliente_id' => 1,
            'total_geral' => 1000.00, 'total_impostos' => 140.00, 'total_sem_desconto' => 1000.00,
            'estado_documento' => 'emitido', 'estado_pagamento' => 'pago',
            'data_emissao' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('documentos')->insert([
            'tipo_nome' => 'Fatura', 'tipo_sigla' => 'FR',
            'empresa_id' => 1, 'cliente_id' => 1,
            'total_geral' => 1000.00, 'total_impostos' => 140.00, 'total_sem_desconto' => 1000.00,
            'estado_documento' => 'emitido', 'estado_pagamento' => 'nao_pago',
            'data_emissao' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('documentos')->insert([
            'tipo_nome' => 'Recibo', 'tipo_sigla' => 'RC',
            'empresa_id' => 1, 'cliente_id' => 1,
            'total_geral' => 1000.00, 'total_impostos' => 140.00, 'total_sem_desconto' => 1000.00,
            'estado_documento' => 'emitido', 'estado_pagamento' => 'pago',
            'data_emissao' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->token = $this->autenticarComoAdmin();
    }

    private function headers(): array
    {
        return $this->headersComToken($this->token);
    }

    public function test_get_summary_retorna_dados_corretos(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/summary?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'faturacao_total',
            'total_recebido',
            'total_em_falta',
            'clientes_ativos',
        ]);

        $data = $response->json();
        $this->assertGreaterThan(0, $data['faturacao_total']);
        $this->assertGreaterThan(0, $data['total_recebido']);
        $this->assertGreaterThanOrEqual(0, $data['total_em_falta']);
        $this->assertEquals(2, $data['clientes_ativos']);
    }

    public function test_get_monthly_data_retorna_array_12_meses(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/monthly-sales?empresa_id=1');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(12, $data);
    }

    public function test_get_monthly_value_retorna_dados_faturado_recebido(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/faturas-qtd-mensal?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'faturado',
            'recebido',
        ]);

        $data = $response->json();
        $this->assertCount(12, $data['faturado']);
        $this->assertCount(12, $data['recebido']);
    }

    public function test_percentagem_tipos_documentos(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/percent-tipo-doc?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'labels',
            'series',
        ]);
    }

    public function test_percentagem_estado_faturas(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/percent-estado-doc?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'labels',
            'series',
        ]);
    }

    public function test_top_clientes_devedores(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/dashboard/top-clientes-devedores?empresa_id=1');

        $response->assertStatus(200);
    }
}
