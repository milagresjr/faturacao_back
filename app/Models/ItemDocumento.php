<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemDocumento extends Model
{
    protected $table = "itens_documento";

    protected $fillable = [
        "documento_id",
        "produto_id",
        "produto_nome",
        "produto_codigo",
        "preco_unitario",
        "descricao",
        "quantidade",
        "desconto_percent",
        "desconto_fixo",
        "imposto_taxa_id",
        "iva_percent",
        "codigo_iva",
        "motivo_isencao",
        "motivo_isencao_id",
        "total_sem_desconto",
        "total",
        "tipo_id",
        "detalhes_lote",
        "data_validade",
        "codigo_lote",
        "lote_id",
    ];

    protected $casts = [
        "detalhes_lote" => "array",
        "data_validade" => "date",
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, "documento_id");
    }

    public function tipoIva()
    {
        return $this->belongsTo(TipoTaxaIva::class, "imposto_taxa_id", "id");
    }
}
