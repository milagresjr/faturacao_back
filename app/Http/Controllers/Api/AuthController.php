<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\Utilizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;

class AuthController extends Controller
{
    private const ACCESS_COOKIE = 'token_softseven_fat';
    private const REFRESH_COOKIE = 'refresh_token';
    private const MUST_CHANGE_PASSWORD_COOKIE = 'must_change_password';
    private const MUST_FILL_DATA_EMPRESA_COOKIE = 'must_fill_data_empresa';
    private const HAS_AUTH_COOKIE = 'has_auth';

    public function register(Request $request)
    {

        $request->validate([
            'nome_pessoal' => 'required|string',
            'nome_de_utilizador' => 'required|string|max:255|unique:utilizadores,nome_de_utilizador',
            'email' => 'required|string|email|max:255|unique:utilizadores,email',
            'nivel_acesso' => 'required|string',
            'senha' => 'required|string|min:8',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'perfil_id' => 'required|integer|exists:perfis,id',
        ]);

        $utilizador = Utilizador::create([
            'nome_pessoal' => $request->nome_pessoal,
            'nome_de_utilizador' => $request->nome_de_utilizador,
            'email' => $request->email,
            'senha' => $request->senha,
            'estado' => '1',
            'nivel_acesso' => $request->nivel_acesso,
            'empresa_id' => $request->empresa_id,
            'perfil_id' => $request->perfil_id,
        ]);

        if (!$utilizador) {
            return response()->json(['message' => 'User registration failed'], 500);
        }

        return response()->json(['message' => 'User registered successfully'], 201);
    }

