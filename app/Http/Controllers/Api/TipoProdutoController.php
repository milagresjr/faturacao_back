<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoProduto;
use Illuminate\Http\Request;

class TipoProdutoController extends Controller
{
    public function index()
    {
        // Return a list of all TipoProduto records
        $tiposProduto = TipoProduto::all();
        return response()->json($tiposProduto);
    }

    public function show($id)
    {
        // Return a single TipoProduto record by ID
        $tipoProduto = TipoProduto::findOrFail($id);
        return response()->json($tipoProduto);
    }

    public function store(Request $request)
    {
        // Validate and create a new TipoProduto record
        $validatedData = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
        ]);

        $tipoProduto = TipoProduto::create($validatedData);
        return response()->json($tipoProduto, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing TipoProduto record
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'estado' => 'sometimes|boolean',
        ]);

        $tipoProduto = TipoProduto::findOrFail($id);
        $tipoProduto->update($validatedData);
        return response()->json($tipoProduto);
    }

    public function destroy($id)
    {
        // Delete a TipoProduto record
        $tipoProduto = TipoProduto::findOrFail($id);
        $tipoProduto->delete();
        return response()->json(null, 204);
    }
}
