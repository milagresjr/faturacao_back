<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoTaxaIva extends Model
{
    protected $table = 'tipos_taxa_iva';

    public $timestamps = true;

    protected $fillable = [
        'codigo',
        'descricao',
        'taxa'
    ];

    // Define any relationships if necessary
    // For example, if TipoTaxaIva is related to Empresa
    // public function empresa()
    // {
    //     return $this->belongsTo(Empresa::class);
    // }
}
