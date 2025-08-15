<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoRelacoes extends Model
{
    protected $table = 'documento_relacoes';

    protected $fillable = [
        'documento_id',
        'documento_relacionado_id',
        'tipo_relacao',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function documentoRelacionado()
    {
        return $this->belongsTo(Documento::class, 'documento_relacionado_id');
    }
}
