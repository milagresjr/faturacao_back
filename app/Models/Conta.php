<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conta extends Model
{
    use SoftDeletes;

    protected $table = 'contas';

    protected $fillable = [
        'empresa_id',
        'banco_id',
        'utilizador_id',
        'numero_conta',
        'descricao',
        'saldo',
        'tipo',
        'moeda',
        'iban',
        'swift',
        'titular',
        'estado'
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }
}
