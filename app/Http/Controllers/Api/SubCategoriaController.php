<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubCategoria;
use Illuminate\Http\Request;

class SubCategoriaController extends Controller
{
   
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $subCategoriaQuery = SubCategoria::query();

        if ($search) {
            // Assuming you want to filter by the 'nome' field. Adjust as necessary.
            $subCategoriaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all subCategoria records
        $subCategorias = $subCategoriaQuery
        ->with(['categoria'])->orderByDesc('id')->paginate($per_page);
        
        return response()->json($subCategorias);
    }

    public function show(string $id)
    {
        $subCategoria = SubCategoria::findOrFail($id);
        return response()->json($subCategoria);
    }
    public function store(Request $request)
    {
        // Validate and create a new subCategoria record
        $validatedData = $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'estado' => 'sometimes|boolean',
            'categoria_id' => 'required|exists:categorias,id',
            'empresa_id' => 'required|exists:empresas,id',
            'utilizador_id' => 'required|exists:utilizadores,id',
        ]);

        $validatedData['nome'] = mb_strtoupper($validatedData['nome']);

        $subCategoria = SubCategoria::create($validatedData);
        return response()->json($subCategoria, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing subCategoria record
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'estado' => 'sometimes|boolean',
            'categoria_id' => 'sometimes|required|exists:categorias,id',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|exists:utilizadores,id',
        ]);

        $validatedData['nome'] = mb_strtoupper($validatedData['nome']);

        $subCategoria = SubCategoria::findOrFail($id);
        $subCategoria->update($validatedData);
        return response()->json($subCategoria);
    }

    public function destroy($id)
    {
        // Delete a subCategoria record
        $subCategoria = SubCategoria::findOrFail($id);
        $subCategoria->delete();
        return response()->json(null, 204);
    }
}
