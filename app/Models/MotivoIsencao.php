<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoIsencao extends Model
{
    protected $table = 'motivo_isencao';

    protected $fillable = [
        'taxa',
        'taxa_retorno',
        'codigo',
        'motivo',
        'texto',
        'alteracao_manual',
    ];

    public function produtos()
    {
        return $this->hasMany(Produto::class, 'motivo_isencao_id');
    }
}
