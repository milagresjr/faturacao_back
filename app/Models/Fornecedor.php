<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fornecedor extends Model
{
    protected $table = 'fornecedores';
    
    protected $fillable = [
        'nome',
        'telefone',
        'email',
        'endereco',
        'nif',
        'estado',
        'empresa_id',
        'utilizador_id',
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
