<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithRememberToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Tenta pegar token do header Authorization
        $authHeader = $request->header('Authorization');
        $token = null;

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        // 2. Se não tem no header, tenta pegar do cookie HttpOnly
        if (!$token) {
            $token = $request->cookie('token_softseven_fat');
        }

        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken) {
                // Access token expirado → 401 para o frontend renovar via refresh token
                if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
                    return response()->json(['message' => 'Não autorizado'], 401);
                }

                $user = $accessToken->tokenable;

                if ($user instanceof \App\Models\Utilizador) {
                    // Injeta id_empresa
                    $request->merge(['empresa_id' => $user->empresa_id]);
                    Auth::setUser($user);

                    $accessToken->forceFill(['last_used_at' => now()])->save();

                    return $next($request);
                }
            }
        }

        return response()->json(['message' => 'Não autorizado'], 401);
    }
}
