<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoCompra extends Model
{
    protected $table = 'documentos_compra';

    protected $fillable = [
        'tipo_nome',
        'tipo_sigla',
        'tipo_cor',

        'num_fatura',
        'via',

        'armazem_id',

        'empresa_id',
        'empresa_nome',
        'empresa_nif',
        'empresa_telefone',
        'empresa_email',
        'empresa_endereco',

        'fornecedor_id',
        'fornecedor_nome',
        'fornecedor_nif',
        'fornecedor_telefone',
        'fornecedor_email',
        'fornecedor_endereco',

        'data_fatura',
        'data_vencimento',
        'obs_pagamento',

        'desconto_total',
        'taxa_iva',
        'valor_fatura',
        'retencao',

        'total_sem_desconto',
        'total_impostos',
        'total_geral',
        'troco',

        'local_entrega',
        'data_recepcao',
        'observacoes',
        'paga',
        'valor_pago',

        'hash',

        'utilizador_id',
        'utilizador',

    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ItemDocumentoCompra::class, 'documento_compra_id', 'id');
    }

    public function otherItens(): HasMany
    {
        return $this->hasMany(OtherItensDocumentoCompra::class, 'documento_compra_id', 'id');
    }

    public function impostosDocumento(): HasMany
    {
        return $this->hasMany(ImpostoDocumentoCompra::class, 'documento_compra_id', 'id');
    }

    public function movimentosStock()
    {
        return $this->morphMany(MovimentoStock::class, 'documento');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoDocumentoCompra::class, 'documento_compra_id');
    }
}
