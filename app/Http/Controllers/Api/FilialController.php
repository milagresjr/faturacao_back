<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FilialController extends Controller
{
    public function index()
    {
        return response()->json(Filial::all(), 200);
    }

    public function show($id)
    {
        $filial = Filial::find($id);
        if (!$filial) {
            return response()->json(['error' => 'Filial not found'], 404);
        }
        return response()->json($filial, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'telefone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'endereco' => 'required|string|max:255',
            'nif' => 'required|string|max:50',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filial = Filial::create($request->all());
        return response()->json($filial, 201);
    }

    public function update(Request $request, $id)
    {
        $filial = Filial::find($id);
        if (!$filial) {
            return response()->json(['error' => 'Filial not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'telefone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|max:255',
            'endereco' => 'sometimes|required|string|max:255',
            'nif' => 'sometimes|required|string|max:50',
            'estado' => 'sometimes|required|boolean',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filial->update($request->all());
        return response()->json($filial, 200);
    }

    public function destroy($id)
    {
        $filial = Filial::find($id);
        if (!$filial) {
            return response()->json(['error' => 'Filial not found'], 404);
        }

        $filial->delete();
        return response()->json(['message' => 'Filial deleted successfully'], 200);
    }
}
