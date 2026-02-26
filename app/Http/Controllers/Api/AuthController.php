<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
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
            'nome_de_utilizador' => 'required|string',
            'senha' => 'required|string',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validated->errors()], 422);
        }

        $utilizador = Utilizador::with(['empresa:id,nome,email,nif,telefone,morada,regime_tributario', 'perfil:id,nome', 'perfil.permissoes:id,nome'])
            ->whereRaw('BINARY nome_de_utilizador = ?', [$request->nome_de_utilizador])->first();
    
        if (!$utilizador || !password_verify($request->senha, $utilizador->senha)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $utilizador->createToken('auth_token')->plainTextToken;

        $utilizador->remember_token = $token;
        $utilizador->save();

        return response()->json([
            'message' => 'Login successful',
            'utilizador' => $utilizador,
            'token_type' => 'Bearer',
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}
