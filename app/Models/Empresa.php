<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'nome',
        'email',
        'nif',
        'regime_tributario',
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
        'rua',
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
