<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'email',
        'nif',
        'telefone',
        'morada',
        'logo',
        'indicativo_fatura',
        'slogan',
        'website',
        'pais',
        'provincia',
        'municipio',
        'bairro',
        'codigo_postal',
        'status'
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function utilizadores()
    {
        return $this->hasMany(Utilizador::class, 'empresa_id');
    }
}
