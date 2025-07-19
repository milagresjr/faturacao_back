<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeioPagamentoDocumento extends Model
{
    protected $table = 'meios_pagamento_documento';

    protected $fillable = [
        'documento_id',
        'descricao',
        'valor',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }
}
