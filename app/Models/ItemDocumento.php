<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDocumento extends Model
{
    protected $table = "itens_documento";

    protected $fillable = [
        'documento_id', 
        'produto_nome', 
        'produto_codigo',
        'preco_unitario', 
        'quantidade',
        'desconto_percent', 
        'desconto_fixo', 
        'iva_percent', 
        'total'
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }
}
