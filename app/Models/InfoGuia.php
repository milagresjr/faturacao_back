<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoGuia extends Model
{
    protected $table = 'info_guias';

    protected $fillable = [
        'marca',
        'matricula',
        'local_origem',
        'local_destino',
        'data_origem',
        'data_destino',
    ];

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'info_guia_id');
    }
}
