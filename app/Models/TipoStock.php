<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoStock extends Model
{
    protected $table = 'tipo_stock';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'sigla',
        'motivo_isencao_id',
        'empresa_id'
    ];

}
