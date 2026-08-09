<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigValidacaoProduto extends Model
{
    protected $table = 'config_validacao_produtos';

    protected $fillable = [
        'produto_id',
        'dias_alerta_precoce',
        'dias_alerta_critico'
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}