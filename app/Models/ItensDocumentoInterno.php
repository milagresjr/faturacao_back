<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItensDocumentoInterno extends Model
{
    protected $table = "itens_documentos_interno";

    protected $fillable = [
        "documento_interno_id",
        "produto_nome",
        "produto_codigo",
        "preco_unitario",
        "descricao",
        "quantidade",
        "desconto_percent",
        "desconto_fixo",
        "iva_percent",
        "total",
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_id');
    }
}
