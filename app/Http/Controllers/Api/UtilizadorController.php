<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\Utilizador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;

use function Laravel\Prompts\table;

class UtilizadorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $empresaId = $request['empresa_id'];

        $utilizadores = Utilizador::where('empresa_id', $empresaId)->get();

        return $utilizadores;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = validator($request->all(), [
            'nome_pessoal' => 'nullable|string|max:255',
            'nome_de_utilizador' => 'required|string|max:255|unique:utilizadores,nome_de_utilizador',
            'email' => 'required|email|max:255|unique:utilizadores,email',
            'senha' => 'required|string|min:6|confirmed',
            'telefone' => 'nullable|string|max:20',
            'nivel_acesso' => 'nullable|integer',
            'perfil_id' => 'nullable|integer',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $data = $request->all();

        return DB::transaction(function () use ($data) {
            try {
                return Utilizador::create([
                    'nome_pessoal' => $data['nome_pessoal'],
                    'nome_de_utilizador' => $data['nome_de_utilizador'],
                    'email' => $data['email'],
                    'senha' => Hash::make($data['senha']),
                    'telefone' => $data['telefone'] ?? null,
                    'nivel_acesso' => $data['nivel_acesso'] ?? 0,
                    'perfil_id' => $data['perfil_id'] ?? null,
                    'estado' => true,
                    'empresa_id' => $data['empresa_id'] ?? null,
                    'must_change_password' => true,
                ]);
                return response()->json($utilizador, 201);
            } catch (\Throwable $th) {
                return response()->json([
                    'message' => 'Erro ao criar utilizador',
                    'error' => $th->getMessage()
                ], 500);
            }
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $utilizador = Utilizador::find($id);

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador não encontrado'
            ], 404);
        }

        // ocultar senha antes de retornar
        $utilizador->makeHidden(['senha']);

        return response()->json($utilizador);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $utilizador = Utilizador::find($id);

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador não encontrado'
            ], 404);
        }

        $validated = validator($request->all(), [
            'nome_pessoal' => 'required|string|max:255',
            'nome_de_utilizador' => 'required|string|max:255|unique:utilizadores,nome_de_utilizador,' . $id . ',id',
            'email' => 'required|email|max:255|unique:utilizadores,email,' . $id . ',id',
            'senha' => 'nullable|string|min:6',
            'telefone' => 'nullable|string|max:20',
            'nivel_acesso' => 'nullable|integer',
            'perfil_id' => 'nullable|integer',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $data = $validated->validated();

        $utilizador = DB::transaction(function () use ($utilizador, $data) {
            if (isset($data['nome_pessoal'])) {
                $utilizador->nome_pessoal = $data['nome_pessoal'];
            }
            if (isset($data['nome_de_utilizador'])) {
                $utilizador->nome_de_utilizador = $data['nome_de_utilizador'];
            }
            if (isset($data['email'])) {
                $utilizador->email = $data['email'];
            }
            if (isset($data['nivel_acesso'])) {
                $utilizador->nivel_acesso = $data['nivel_acesso'];
            }
            if (array_key_exists('perfil_id', $data)) {
                $utilizador->perfil_id = $data['perfil_id'];
            }
            if (isset($data['telefone'])) {
                $utilizador->telefone = $data['telefone'];
            }
            if (!empty($data['senha'])) {
                $utilizador->senha = Hash::make($data['senha']);
            }

            $utilizador->save();

            return $utilizador;
        });

        $utilizador->makeHidden(['senha']);

        return response()->json($utilizador);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $utilizador = Utilizador::find($id);

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador não encontrado'
            ], 404);
        }

        DB::transaction(function () use ($utilizador) {
            $utilizador->delete();
        });

        return response()->json([
            'message' => 'Utilizador eliminado com sucesso'
        ]);
    }

    public function changeNewPassword(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'senha_atual' => ['required', 'string', 'min:8'],
            'nova_senha' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->senha_atual, $user->senha)) {
            return response()->json([
                'message' => 'Senha atual incorreta'
            ], 421);
        }

        DB::beginTransaction();

        try {

            $user->senha = $request->nova_senha;
            $user->must_change_password = false;
            $user->save();

            DB::commit();

            return response()->json(['message' => 'Senha alterada com sucesso']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao alterar a senha'], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'senha' => ['required', 'confirmed', 'min:8'],
            'utilizador_id' => ['nullable', 'exists:utilizadores,id'], // opcional
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Se vier utilizador_id → admin está alterando
            if ($request->utilizador_id) {
                $user = Utilizador::findOrFail($request->utilizador_id);

                $user->senha = $request->senha;
                $user->must_change_password = true; // força troca no próximo login
            } else {
                // Caso contrário → usuário logado
                $user = $request->user();

                $user->senha = $request->senha;
                $user->must_change_password = false; // opcional
            }

            $user->save();

            DB::commit();

            $cookieDomain = env('COOKIE_DOMAIN', 'app.localhost');
            $secure = env('APP_ENV') === 'production' && env('APP_DEBUG') !== 'true';

            $cookie = Cookie::make('must_change_password', (int)$user->must_change_password, 10080, '/', $cookieDomain, $secure, true, false, 'lax');

            return response()->json([
                'message' => 'Senha alterada com sucesso',
                'must_change_password' => (bool)$user->must_change_password
            ])->withCookie($cookie);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function sendCodePasswordReset(Request $request)
    {

        $validated = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $user = Utilizador::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Este e-mail não está registado no sistema.',
            ], 404);
        }

        // Gerar código de 6 dígitos
        $code = rand(100000, 999999);

        // Definir expiração (ex: 15 minutos)
        $expiresAt = Carbon::now()->addMinutes(15);

        try {
            // Salvar no banco (apaga anteriores)
            DB::table('password_resets_custom')->where('email', $user->email)->delete();
            DB::table('password_resets_custom')->insert([
                'email' => $user->email,
                'token' => $code,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Enviar o email
            Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code, $expiresAt));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erro ao enviar o código de recuperação',
                'error' => $th->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Código de recuperação enviado para o seu e-mail!.'
        ]);
    }

    public function verifyResetCode(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'email' => 'required|email',
            'codigo' => 'required|string|min:6|max:6',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $data = $validated->validated();

        $reset = DB::table('password_resets_custom')
            ->where('email', $data['email'])
            ->where('token', $data['codigo'])
            ->first();

        if (!$reset) {
            return response()->json(['message' => 'Código inválido.'], 400);
        }

        if (Carbon::now()->greaterThan($reset->expires_at)) {
            return response()->json(['message' => 'Código expirado. Solicite um novo código.'], 400);
        }

        DB::table('password_resets_custom')
            ->where('email', $data['email'])
            ->where('token', $data['codigo'])
            ->update(['verified_at' => now()]);

        return response()->json(['message' => 'Código verificado com sucesso.']);
    }

    public function resetNewPassword(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'email' => 'required|email|exists:utilizadores,email',
            "codigo" => "required|string|min:6|max:6",
            "nova_senha" => "required|string|min:6|confirmed"
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $data = $validated->validated();

        try {
            // Lógica para redefinir a senha
            $reset = DB::table('password_resets_custom')
                ->where('email', $data['email'])
                ->where('token', $data['codigo'])
                ->first();

            if (!$reset) {
                return response()->json(['message' => 'Código inválido.'], 400);
            }

            if (Carbon::now()->greaterThan($reset->expires_at)) {
                return response()->json(['message' => 'Código expirado. Solicite um novo código.'], 400);
            }

            if (!$reset->verified_at) {
                return response()->json(['message' => 'O código ainda não foi verificado.'], 400);
            }

            $userEmail = $reset->email ?? null;

            $user = Utilizador::where('email', $userEmail)->first();

            if (!$user) {
                return response()->json(['message' => 'Código de verificação inválido'], 404);
            }

            $user->senha = Hash::make($data['nova_senha']);
            $user->must_change_password = false;
            $user->save();

            // Remover o código usado
            DB::table('password_resets_custom')->where('email', $userEmail)->delete();

            return response()->json(['message' => 'Senha redefinida com sucesso'], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'error' => $th->getTraceAsString()
            ], 500);
        }
    }

    public function changeEstado(Request $request, string $id)
    {
        $utilizador = Utilizador::find($id);

        if (!$utilizador) {
            return response()->json([
                'message' => 'Utilizador não encontrado'
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Toggle do estado (se null/false -> true, se true -> false)
            $utilizador->estado = !$utilizador->estado;
            $utilizador->save();

            DB::commit();

            $utilizador->makeHidden(['senha']);

            $statusText = $utilizador->estado ? 'ativado' : 'suspenso';

            return response()->json([
                'message' => "Utilizador {$statusText} com sucesso",
                'utilizador' => $utilizador
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao atualizar o estado do utilizador',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $utilizador = $request->user()->load('perfil.permissoes');
        $utilizador->makeHidden(['senha']);
        return response()->json($utilizador);
    }
}
