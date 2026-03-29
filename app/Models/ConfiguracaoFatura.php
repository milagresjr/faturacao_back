<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoFatura extends Model
{
    protected $table = 'configuracoes_fatura';

    protected $appends = ['logo_url'];

    protected $fillable = [
        'empresa_id',

        // Dados da empresa
        'nome_empresa',
        'nif',
        'email',
        'telefone',
        'endereco',
        'website',

        // Cliente
        'endereco_cliente',

        // Identidade visual
        'logo',

        // Layout
        'template',

        // Conteúdo
        'rodape',

        // Extras
        'mostrar_utilizador',
        'mostrar_logo',
        'mostrar_nif',
        'mostrar_rodape',
    ];

    protected $casts = [
        'nome_empresa' => 'boolean',
        'nif' => 'boolean',
        'email' => 'boolean',
        'telefone' => 'boolean',
        'endereco' => 'boolean',
        'website' => 'boolean',
        'endereco_cliente' => 'boolean',
        'mostrar_utilizador' => 'boolean',
        'mostrar_logo' => 'boolean',
        'mostrar_nif' => 'boolean',
        'mostrar_rodape' => 'boolean',
    ];


    // Acessor para obter a URL completa do logo
    public function getLogoUrlAttribute()
    {
        return $this->logo ? asset('storage/logos-fatura/' . $this->logo) : null;
    }

    // Relacionamento
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
