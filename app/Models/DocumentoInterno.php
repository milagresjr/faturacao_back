<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoInterno extends Model
{
    protected $table = "documentos_interno";

    protected $fillable = [
        "tipo_nome",
        "tipo_sigla",
        "tipo_cor",
        "num_fatura",
        "via",
        "vezes_impresso",
        "empresa_id",
        "empresa_nome",
        "empresa_nif",
        "empresa_telefone",
        "empresa_email",
        "empresa_endereco",
        "caixa",
        "data_emissao",
        "data_vencimento",
        "forma_pagamento",
        "movimenta_stock",
        "descricao_iva",
        "desconto_total",
        "taxa_iva",
        "valor_iva",
        "retencao",
        "valor_transporte",
        "total_sem_desconto",
        "total_impostos",
        "total_geral",
        "troco",
        "hash",
        "utilizador_id",
        "utilizador",
        "tipo_documento",
        "documento_origem_id",
        "armazem_origem_id",
        "armazem_destino_id",
        "armazem_origem",
        "armazem_destino",
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ItensDocumentoInterno::class);
    }

    public function movimentosStock()
    {
        return $this->morphMany(MovimentoStock::class, 'documento');
    }
    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

}
