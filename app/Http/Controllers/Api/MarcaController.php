<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $marcaQuery = Marca::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $marcaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all Marca records
        $marcas = $marcaQuery
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
        $validatedData = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'estado' => 'boolean',
            'empresa_id' => 'required|exists:empresas,id',
            'utilizador_id' => 'required|exists:utilizadores,id',
        ]);

        $marca = Marca::create($validatedData);
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
