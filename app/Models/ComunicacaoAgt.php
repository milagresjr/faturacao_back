<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicacaoAgt extends Model
{
    protected $table = 'comunicacoes_agt';

    protected $fillable = [
        'empresa_id',
        'documento_id',
        'tipo_comunicacao',
        'status',
        'request_payload',
        'response_payload',
        'codigo_erro',
        'codigo_validacao_agt',
        'tentativas',
        'ultima_tentativa',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }
}
