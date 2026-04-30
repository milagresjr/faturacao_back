<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDocumentoCompra extends Model
{
    protected $table = 'itens_documento_compra';

    protected $fillable = [
        'documento_compra_id',
        'produto_id',
        'produto_nome',
        'produto_codigo',
        'preco_custo',
        'descricao',
        'quantidade',
        'desconto_percent',
        'desconto_fixo',
        'iva_percent',
        'total_sem_desconto',
        'total_sem_imposto',
        'valor_imposto',
        'total',
        //LOTES
        'lote_id',
        'lote',
        'codigo_lote',
        'lote_codigo_barras',
        'lote_data_validade',
    ];

    public function documentos(): BelongsTo
    {
        return $this->belongsTo(DocumentoCompra::class, 'documento_compra_id');
    }
}
