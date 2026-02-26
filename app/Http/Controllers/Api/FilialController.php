<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FilialController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $filialQuery = Filial::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $filialQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all CategoriaProduto records
        $filial = $filialQuery
            ->where('empresa_id', $idEmpresa) // Filtra por empresa_id
            ->orderByDesc('id')->paginate($per_page);

        return response()->json($filial);
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

        //Se ja existir filial com mesmo nome retornar uma mensagem
        $filialExistente = Filial::where('nome', $request->input('nome'))
            ->where('empresa_id', $request->input('empresa_id'))
            ->first();

        if ($filialExistente) {
            return response()->json(['message' => 'Já existe uma filial com este nome'], 409);
        }

        //Se ja existir filial com mesmo telefone retornar uma mensagem
        $filialTelefoneExistente = Filial::where('telefone', $request->input('telefone'))
            ->where('empresa_id', $request->input('empresa_id'))
            ->first();

        if ($filialTelefoneExistente) {
            return response()->json(['message' => 'Já existe uma filial com este telefone'], 410);
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
            'email' => 'sometimes|nullable|email|max:255',
            'endereco' => 'sometimes|required|string|max:255',
            'nif' => 'sometimes|required|string|max:50',
            'estado' => 'sometimes|required|boolean',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        //Se ja existir filial com mesmo nome retornar uma mensagem
        $filialExistente = Filial::where('nome', $request->input('nome'))
            ->where('empresa_id', $request->input('empresa_id'))
            ->where('id', '!=', $id)
            ->first();

        if ($filialExistente) {
            return response()->json(['message' => 'Já existe uma filial com este nome'], 409);
        }

        //Se ja existir filial com mesmo telefone retornar uma mensagem
        $filialTelefoneExistente = Filial::where('telefone', $request->input('telefone'))
            ->where('empresa_id', $request->input('empresa_id'))
            ->where('id', '!=', $id)
            ->first();

        if ($filialTelefoneExistente) {
            return response()->json(['message' => 'Já existe uma filial com este telefone'], 410);
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
