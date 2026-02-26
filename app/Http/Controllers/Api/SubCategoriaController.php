<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubCategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubCategoriaController extends Controller
{
   
    public function index(Request $request)
    {
        $idEmpresa = $request->query('empresa_id');
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $subCategoriaQuery = SubCategoria::query();

        if ($search) {
            // Assuming you want to filter by the 'nome' field. Adjust as necessary.
            $subCategoriaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all subCategoria records
        $subCategorias = $subCategoriaQuery
        ->where('empresa_id', $idEmpresa)
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
        $validatedData = Validator::make($request->all(),[
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'estado' => 'sometimes|boolean',
            'categoria_id' => 'required|exists:categorias,id',
            'empresa_id' => 'required|exists:empresas,id',
            'utilizador_id' => 'required|exists:utilizadores,id',
        ]);

        if($validatedData->fails()){
            return response()->json([
                'message' => 'Erro de validação!',
                'errors' => $validatedData->errors()
            ], 422);
        }

        $data = $validatedData->validated();

        //se ja existir sub categoria com mesmo nome retornar uma mensagem
        $subCategoriaExistente = SubCategoria::where('nome', $data['nome'])
            ->where('empresa_id', $data['empresa_id'])
            ->first();

        if ($subCategoriaExistente) {
            return response()->json(['message' => 'Já existe uma subcategoria com este nome'], 409);
        }

        $data['nome'] = mb_strtoupper($data['nome']);

        $subCategoria = SubCategoria::create($data);
        return response()->json($subCategoria, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing subCategoria record
        $validatedData = Validator::make($request->all(),[
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string',
            'estado' => 'sometimes|boolean',
            'categoria_id' => 'sometimes|required|exists:categorias,id',
            'empresa_id' => 'sometimes|required|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|exists:utilizadores,id',
        ]);

        if($validatedData->fails()) { 
            return response()->json([ 
                'message' => 'Erro de validação!', 
                'errors' => $validatedData->errors() ], 422); 
        }

        $data = $validatedData->validated();

         //se ja existir sub categoria com mesmo nome retornar uma mensagem
         $subCategoriaExistente = SubCategoria::where('nome', $data['nome'])
            ->where('empresa_id', $data['empresa_id'])
            ->where('id', '!=', $id)
            ->first();

        if ($subCategoriaExistente) {
            return response()->json(['message' => 'Já existe uma subcategoria com este nome'], 409);
        }

        $data['nome'] = mb_strtoupper($data['nome']);

        $subCategoria = SubCategoria::findOrFail($id);
        $subCategoria->update($data);
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
