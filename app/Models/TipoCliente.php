<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCliente extends Model
{
    protected $table = 'tipo_clientes';

    protected $fillable = [
        'descricao',
        'estado',
        'utilizador_id',
        'empresa_id'
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
