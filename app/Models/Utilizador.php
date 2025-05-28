<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilizador extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UtilizadorFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'utilizadores';

    protected $fillable = [
        'nome_pessoal',
        'nome_de_utilizador',
        'email',
        'senha',
        'nivel_acesso',
        'estado',
        'perfil_id',
        'empresa_id',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'senha' => 'hashed',
        ];
    } 

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
    
    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    public function isAdmin()
    {
        return $this->nivel_acesso === 'admin';
    }
}
