<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index()
    {
        $empresas = Empresa::all();
        return response()->json($empresas);
    }

    public function show($id)
    {
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        return response()->json($empresa);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:empresas,email',
            'nif' => 'required|string|max:255|unique:empresas,nif',
            'telefone' => 'required|string|max:255',
            'morada' => 'required|string|max:255',
        ]);

        $empresa = Empresa::create($request->all());
        return response()->json($empresa, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing company
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:empresas,email,' . $id,
            'nif' => 'sometimes|required|string|max:255|unique:empresas,nif,' . $id,
            'telefone' => 'sometimes|required|string|max:255',
            'morada' => 'sometimes|required|string|max:255',
        ]);

        $empresa->update($request->all());
        return response()->json($empresa);
    }

    public function destroy($id)
    {
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $empresa->delete();
        return response()->json(['message' => 'Company deleted successfully']);
    }
}
