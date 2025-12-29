<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PagamentoDocumentoCompra extends Model
{
    use SoftDeletes;

    protected $table = 'pagamentos_documento_compra';

    protected $fillable = [
        'documento_compra_id',
        'observacao',
        'data_pagamento',
        'valor',
    ];

    public function documento(): BelongsTo
    {
       return $this->belongsTo(DocumentoCompra::class, 'documento_compra_id');
    }
}
