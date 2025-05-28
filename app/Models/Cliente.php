<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{

    protected $table = 'clientes';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'nif',
        'numero_bi',
        'endereco',
        'data_nasc',
        'estado',
        'tipo_cliente_id',
        'empresa_id',
        'utilizador_id'
    ];

    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class);
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
