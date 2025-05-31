<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Fornecedor;
use Illuminate\Support\Facades\Validator;

class FornecedorController extends Controller
{
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $fornecedorQuery = Fornecedor::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $fornecedorQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all Marca records
        $fornecedores = $fornecedorQuery
        ->orderByDesc('id')->paginate($per_page);

        return response()->json($fornecedores);
    }

    public function show($id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor not found'], 404);
        }
        return response()->json($fornecedor, 200);
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

        $fornecedor = Fornecedor::create($request->all());
        return response()->json($fornecedor, 201);
    }

    public function update(Request $request, $id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor not found'], 404);
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

        $fornecedor->update($request->all());
        return response()->json($fornecedor, 200);
    }

    public function destroy($id)
    {
        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['error' => 'Fornecedor not found'], 404);
        }

        $fornecedor->delete();
        return response()->json(['message' => 'Fornecedor deleted successfully'], 200);
    }
}
