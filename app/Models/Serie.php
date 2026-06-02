<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serie extends Model
{
    use SoftDeletes;

    protected $table = 'series';

    protected $fillable = [
        'empresa_id',
        'nome',
        'ano',
        'prefixo',
        'tipo_documento',
        'sequencia_atual',
        'padrao',
        'ativo',
    ];

    protected $casts = [
        'padrao' => 'boolean',
        'ativo' => 'boolean',
    ];

    public static function getTipoDocumento()
    {

        $tiposDocumento = [
            'factura' => 'FT',
            'factura_recibo' => 'FR',
            'recibo' => 'RC',
            'proforma' => 'PF',
            'nota_credito' => 'NC',
            'nota_debito' => 'ND',
            'orcamento' => 'OR',
            'encomenda' => 'EN',
            'guia_remessa' => 'GR',
            'guia_transporte' => 'GT',
            'entrada' => 'ET',
            'saida' => 'SD',
            'entrada_inventario' => 'EI',
            'saida_inventario' => 'SI',
            'nota_quebra' => 'NQ',
            'transferencia' => 'TR',
        ];

        return $tiposDocumento;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function gerarNumero(): string
    {
        $proximo = $this->sequencia_atual + 1;

        return $this->prefixo . ' ' .
            date('Y') . '/' .
            str_pad($proximo, 4, '0', STR_PAD_LEFT);
    }
}
