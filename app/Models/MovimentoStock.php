<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentoStock extends Model
{
    protected $table = 'movimentos_stock';

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
        'documento_relacionado_id',
    ];

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

    /**
     * Usuário responsável (opcional)
     */
    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }
}
