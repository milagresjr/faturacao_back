<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentoNumeroService
{
    public function gerarNumeroDocumento(int $serieId): string
    {
        $serie = DB::table('series')
            ->where('id', $serieId)
            ->lockForUpdate()
            ->first();

        if (!$serie) {
            throw new \Exception('Série não encontrada.');
        }

        $proximoNumero = $serie->sequencia_atual + 1;

        DB::table('series')
            ->where('id', $serieId)
            ->update([
                'sequencia_atual' => $proximoNumero
            ]);

        return "{$serie->prefixo} {$serie->ano}/{$proximoNumero}";
    }
}
