<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxaCambio extends Model
{
    use SoftDeletes;

    protected $table = 'taxas_cambio';

    protected $fillable = [
        'empresa_id',
        'moeda_id',
        'taxa',
        'data',
        'fonte',
        'estado',
    ];

    protected $casts = [
        'taxa' => 'float',
        'data' => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function moeda()
    {
        return $this->belongsTo(Moeda::class);
    }
}