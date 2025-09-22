<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpostoDocumentoCompra extends Model
{
    protected $table = 'impostos_documento_compra';

    protected $fillable = [
        'documento_compra_id',
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
        return $this->belongsTo(DocumentoCompra::class);
    }
}
