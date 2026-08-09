<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacaoValidade extends Model
{
    protected $table = 'notificacoes_validade';

    protected $fillable = [
        'lote_id',
        'utilizador_id',
        'tipo',
        'nivel',
        'mensagem',
        'dias_restantes',
        'quantidade_afetada',
        'data_envio',
        'lida',
        'data_leitura',
        'lida_por'
    ];

    protected $casts = [
        'lida' => 'boolean',
        'data_envio' => 'datetime',
        'data_leitura' => 'datetime'
    ];

    public function lote()
    {
        return $this->belongsTo(LoteProduto::class, 'lote_id');
    }

    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

    public function lidaPor()
    {
        return $this->belongsTo(Utilizador::class, 'lida_por');
    }
}