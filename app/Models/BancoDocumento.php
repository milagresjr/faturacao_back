<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancoDocumento extends Model
{
    protected $table = 'bancos_documento';

    protected $fillable = [
        'documento_id',
        'sigla',
        'descricao',
        'numero_conta',
        'iban',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'sigla', 'sigla');
    }
}
