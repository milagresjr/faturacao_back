<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherItensDocumentoCompra extends Model
{
    protected $table = "other_itens_documento_compra";

    protected $fillable = [
        "documento_compra_id",
        "nome",
        "preco_custo",
        "descricao",
        "quantidade",
        "desconto_percent",
        "desconto_fixo",
        "iva_percent",
        'total_sem_desconto',
        'valor_imposto',
        'total_sem_imposto',
        "total"
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoCompra::class, 'documento_compra_id');
    }
}
