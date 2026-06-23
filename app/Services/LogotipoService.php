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
}
