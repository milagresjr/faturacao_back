<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarcaController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $marcaQuery = Marca::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $marcaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all Marca records
        $marcas = $marcaQuery
            ->where('empresa_id', $idEmpresa) // Filtra por empresa_id
            ->orderByDesc('id')->paginate($per_page);

        return response()->json($marcas);
    }

    public function show($id)
    {
        // Return a single Marca record by ID
        $marca = Marca::findOrFail($id);
        return response()->json($marca);
    }

    public function store(Request $request)
    {
        // Validate and create a new Marca record
        $validatedData = Validator::make($request->all(), [
            'nome' => 'required|string|max:255|unique:marcas,nome,NULL,id,empresa_id,',
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

        //Se a marca já existir, retorna um erro
        if (Marca::where('nome', $data['nome'])->where('empresa_id', $data['empresa_id'])->exists()) {
            return response()->json([
                'message' => 'Já existe uma marca com este nome.',
            ], 409);
        }

        $marca = Marca::create($data);
        return response()->json($marca, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing Marca record
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'estado' => 'sometimes|boolean',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|exists:utilizadores,id',
        ]);

        $marca = Marca::findOrFail($id);

        //Se a marca já existir, retorna um erro
        $marcaExistente = Marca::where('nome', $validatedData['nome'])
            ->where('empresa_id', $marca->empresa_id)
            ->where('id', '!=', $id)
            ->first();

        if ($marcaExistente) {
            return response()->json([
                'message' => 'Já existe uma marca com este nome.',
            ], 409);
        }

        $marca->update($validatedData);
        return response()->json($marca);
    }

    public function destroy($id)
    {
        // Delete a Marca record
        $marca = Marca::findOrFail($id);
        $marca->delete();
        return response()->json(null, 204);
    }
}
