<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caixa extends Model
{
    use SoftDeletes;

    protected $table = 'caixas';

    protected $fillable = [
        'nome',
        'localizacao',
        'tipo',
        'estado',
        'saldo_inicial',
        'saldo_atual',
        'data_abertura',
        'data_fechamento',
        'turno',
        'observacoes',
        'imprimir_abertura',
        'documento_predefinido',
        'aspecto',
        'metodo_impressao',
        'modelo_impressao',
        'impressao_papel',
        'modelo_email',
        'finalizar_avancado',
        'referencia_produtos',
        'precos_produtos',
        'modo_funcionamento',
        'listar_produtos',
        'grupo_precos',
        'permite_movimento_negativo',
        'permite_multiplos_usuarios',
        'usuario_id',
        'armazem_id',
        'empresa_id'
    ];

    public function armazem()
    {
        return $this->belongsTo(Armazem::class, 'armazem_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Utilizador::class, 'usuario_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
