<?php

namespace App\Services;

use App\Models\ConfiguracaoFatura;

class LogotipoService
{
    public function carregar(int $empresaId): array
    {
        $config = ConfiguracaoFatura::where('empresa_id', $empresaId)->first();
        $src = null;

        if ($config && !empty($config->logo) && $config->mostrar_logo) {
            $imagePath = storage_path('app/public/logos-fatura/' . $config->logo);
            if (file_exists($imagePath) && is_file($imagePath)) {
                $imageData = base64_encode(file_get_contents($imagePath));
                $src = 'data:image/png;base64,' . $imageData;
            }
        }

        return [
            'src' => $src,
            'dadosPersonalizacaoFatura' => $config,
        ];
    }

    /**
     * Retorna apenas o nome do ficheiro do logo atual da empresa (ou null).
     */
    public function obterNomeLogo(int $empresaId): ?string
    {
        $config = ConfiguracaoFatura::where('empresa_id', $empresaId)->first();

        if ($config && !empty($config->logo) && $config->mostrar_logo) {
            $imagePath = storage_path('app/public/logos-fatura/' . $config->logo);
            if (file_exists($imagePath) && is_file($imagePath)) {
                return $config->logo;
            }
        }

        return null;
    }

    /**
     * Converte um nome de ficheiro de logo guardado no documento em data URI base64.
     */
    public function obterSrc(?string $nomeLogo): ?string
    {
        if (empty($nomeLogo)) {
            return null;
        }

        $imagePath = storage_path('app/public/logos-fatura/' . $nomeLogo);
        if (!file_exists($imagePath) || !is_file($imagePath)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($imagePath));
    }

    /**
     * Retorna o logo com dimensões proporcionais dentro de uma caixa máxima.
     * O DomPDF não suporta "object-fit", por isso o tamanho exato tem de ser
     * calculado no servidor para não distorcer nem estourar o cabeçalho do PDF.
     *
     * @return array|null {src, largura, altura}
     */
    public function obterEstilo(?string $nomeLogo, int $maxLargura = 220, int $maxAltura = 90): ?array
    {
        $src = $this->obterSrc($nomeLogo);
        if ($src === null) {
            return null;
        }

        $imagePath = storage_path('app/public/logos-fatura/' . $nomeLogo);
        [$larguraOriginal, $alturaOriginal] = @getimagesize($imagePath) ?: [0, 0];

        if ($larguraOriginal <= 0 || $alturaOriginal <= 0) {
            // Dimensões desconhecidas: usa a caixa máxima (comportamento antigo)
            return ['src' => $src, 'largura' => $maxLargura, 'altura' => $maxAltura];
        }

        $escala = min($maxLargura / $larguraOriginal, $maxAltura / $alturaOriginal);

        return [
            'src' => $src,
            'largura' => max(1, (int) round($larguraOriginal * $escala)),
            'altura' => max(1, (int) round($alturaOriginal * $escala)),
        ];
    }

    /**
     * Retorna o template de fatura (classic/modern/minimal) configurado na empresa.
     */
    public function obterTemplate(int $empresaId): string
    {
        $template = ConfiguracaoFatura::where('empresa_id', $empresaId)->value('template');
        return in_array($template, ['classic', 'modern', 'minimal']) ? $template : 'classic';
    }
}
