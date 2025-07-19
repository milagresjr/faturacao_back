<?php

namespace App\Http\Middleware;

use App\Models\Utilizador;
use Closure;
use Illuminate\Http\Request;
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
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {

            $token = substr($authHeader, 7);

            $user = Utilizador::where('remember_token', $token)->first();

            if ($user) {
                
                // Injeta id_empresa
                $request->merge(['empresa_id' => $user->empresa_id]);

                return $next($request);
            }
        }

        return response()->json(['message' => 'Não autorizado'], 401);
    }
}
