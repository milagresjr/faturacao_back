<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Caixa;

class CaixaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Logic to list all caixas
        $caixas = Caixa::all();
        return response()->json($caixas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logic to create a new caixa
        $validatedData = $request->validate([
            'nome' => 'required|string|max:150',
            'localizacao' => 'nullable|string|max:255',
            'tipo' => 'required|in:fisico,virtual,movel',
            'estado' => 'required|in:aberto,fechado,inativo',
            'saldo_inicial' => 'required|numeric',
            'saldo_atual' => 'required|numeric',
            // Add other fields validation as necessary
        ]);
        $caixa = Caixa::create($validatedData);
        return response()->json($caixa, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Logic to show a specific caixa
        $caixa = Caixa::findOrFail($id);
        return response()->json($caixa);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Logic to update a specific caixa
        $caixa = Caixa::findOrFail($id);
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:150',
            'localizacao' => 'nullable|string|max:255',
            'tipo' => 'sometimes|required|in:fisico,virtual,movel',
            'estado' => 'sometimes|required|in:aberto,fechado,inativo',
            'saldo_inicial' => 'sometimes|required|numeric',
            'saldo_atual' => 'sometimes|required|numeric',
            // Add other fields validation as necessary
        ]);
        $caixa->update($validatedData);
        return response()->json($caixa);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Logic to delete a specific caixa
        $caixa = Caixa::findOrFail($id);
        $caixa->delete();
        return response()->json(null, 204);
    }
}
