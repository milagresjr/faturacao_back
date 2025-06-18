<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoProduto extends Model
{
    protected $table = 'tipo_produtos';

    protected $fillable = [
        'nome',
        'descricao',
        'estado',
    ];
}
