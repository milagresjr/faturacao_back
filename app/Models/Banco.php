<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banco extends Model
{
    protected $table = 'bancos';

    public $timestamps = false;

    protected $fillable = [
        'descricao',
        'sigla',
        'estado'
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

   /* public function contas()
    {
        return $this->hasMany(Conta::class, 'banco_id');
    }
    */ 
}
