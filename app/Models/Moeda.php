<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Moeda extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nome',
        'simbolo',
        'casas_decimais',
        'predefinida',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function taxasCambio()
    {
        return $this->hasMany(TaxaCambio::class);
    }
}