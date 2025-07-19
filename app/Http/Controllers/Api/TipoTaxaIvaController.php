<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoTaxaIva;
use Illuminate\Http\Request;

class TipoTaxaIvaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Return a list of all tipo_taxa_iva records
        $tiposTaxaIva = TipoTaxaIva::all();
        return response()->json($tiposTaxaIva);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|unique:tipos_taxa_iva,codigo',
            'descricao' => 'required|string',
            'taxa' => 'required|numeric|min:0'
        ], [
            'codigo.required' => 'O campo código é obrigatório.',
            'descricao.required' => 'O campo descrição é obrigatório.',
            'taxa.required' => 'O campo taxa é obrigatório.',
            'taxa.numeric' => 'A taxa deve ser um número.'
        ]);

        $tipoTaxaIva = TipoTaxaIva::create($validated);

        return response()->json($tipoTaxaIva, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tipoTaxaIva = TipoTaxaIva::find($id);

        if (!$tipoTaxaIva) {
            return response()->json(['message' => 'Tipo de Taxa IVA não encontrado'], 404);
        }

        return response()->json($tipoTaxaIva);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $tipoTaxaIva = TipoTaxaIva::find($id);

        if (!$tipoTaxaIva) {
            return response()->json(['message' => 'Tipo de Taxa IVA não encontrado'], 404);
        }

        $validated = $request->validate([
            'codigo' => 'sometimes|required|string|unique:tipos_taxa_iva,codigo,' . $id,
            'descricao' => 'sometimes|required|string',
            'taxa' => 'sometimes|required|numeric|min:0'
        ]);

        $tipoTaxaIva->update($validated);

        return response()->json($tipoTaxaIva);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tipoTaxaIva = TipoTaxaIva::find($id);

        if (!$tipoTaxaIva) {
            return response()->json(['message' => 'Tipo de Taxa IVA não encontrado'], 404);
        }

        $tipoTaxaIva->delete();

        return response()->json(['message' => 'Tipo de Taxa IVA deletado com sucesso'], 204);
    }
}
