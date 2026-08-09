<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaStock extends Model
{
    protected $table = 'alertas_stock';

    protected $fillable = [
        'stock_id',
        'produto_id',
        'armazem_id',
        'stock_atual',
        'sms_enviado',
        'empresa_id',
        'enviado_em',
        'lida',
        'data_leitura',
        'lida_por'
    ];

    protected $casts = [
        'sms_enviado' => 'boolean',
        'lida' => 'boolean',
        'enviado_em' => 'datetime',
        'data_leitura' => 'datetime'
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function armazem()
    {
        return $this->belongsTo(Armazem::class, 'armazem_id');
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }
}
