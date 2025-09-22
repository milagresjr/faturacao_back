<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDocumentoCompra extends Model
{
    protected $table = 'itens_documento_compra';

    protected $fillable = [
        'documento_compra_id',
        'produto_nome',
        'produto_codigo',
        'preco_custo',
        'descricao',
        'quantidade',
        'desconto_percent',
        'desconto_fixo',
        'iva_percent',
        'total'
    ];

    public function documentos(): BelongsTo
    {
        return $this->belongsTo(DocumentoCompra::class, 'documento_compra_id');
    }
}
