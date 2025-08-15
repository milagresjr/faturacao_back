<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpostoDocumento extends Model
{
    protected $table = 'impostos_documento';

    protected $fillable = [
        'documento_id',
        'taxa',
        'codigo',
        'isento',
        'motivo_isencao',
        'incidencia',
        'base',
        'imposto',
        'total',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    // Define other relationships or methods as needed
}
