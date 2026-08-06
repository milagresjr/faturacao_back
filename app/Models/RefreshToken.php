<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'utilizador_id',
        'token',
        'device_fingerprint',
        'ip_address',
        'user_agent',
        'expires_at',
        'revoked_at',
        'replaced_by_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function utilizador(): BelongsTo
    {
        return $this->belongsTo(Utilizador::class, 'utilizador_id');
    }

    public function isValid(): bool
    {
        return !$this->revoked_at && $this->expires_at->isFuture();
    }

    public function isRevokedButNotExpired(): bool
    {
        return (bool) $this->revoked_at && $this->expires_at->isFuture();
    }

    public function revoke(?string $replacedBy = null): void
    {
        $this->update([
            'revoked_at' => now(),
            'replaced_by_token' => $replacedBy,
        ]);
    }
}
