<?php

namespace Tests\Feature\Comando;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\DatabaseSetup;
use Tests\TestCase;

class StockCommandsTest extends TestCase
{
    use DatabaseTransactions, DatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelaEmpresas();
        $this->criarTabelaArmazens();
        $this->criarTabelaProdutos();
        $this->criarTabelaStocks();
        $this->criarTabelaMovimentosStock();
        $this->criarTabelaAlertasStock();
        $this->criarTabelaFiliais();
        $this->criarTabelaCaixas();

        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => '923456789',
            'morada' => 'Rua Teste, 123',
        ]);

        DB::table('armazens')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Armazém Principal',
            'empresa_id' => 1,
        ]);

        DB::table('produtos')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Produto Teste',
            'movimenta_stock' => 1,
            'controla_validade' => 0,
            'empresa_id' => 1,
            'stock_atual' => 100,
            'preco_venda' => 10.00,
        ]);

        config([
            'services.sms.url' => 'https://fake-sms.example.com/api',
            'services.sms.key' => 'fake-key',
            'services.sms.from' => 'FakeSender',
        ]);
        Http::fake();
    }

    public function test_command_recalcular_stock_atual(): void
    {
        DB::table('stocks')->insertOrIgnore([
            'id' => 1,
            'produto_id' => 1,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('movimentos_stock')->insert([
            'produto_id' => 1,
            'armazem_id' => 1,
            'quantidade' => 50,
            'operacao' => 'entrada',
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('movimentos_stock')->insert([
            'produto_id' => 1,
            'armazem_id' => 1,
            'quantidade' => 20,
            'operacao' => 'saida',
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('stock:recalcular-stock-atual');

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('stocks', [
            'produto_id' => 1,
            'armazem_id' => 1,
            'stock_atual' => 30,
        ]);
    }

    public function test_command_verificar_stock_minimo_cria_alerta(): void
    {
        DB::table('stocks')->insertOrIgnore([
            'id' => 2,
            'produto_id' => 1,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 1,
            'stock_min' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('movimentos_stock')->insert([
            'stock_id' => 2,
            'produto_id' => 1,
            'armazem_id' => 1,
            'quantidade' => 10,
            'operacao' => 'entrada',
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('stock:verificar-stock-minimo');

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('alertas_stock', [
            'stock_id' => 2,
            'stock_atual' => 1,
        ]);
    }

    public function test_command_verificar_stock_minimo_nao_cria_alerta_duplicado(): void
    {
        DB::table('stocks')->insertOrIgnore([
            'id' => 3,
            'produto_id' => 1,
            'armazem_id' => 1,
            'empresa_id' => 1,
            'stock_atual' => 2,
            'stock_min' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('movimentos_stock')->insert([
            'stock_id' => 3,
            'produto_id' => 1,
            'armazem_id' => 1,
            'quantidade' => 10,
            'operacao' => 'entrada',
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('alertas_stock')->insert([
            'stock_id' => 3,
            'produto_id' => 1,
            'armazem_id' => 1,
            'stock_atual' => 2,
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $alertasAntes = DB::table('alertas_stock')->count();

        Artisan::call('stock:verificar-stock-minimo');

        $this->assertEquals($alertasAntes, DB::table('alertas_stock')->count());
    }
}
