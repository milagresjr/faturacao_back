<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $fillable = [
        'nome',
        'descricao',
        'estado',
        'empresa_id',
        'utilizador_id',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function utilizadores()
    {
        return $this->belongsTo(Utilizador::class);
    }
}
