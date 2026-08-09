<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxaCambio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaxaCambioController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $per_page = $request->input('per_page', 10);

        $taxaQuery = TaxaCambio::query();
        $taxaQuery->where('empresa_id', $idEmpresa);

        if ($request->has('moeda_id')) {
            $taxaQuery->where('moeda_id', $request->input('moeda_id'));
        }

        $taxas = $taxaQuery
            ->with('moeda')
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json($taxas);
    }

    public function show($id)
    {
        $taxaCambio = TaxaCambio::with('moeda')->findOrFail($id);
        return response()->json($taxaCambio);
    }

    public function store(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'moeda_id' => 'required|exists:moedas,id',
            'taxa' => 'required|numeric|min:0',
            'data' => 'nullable|date',
            'fonte' => 'nullable|string|in:manual,banco',
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

        $taxaCambio = TaxaCambio::create($data);
        return response()->json($taxaCambio->load('moeda'), 201);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'moeda_id' => 'sometimes|required|exists:moedas,id',
            'taxa' => 'sometimes|required|numeric|min:0',
            'data' => 'sometimes|nullable|date',
            'fonte' => 'sometimes|nullable|string|in:manual,banco',
            'estado' => 'sometimes|boolean',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
        ]);

        $taxaCambio = TaxaCambio::findOrFail($id);
        $taxaCambio->update($validatedData);
        return response()->json($taxaCambio->load('moeda'));
    }

    public function destroy($id)
    {
        $taxaCambio = TaxaCambio::findOrFail($id);
        $taxaCambio->delete();
        return response()->json(null, 204);
    }
}