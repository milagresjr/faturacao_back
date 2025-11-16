<?php

namespace App\Http\Controllers\Api;

use App\Models\Unidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class UnidadeController extends Controller
{
    /**
     * 📜 Lista todas as unidades
     */
    public function index()
    {
        return response()->json(Unidade::all(), 200);
    }

    /**
     * ➕ Cria uma nova unidade
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'descricao' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:10',
            'casas_decimais' => 'required|integer|min:0|max:5',
            'predefinida' => 'nullable|integer',
        ]);

        if ($validated->fails()) {
            return response([
                "message" => "Erro de Validacao",
                "errors" => $validated->errors(),
            ]);
        }

        $data = $validated->validated();

        $unidade = Unidade::create($data);

        return response()->json($unidade, 201);
    }

    /**
     * 👁️ Mostra uma unidade específica
     */
    public function show($id)
    {
        $unidade = Unidade::find($id);

        if (!$unidade) {
            return response()->json(['message' => 'Unidade não encontrada'], 404);
        }

        return response()->json($unidade, 200);
    }

    /**
     * ✏️ Atualiza uma unidade
     */
    public function update(Request $request, $id)
    {
        $unidade = Unidade::find($id);

        if (!$unidade) {
            return response()->json(['message' => 'Unidade não encontrada'], 404);
        }

        $validated = validator::make($request->all(), [
            'descricao' => 'sometimes|required|string|max:255',
            'sigla' => 'nullable|string|max:10',
            'casas_decimais' => 'sometimes|required|integer|min:0|max:5',
        ]);

        if ($validated->fails()) {
            return response([
                "message" => "Erro de Validacao",
                "errors" => $validated->errors(),
            ]);
        }

        $data = $validated->validated();

        $unidade->update($data);

        return response()->json($unidade, 200);
    }

    /**
     * 🗑️ Remove uma unidade
     */
    public function destroy($id)
    {
        $unidade = Unidade::find($id);

        if (!$unidade) {
            return response()->json(['message' => 'Unidade não encontrada'], 404);
        }

        //Impede a exclusão da unidade predefinida
        if ($unidade->predefinida === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir a unidade predefinida.',
            ], 400);
        }

        $unidade->delete();

        return response()->json(['message' => 'Unidade removida com sucesso'], 200);
    }

    public function definirComoPredefinida($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'ID inválido'], 400);
        }

        $unidade = Unidade::find($id);

        if (!$unidade) {
            return response()->json(['message' => 'Unidade não encontrada'], 404);
        }

        if ((int) $unidade->predefinida === 1) {
            return response()->json(['message' => 'Unidade já é predefinida', 'unidade' => $unidade], 200);
        }

        DB::transaction(function () use ($unidade) {
            Unidade::query()->where('predefinida', 1)->update(['predefinida' => 0]);
            $unidade->predefinida = 1;
            $unidade->save();
        });

        $unidade->refresh();

        return response()->json(['message' => 'Unidade marcada como predefinida', 'unidade' => $unidade], 200);
    }
}
