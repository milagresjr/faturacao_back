<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloPermissao extends Model
{
    protected $table = 'modulo_permissoes';

    protected $fillable = [
        'nome',
    ];

    public function permissoes()
    {
        return $this->hasMany(Permissao::class, 'modulo_id');
    }
}
