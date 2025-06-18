<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{

    use SoftDeletes;

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
        'gestor_id',
        'vencimento',
        'telemovel',
        'fatura_eletronica',
        'website',
        'grupo_preco_id',
        'observacoes',
        'faz_retencao',
        'pais',
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
