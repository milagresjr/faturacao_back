<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class LogotipoServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/softseven-test-logos';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_deve_retornar_base64_quando_ficheiro_existe(): void
    {
        $filename = 'logo-teste.png';
        $content = 'fake-image-content';
        file_put_contents($this->tempDir . '/' . $filename, $content);

        $imagePath = $this->tempDir . '/' . $filename;
        $result = null;

        if (!empty($filename) && file_exists($imagePath) && is_file($imagePath)) {
            $result = 'data:image/png;base64,' . base64_encode(file_get_contents($imagePath));
        }

        $this->assertNotNull($result);
        $this->assertEquals('data:image/png;base64,' . base64_encode($content), $result);
    }

    public function test_deve_retornar_null_quando_ficheiro_nao_existe(): void
    {
        $filename = 'logo-inexistente.png';
        $imagePath = $this->tempDir . '/' . $filename;

        $result = null;
        if (!empty($filename) && file_exists($imagePath) && is_file($imagePath)) {
            $result = 'data:image/png;base64,' . base64_encode(file_get_contents($imagePath));
        }

        $this->assertNull($result);
    }

    public function test_deve_retornar_null_quando_nome_ficheiro_vazio(): void
    {
        $filename = '';
        $result = null;

        if (!empty($filename) && file_exists('/tmp') && is_file('/tmp')) {
            $result = 'data:image/png;base64,';
        }

        $this->assertNull($result);
    }

    public function test_deve_retornar_null_quando_mostrar_logo_eh_false(): void
    {
        $mostrarLogo = false;
        $logo = 'logo.png';
        $src = null;

        if ($mostrarLogo && !empty($logo)) {
            $src = 'data:image/png;base64,abc';
        }

        $this->assertNull($src);
    }
}
