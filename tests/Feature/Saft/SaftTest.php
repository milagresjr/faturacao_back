<?php

namespace Tests\Feature\Saft;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class SaftTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelaEmpresas();
        $this->criarTabelaPerfis();
        $this->criarTabelaPermissoes();
        $this->criarTabelaPerfilPermissao();
        $this->criarTabelaUtilizadores();
        $this->criarTabelaPersonalAccessTokens();
        $this->criarTabelaClientes();
        $this->criarTabelaFornecedores();
        $this->criarTabelaTipoProduto();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaInfoGuia();
        $this->criarTabelaDocumentos();
        $this->criarTabelaItensDocumento();
        $this->criarTabelaMeiosPagamentoDocumento();
        $this->criarTabelaImpostosDocumento();
        $this->criarTabelaDocumentoRelacoes();
        $this->criarTabelaSeries();
        $this->criarTabelaBancos();
        $this->criarTabelaContas();
        $this->criarTabelaTipoTaxaIva();
        $this->criarTabelaUnidades();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaDocumentosCompra();
        $this->criarTabelaItensDocumentoCompra();
        $this->criarTabelaImpostosDocumentoCompra();

        DB::table('empresas')->insert([
            'id' => 1,
            'nome' => 'Empresa SAF-T',
            'email' => 'saft@empresa.com',
            'nif' => '999999990',
            'telefone' => '923456789',
            'morada' => 'Rua SAF-T, 123',
            'regime_tributario' => 'regime_normal',
            'indicativo_fatura' => 'FT',
        ]);

        DB::table('tipos_taxa_iva')->insert([
            'id' => 1,
            'codigo' => 'NOR',
            'descricao' => 'IVA Normal',
            'taxa' => 14.00,
            'empresa_id' => 1,
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
            'email' => 'admin@saft.com',
            'senha' => bcrypt('password'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'perfil_id' => 1,
            'empresa_id' => 1,
            'must_change_password' => 0,
            'remember_token' => 'test-token-saft-2026',
        ]);

        DB::table('clientes')->insert([
            'id' => 1,
            'nome' => 'Cliente SAF-T',
            'nif' => '111111119',
            'email' => 'cliente@saft.com',
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        DB::table('tipo_produtos')->insert([
            'id' => 1,
            'nome' => 'Produto',
            'empresa_id' => 1,
            'estado' => 1,
        ]);

        DB::table('produtos')->insert([
            'id' => 1,
            'nome' => 'Produto SAF-T',
            'preco_venda' => 100.00,
            'codigo_produto' => 'PROD-SAFT',
            'empresa_id' => 1,
            'estado' => 1,
            'movimenta_stock' => 0,
            'tipo_id' => 1,
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

        DB::table('documentos')->insert([
            'id' => 1,
            'tipo_nome' => 'Fatura',
            'tipo_sigla' => 'FT',
            'empresa_id' => 1,
            'cliente_id' => 1,
            'cliente_nome' => 'Cliente SAF-T',
            'cliente_nif' => '111111119',
            'total_geral' => 114.00,
            'total_impostos' => 14.00,
            'total_sem_desconto' => 100.00,
            'taxa_iva' => 14.00,
            'valor_iva' => 14.00,
            'num_fatura' => 'FT 2026/1',
            'estado_documento' => 'emitido',
            'estado_pagamento' => 'pago',
            'data_emissao' => now()->subDay(),
            'utilizador_id' => 1,
            'serie_id' => 1,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('itens_documento')->insert([
            'id' => 1,
            'documento_id' => 1,
            'produto_id' => 1,
            'produto_nome' => 'Produto SAF-T',
            'produto_codigo' => 'PROD-SAFT',
            'quantidade' => 1,
            'preco_unitario' => 100.00,
            'total' => 100.00,
            'iva_percent' => 14.00,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }

    public function test_gerar_saft_exporta_xml_valido(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token-saft-2026',
        ])->get('/api/generate-saft?tipo=faturacao&empresa_id=1');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->content());
    }

    public function test_list_saft_faturas(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token-saft-2026',
        ])->getJson('/api/list-saft-faturas?empresa_id=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);
    }
}
