<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoAgt extends Model
{
    protected $table = 'configuracoes_agt';

    protected $fillable = [
        'empresa_id',
        'numero_validacao_software',
        'certificado_digital',
        'ambiente',
        'comunicacao_ativa',
    ];

    protected $casts = [
        'comunicacao_ativa' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
