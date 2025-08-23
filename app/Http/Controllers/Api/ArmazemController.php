<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Armazem;
use Illuminate\Support\Facades\Validator;

class ArmazemController extends Controller
{
    public function index(Request $request)
    {
         $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $armazenQuery = Armazem::query();

        if ($search) {
            // Assuming you want to filter by the 'nome' field. Adjust as necessary.
            $armazenQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all filial records
        $armazens = $armazenQuery
        ->with(['filial'])->orderByDesc('id')->paginate($per_page);
        
        return response()->json($armazens);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'filial_id' => 'nullable|integer',
            'empresa_id' => 'required|integer',
            'utilizador_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $armazem = Armazem::create($request->all());
        return response()->json($armazem, 201);
    }

    public function show($id)
    {
        $armazem = Armazem::find($id);

        if (!$armazem) {
            return response()->json(['message' => 'Armazém não encontrado'], 404);
        }

        return response()->json($armazem);
    }

    public function update(Request $request, $id)
    {
        $armazem = Armazem::find($id);

        if (!$armazem) {
            return response()->json(['message' => 'Armazém não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'endereco' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|required|string|max:50',
            'filial_id' => 'sometimes|required|integer',
            'empresa_id' => 'sometimes|required|integer',
            'utilizador_id' => 'sometimes|required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $armazem->update($request->all());
        return response()->json($armazem);
    }

    public function destroy($id)
    {
        $armazem = Armazem::find($id);

        if (!$armazem) {
            return response()->json(['message' => 'Armazém não encontrado'], 404);
        }

        $armazem->delete();
        return response()->json(['message' => 'Armazém deletado com sucesso']);
    }
}
