<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Armazem extends Model
{
    protected $table = 'armazens';
    
    protected $fillable = [
        'nome',
        'endereco',
        'estado',
        'filial_id',
        'empresa_id',
        'utilizador_id',
    ];

    public function filial()
    {
        return $this->belongsTo(Filial::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class);
    }
}
