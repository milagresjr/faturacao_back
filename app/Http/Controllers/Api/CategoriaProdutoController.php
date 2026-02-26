<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProduto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriaProdutoController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $categoriaQuery = CategoriaProduto::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $categoriaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all CategoriaProduto records
        $categoriasProduto = $categoriaQuery
            ->where('empresa_id', $idEmpresa) // Filtra por empresa_id
            ->orderByDesc('id')->paginate($per_page);

        return response()->json($categoriasProduto);
    }

    public function show($id)
    {
        // Return a single CategoriaProduto record by ID
        $categoriaProduto = CategoriaProduto::findOrFail($id);
        return response()->json($categoriaProduto);
    }

    public function store(Request $request)
    {
        // Validate and create a new CategoriaProduto record
        $validatedData = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'estado' => 'boolean',
            'empresa_id' => 'required|exists:empresas,id',
            'utilizador_id' => 'required|exists:utilizadores,id',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validatedData->errors()
            ], 422);
        }

        $data = $validatedData->validated();

        //se ja existir categoria com mesmo nome retornar uma mensagem
        $categoriaExistente = CategoriaProduto::where('nome', $data['nome'])
            ->where('empresa_id', $data['empresa_id'])
            ->first();

        if ($categoriaExistente) {
            return response()->json(['message' => 'Já existe uma categoria com este nome'], 409);
        }

        $data['nome'] = mb_strtoupper($data['nome']);

        $categoriaProduto = CategoriaProduto::create($data);
        return response()->json($categoriaProduto, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing CategoriaProduto record
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'estado' => 'sometimes|boolean',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|exists:utilizadores,id',
        ]);

        //se ja existir categoria com mesmo nome retornar uma mensagem
        $categoriaExistente = CategoriaProduto::where('nome', $validatedData['nome'])
            ->where('empresa_id', $validatedData['empresa_id'])
            ->where('id', '!=', $id)
            ->first();

        if ($categoriaExistente) {
            return response()->json(['message' => 'Já existe uma categoria com este nome'], 409);
        }

        $validatedData['nome'] = mb_strtoupper($validatedData['nome']);

        $categoriaProduto = CategoriaProduto::findOrFail($id);
        $categoriaProduto->update($validatedData);
        return response()->json($categoriaProduto);
    }

    public function destroy($id)
    {
        // Delete a CategoriaProduto record
        $categoriaProduto = CategoriaProduto::findOrFail($id);
        $categoriaProduto->delete();
        return response()->json(null, 204);
    }
}
