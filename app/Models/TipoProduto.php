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
