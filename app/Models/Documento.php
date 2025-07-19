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

        'hash',
        'utilizador_id',
        'utilizador'
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(ItemDocumento::class, 'documento_id', 'id');
    }
}
