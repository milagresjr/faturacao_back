<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentoStock extends Model
{
    protected $table = 'movimentos_stock';

    protected $appends = ['tipo_documento'];

    protected $fillable = [
        'id',
        'armazem_id',
        'produto_id',
        'quantidade',
        'operacao',
        'observacao',
        'utilizador_id',
        'origem_movimento',
        'armazem_origem_id',
        'armazem_destino_id',
    ];

    public function getTipoDocumentoAttribute()
    {
        return match ($this->documento_type) {
            Documento::class => 'VENDA',
            DocumentoCompra::class => 'COMPRA',
            DocumentoInterno::class => 'INTERNO',
            default => 'DESCONHECIDO',
        };
    }


    /**
     * Relacionamento com Produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class);
    }

    public function documento()
    {
        return $this->morphTo();
    }

    /**
     * Usuário responsável (opcional)
     */
    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }
}
