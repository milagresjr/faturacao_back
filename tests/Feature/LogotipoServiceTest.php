<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoFatura;
use App\Models\Empresa;
use App\Services\LogotipoService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogotipoServiceTest extends TestCase
{
    private LogotipoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LogotipoService::class);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DROP TABLE IF EXISTS configuracoes_fatura');
        DB::statement('DROP TABLE IF EXISTS empresas');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::statement('
            CREATE TABLE empresas (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                nif VARCHAR(255) NOT NULL,
                telefone INT NOT NULL,
                morada TEXT NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        DB::statement('
            CREATE TABLE configuracoes_fatura (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                empresa_id BIGINT UNSIGNED NOT NULL,
                logo VARCHAR(255) NULL,
                mostrar_logo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    public function test_deve_retornar_src_null_quando_empresa_sem_configuracao(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Teste',
            'email' => 'teste@teste.com',
            'nif' => '123456789',
            'telefone' => 999999999,
            'morada' => 'Rua X',
        ]);

        $resultado = $this->service->carregar($empresa->id);

        $this->assertNull($resultado['src']);
        $this->assertNull($resultado['dadosPersonalizacaoFatura']);
    }

    public function test_deve_retornar_src_null_quando_mostrar_logo_eh_false(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Teste2',
            'email' => 'teste2@teste.com',
            'nif' => '987654321',
            'telefone' => 999999998,
            'morada' => 'Rua Y',
        ]);
        ConfiguracaoFatura::create([
            'empresa_id' => $empresa->id,
            'mostrar_logo' => false,
            'logo' => 'logo.png',
        ]);

        $resultado = $this->service->carregar($empresa->id);

        $this->assertNull($resultado['src']);
    }

    public function test_deve_retornar_src_null_quando_ficheiro_logo_nao_existe(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Teste3',
            'email' => 'teste3@teste.com',
            'nif' => '111111111',
            'telefone' => 999999997,
            'morada' => 'Rua Z',
        ]);
        ConfiguracaoFatura::create([
            'empresa_id' => $empresa->id,
            'mostrar_logo' => true,
            'logo' => 'ficheiro-inexistente.png',
        ]);

        $resultado = $this->service->carregar($empresa->id);

        $this->assertNull($resultado['src']);
        $this->assertNotNull($resultado['dadosPersonalizacaoFatura']);
    }

    public function test_deve_retornar_base64_quando_logo_existe(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Teste4',
            'email' => 'teste4@teste.com',
            'nif' => '222222222',
            'telefone' => 999999996,
            'morada' => 'Rua W',
        ]);
        ConfiguracaoFatura::create([
            'empresa_id' => $empresa->id,
            'mostrar_logo' => true,
            'logo' => 'logo-teste.png',
        ]);

        $logoDir = storage_path('app/public/logos-fatura');
        if (!is_dir($logoDir)) {
            mkdir($logoDir, 0777, true);
        }
        $logoPath = $logoDir . '/logo-teste.png';
        file_put_contents($logoPath, 'fake-png-content');

        $resultado = $this->service->carregar($empresa->id);

        $this->assertNotNull($resultado['src']);
        $this->assertStringStartsWith('data:image/png;base64,', $resultado['src']);

        unlink($logoPath);
    }
}
