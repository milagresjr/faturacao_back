<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MotivoIsencao;

class MotivoIsencaoController extends Controller
{
    public function index()
    {
        $motivosIsencao = MotivoIsencao::orderByDesc('id')->get();
        return response()->json($motivosIsencao);
    }

    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'codigo' => 'required|string|max:255',
            'motivo' => 'nullable|string',
            'texto' => 'nullable|string',
            'taxa' => 'nullable|numeric',
            'taxa_retorno' => 'nullable|numeric',
            'alteracao_manual' => 'boolean',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validatedData->errors()
            ], 422);
        }

        $data = $validatedData->validated();

        $motivoIsencao = MotivoIsencao::create($data);
        return response()->json($motivoIsencao, 201);
    }

    public function show($id)
    {
        $motivoIsencao = MotivoIsencao::findOrFail($id);
        return response()->json($motivoIsencao);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'codigo' => 'sometimes|required|string|max:255',
            'motivo' => 'sometimes|nullable|string',
            'texto' => 'sometimes|nullable|string',
            'taxa' => 'sometimes|nullable|numeric',
            'taxa_retorno' => 'sometimes|nullable|numeric',
            'alteracao_manual' => 'sometimes|boolean',
        ]);

        $motivoIsencao = MotivoIsencao::findOrFail($id);
        $motivoIsencao->update($validatedData);
        return response()->json($motivoIsencao);
    }

    public function destroy($id)
    {
        $motivoIsencao = MotivoIsencao::findOrFail($id);
        $motivoIsencao->delete();
        return response()->json(null, 204);
    }
}