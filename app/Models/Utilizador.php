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
        'telefone',
        'nivel_acesso',
        'remember_token',
        'estado',
        'perfil_id',
        'empresa_id',
        'must_change_password',
        'must_fill_data_empresa'
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

    public function temPermissao($permissao)
    {
        return $this->perfil
            ->permissoes
            ->contains('nome', $permissao);
    }

    public function isAdmin()
    {
        return $this->nivel_acesso === 'admin';
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'utilizador_id');
    }

    /**
     * Emite um refresh token com rotação e devolve o valor em texto simples
     * (apenas o hash sha256 fica persistido na base de dados).
     */
    public function issueRefreshToken(array $context = []): string
    {
        $plain = \Illuminate\Support\Str::random(64);

        $this->refreshTokens()->create([
            'token' => hash('sha256', $plain),
            'device_fingerprint' => $context['device_fingerprint'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'expires_at' => now()->addDays((int) config('autenticacao.refresh_token_days')),
        ]);

        return $plain;
    }

    /**
     * Revoga todos os refresh tokens do utilizador (ex.: deteção de roubo).
     */
    public function revokeAllRefreshTokens(): void
    {
        $this->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
