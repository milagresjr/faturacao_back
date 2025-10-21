<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    protected $table = "documentos";

    protected $fillable = [
        'tipo_nome',
        'tipo_sigla',
        'tipo_cor',

        'num_fatura',
        'via',

        'empresa_id',
        'empresa_logo',
        'empresa_nome',
        'empresa_nif',
        'empresa_telefone',
        'empresa_email',
        'empresa_endereco',

        'cliente_id',
        'cliente_nome',
        'cliente_nif',
        'cliente_telefone',
        'cliente_email',
        'cliente_endereco',

        'caixa',
        'data_emissao',
        'data_vencimento',

        'forma_pagamento',
        'movimenta_stock',
        'descricao_iva',

        'taxa_iva',
        'valor_iva',
        'retencao',

        'desconto_total',
        'valor_transporte',

        'total_sem_desconto',
        'total_impostos',
        'total_geral',
        'troco',

        'motivo_emissao_nota_credito',

        'hash',

        'estado',

        'utilizador_id',
        'utilizador',

        'local_entrega',
        'data_recepcao',
        'observacoes',
        'paga',
        'valor_pago',

        'tipo_documento',

        'info_guia_id',

        'documento_origem_id',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ItemDocumento::class, 'documento_id', 'id');
    }

    public function meiosPagamento(): HasMany
    {
        return $this->hasMany(MeioPagamentoDocumento::class, 'documento_id', 'id');
    }

    public function impostosDocumento(): HasMany
    {
        return $this->hasMany(ImpostoDocumento::class, 'documento_id', 'id');
    }

    public function infoGuia()
    {
        return $this->belongsTo(InfoGuia::class, 'info_guia_id');
    }

    public function documentoOrigem()
    {
        return $this->belongsTo(Documento::class, 'documento_origem_id');
    }

    /**
     * Documentos que este documento referencia
     */
    public function documentosRelacionados()
    {
        return $this->belongsToMany(
            Documento::class,
            'documento_relacoes',
            'documento_relacionado_id', // chave no documento relacionado
            'documento_id', // chave neste documento
        )->withPivot('tipo_relacao')
            ->withTimestamps();
    }

    /**
     * Documentos que fazem referência a este documento
     */
    public function relacionadoEm()
    {
        return $this->belongsToMany(
            Documento::class,
            'documento_relacoes',
            'documento_id', // chave no documento original
            'documento_relacionado_id', // chave neste documento
        )->withPivot('tipo_relacao')
            ->withTimestamps();
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'id');
    }
}