    public function login(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'login' => 'required|string',
            'senha' => 'required|string',
            'remember_me' => 'sometimes|boolean',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validated->errors()], 422);
        }

        $login = $request->input('login');

        $utilizador = $this->loadUtilizadorCompleto()
            ->where(function ($q) use ($login) {
                $q->whereRaw('BINARY nome_de_utilizador = ?', [$login])
                    ->orWhereRaw('BINARY email = ?', [$login]);
            })
            ->first();

        if (!$utilizador || !password_verify($request->senha, $utilizador->senha)) {
            return response()->json([
                'message' => 'Credenciais inválidas'
            ], 401);
        }

        $rememberMe = $request->boolean('remember_me', false);

        // Access token: curto (15 min por padrão)
        $accessExpiration = Carbon::now()->addMinutes((int) config('autenticacao.access_token_minutes'));
        $token = $utilizador->createToken('auth_token', ['*'], $accessExpiration)->plainTextToken;

        // Refresh token: longo, com rotação. Duração depende do remember_me.
        $refreshDays = $rememberMe
            ? (int) config('autenticacao.refresh_token_days')
            : (int) config('autenticacao.refresh_token_session_days');

        $fingerprint = $this->deviceFingerprint($request);

        // Reutilizar o mesmo device: revoga o refresh anterior para evitar acumulação
        $utilizador->refreshTokens()
            ->where('device_fingerprint', $fingerprint)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $refreshToken = $utilizador->issueRefreshToken([
            'device_fingerprint' => $fingerprint,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        $refreshMinutes = $refreshDays * 24 * 60;

        return $this->buildAuthResponse(
            $utilizador,
            $token,
            $refreshToken,
            $refreshMinutes,
            'Login successful'
        );
    }

    /**
     * Renova o access token usando o refresh token (com rotação).
     * Rota pública — autentica-se exclusivamente pelo cookie refresh_token.
     */
    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token não fornecido'], 401);
        }

        $stored = RefreshToken::where('token', hash('sha256', $refreshToken))->first();

        if (!$stored || !$stored->isValid()) {
            // Deteção de roubo: refresh token revogado mas ainda não expirado
            if ($stored && $stored->isRevokedButNotExpired() && $stored->utilizador) {
                $stored->utilizador->revokeAllRefreshTokens();
            }

            return response()->json(['message' => 'Sessão expirada. Inicie sessão novamente.'], 401);
        }

        // Novo access token
        $accessExpiration = Carbon::now()->addMinutes((int) config('autenticacao.access_token_minutes'));
        $token = $stored->utilizador->createToken('auth_token', ['*'], $accessExpiration)->plainTextToken;

        // Modo SSR (fetch server-to-server, p.ex. render de páginas Next.js):
        // NÃO rotaciona o refresh token — a cookie do browser mantém-se válida.
        // Devolve apenas um novo access token + utilizador.
        if ($request->header('X-Softseven-SSR') === 'true') {
            return response()->json([
                'message' => 'Token renovado',
                'utilizador' => $this->loadUtilizadorCompleto()->find($stored->utilizador_id),
                'token_type' => 'Bearer',
                'token' => $token,
            ], 200);
        }

        // ROTAÇÃO: invalida o refresh token atual e emite um novo
        $newRefreshToken = $stored->utilizador->issueRefreshToken([
            'device_fingerprint' => $this->deviceFingerprint($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        $stored->revoke(hash('sha256', $newRefreshToken));

        // Mantém a duração original do refresh token na renovação
        $refreshMinutes = (int) max(1, now()->diffInMinutes($stored->expires_at));

        $utilizador = $this->loadUtilizadorCompleto()->find($stored->utilizador_id);

        // Preserva os valores atuais dos cookies (não re-aplica os da BD),
        // para não anular escolhas como "Preencher mais tarde".
        $mustChangePassword = (int) ($request->cookie(self::MUST_CHANGE_PASSWORD_COOKIE) ?? $utilizador->must_change_password);
        $mustFillDataEmpresa = (int) ($request->cookie(self::MUST_FILL_DATA_EMPRESA_COOKIE) ?? $utilizador->must_fill_data_empresa);

        return $this->buildAuthResponse(
            $utilizador,
            $token,
            $newRefreshToken,
            $refreshMinutes,
            'Token renovado com sucesso',
            $mustChangePassword,
            $mustFillDataEmpresa
        );
    }

    public function logout(Request $request)
    {
        // Não depende de guard ativo: funciona mesmo com access token expirado.
        // Revoga o access token (se ainda existir) usando o valor do header/cookie.
        $rawToken = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $rawToken = substr($authHeader, 7);
        }
        if (!$rawToken) {
            $rawToken = $request->cookie(self::ACCESS_COOKIE);
        }

        if ($rawToken) {
            \Laravel\Sanctum\PersonalAccessToken::findToken($rawToken)?->delete();
        }

        // Revoga o refresh token da sessão atual
        $refreshToken = $request->cookie(self::REFRESH_COOKIE);
        if ($refreshToken) {
            RefreshToken::where('token', hash('sha256', $refreshToken))->update(['revoked_at' => now()]);
        }

        return $this->expireAuthCookies();
    }

    /**
     * Response de autenticação (login/refresh) com todos os cookies.
     *
     * $mustChangePassword / $mustFillDataEmpresa opcionais:
     * - login: null → usa os valores da BD (sessão nova)
     * - refresh: preserva os valores atuais dos cookies (não re-aplica os da BD,
     *   para não anular decisões do utilizador como "Preencher mais tarde")
     */
    private function buildAuthResponse(Utilizador $utilizador, string $accessToken, string $refreshToken, int $refreshMinutes, string $message = 'Login successful', ?int $mustChangePassword = null, ?int $mustFillDataEmpresa = null)
    {
        $cookieDomain = $this->cookieDomain();
        $secure = $this->cookieSecure();
        $samesite = $this->cookieSameSite();

        $mustChangePassword ??= (int) $utilizador->must_change_password;
        $mustFillDataEmpresa ??= (int) $utilizador->must_fill_data_empresa;

        $accessCookie = Cookie::make(self::ACCESS_COOKIE, $accessToken, (int) config('autenticacao.access_token_minutes'), '/', $cookieDomain, $secure, true, false, $samesite);
        $refreshCookie = Cookie::make(self::REFRESH_COOKIE, $refreshToken, $refreshMinutes, '/', $cookieDomain, $secure, true, false, $samesite);
        $mustChangePasswordCookie = Cookie::make(self::MUST_CHANGE_PASSWORD_COOKIE, $mustChangePassword, $refreshMinutes, '/', $cookieDomain, $secure, true, false, $samesite);
        $mustFillDataEmpresaCookie = Cookie::make(self::MUST_FILL_DATA_EMPRESA_COOKIE, $mustFillDataEmpresa, $refreshMinutes, '/', $cookieDomain, $secure, true, false, $samesite);
        $hasAuthCookie = Cookie::make(self::HAS_AUTH_COOKIE, '1', $refreshMinutes, '/', $cookieDomain, $secure, false, false, $samesite);

        return response()->json([
            'message' => $message,
            'utilizador' => $utilizador,
            'token_type' => 'Bearer',
            'token' => $accessToken,
        ], 200)
            ->withCookie($accessCookie)
            ->withCookie($refreshCookie)
            ->withCookie($mustChangePasswordCookie)
            ->withCookie($mustFillDataEmpresaCookie)
            ->withCookie($hasAuthCookie);
    }

    private function expireAuthCookies()
    {
        $cookieDomain = $this->cookieDomain();

        $accessCookie = Cookie::forget(self::ACCESS_COOKIE, '/', $cookieDomain);
        $refreshCookie = Cookie::forget(self::REFRESH_COOKIE, '/', $cookieDomain);
        $mustChangePasswordCookie = Cookie::forget(self::MUST_CHANGE_PASSWORD_COOKIE, '/', $cookieDomain);
        $mustFillDataEmpresaCookie = Cookie::forget(self::MUST_FILL_DATA_EMPRESA_COOKIE, '/', $cookieDomain);
        $hasAuthCookie = Cookie::forget(self::HAS_AUTH_COOKIE, '/', $cookieDomain);

        return response()->json(['message' => 'Logged out successfully'], 200)
            ->withCookie($accessCookie)
            ->withCookie($refreshCookie)
            ->withCookie($mustChangePasswordCookie)
            ->withCookie($mustFillDataEmpresaCookie)
            ->withCookie($hasAuthCookie);
    }

    private function loadUtilizadorCompleto()
    {
        return Utilizador::with([
            'empresa:id,nome,email,nif,telefone,morada,regime_tributario',
            'perfil:id,nome',
            'perfil.permissoes:id,nome',
        ]);
    }

    private function deviceFingerprint(Request $request): string
    {
        return hash('sha256', ($request->header('User-Agent') ?? '') . '|' . ($request->ip() ?? ''));
    }

    private function cookieDomain(): string
    {
        return (string) config('autenticacao.cookie_domain', 'app.localhost');
    }

    private function cookieSecure(): bool
    {
        // Se SameSite=none, secure DEVE ser true (o browser rejeita o cookie caso contrário)
        if ($this->cookieSameSite() === 'none') {
            return true;
        }

        return (bool) config('autenticacao.cookie_secure', false);
    }

    private function cookieSameSite(): string
    {
        return (string) config('autenticacao.cookie_same_site', 'lax');
    }
}
