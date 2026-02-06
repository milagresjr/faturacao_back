<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            'nome_pessoal' => 'required|string|max:255',
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

    public function changePassword(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'senha' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $user = $request->user();
     
        DB::beginTransaction();

        try {

            $user->senha = $request->senha;
            $user->must_change_password = false;
            $user->save();

            DB::commit();

            return response()->json(['message' => 'Senha alterada com sucesso']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao alterar a senha'], 500);
        }
    }
}
