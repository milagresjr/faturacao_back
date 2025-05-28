<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategoria extends Model
{
    protected $table = 'sub_categorias';

    protected $fillable = [
        'nome',
        'descricao',
        'estado',
        'categoria_id',
        'empresa_id',
        'utilizador_id'
    ];

    public function categoria()
    {
        return $this->belongsTo(CategoriaProduto::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function utilizador()
    {
        return $this->belongsTo(Utilizador::class);
    }
}
