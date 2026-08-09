<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moeda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MoedaController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $moedaQuery = Moeda::query();

        if ($search) {
            $moedaQuery->where(function ($q) use ($search) {
                $q->where('nome', 'like', '%' . $search . '%')
                    ->orWhere('codigo', 'like', '%' . $search . '%');
            });
        }

        $moedas = $moedaQuery
            ->where('empresa_id', $idEmpresa)
            ->orderByDesc('id')->paginate($per_page);

        return response()->json($moedas);
    }

    public function show($id)
    {
        $moeda = Moeda::findOrFail($id);
        return response()->json($moeda);
    }

    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'codigo' => 'required|string|max:3|unique:moedas,codigo,NULL,id,empresa_id,',
            'nome' => 'required|string|max:255',
            'simbolo' => 'nullable|string|max:10',
            'casas_decimais' => 'nullable|integer|min:0|max:4',
            'predefinida' => 'boolean',
            'estado' => 'boolean',
            'empresa_id' => 'required|exists:empresas,id',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validatedData->errors()
            ], 422);
        }
        $data = $validatedData->validated();

        if (Moeda::where('codigo', $data['codigo'])->where('empresa_id', $data['empresa_id'])->exists()) {
            return response()->json([
                'message' => 'Já existe uma moeda com este código.',
            ], 409);
        }

        $moeda = Moeda::create($data);

        // Se for marcada como predefinida, limpar as outras da empresa
        if (!empty($data['predefinida'])) {
            Moeda::where('empresa_id', $data['empresa_id'])
                ->where('id', '!=', $moeda->id)
                ->update(['predefinida' => false]);
        }

        return response()->json($moeda, 201);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'codigo' => 'sometimes|required|string|max:3',
            'nome' => 'sometimes|required|string|max:255',
            'simbolo' => 'sometimes|nullable|string|max:255',
            'casas_decimais' => 'sometimes|nullable|integer|min:0|max:4',
            'predefinida' => 'sometimes|boolean',
            'estado' => 'sometimes|boolean',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
        ]);

        $moeda = Moeda::findOrFail($id);

        $moedaExistente = Moeda::where('codigo', $validatedData['codigo'])
            ->where('empresa_id', $moeda->empresa_id)
            ->where('id', '!=', $id)
            ->first();

        if ($moedaExistente) {
            return response()->json([
                'message' => 'Já existe uma moeda com este código.',
            ], 409);
        }

        $moeda->update($validatedData);

        if (!empty($validatedData['predefinida'])) {
            Moeda::where('empresa_id', $moeda->empresa_id)
                ->where('id', '!=', $moeda->id)
                ->update(['predefinida' => false]);
        }

        return response()->json($moeda);
    }

    public function destroy($id)
    {
        $moeda = Moeda::findOrFail($id);
        $moeda->delete();
        return response()->json(null, 204);
    }
}