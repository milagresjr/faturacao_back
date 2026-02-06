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
        'enviado_em'
    ];
}
