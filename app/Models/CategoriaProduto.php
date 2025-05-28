<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaProduto extends Model
{
    protected $table = 'categorias';

    protected $fillable = [
        'nome',
        'descricao',
        'estado',
        'empresa_id',
        'utilizador_id'
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class);
    }
}
