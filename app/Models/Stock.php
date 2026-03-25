<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'id',
        'empresa_id',
        'produto_id',
        'armazem_id',
        'stock_atual',
        'stock_min',
        'stock_max',
        'stock_ideal',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class);
    }

    public function movimentos()
    {
        return $this->hasMany(MovimentoStock::class, 'stock_id');
    }
}
