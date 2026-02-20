<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Armazem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArmazemController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $armazenQuery = Armazem::query();

        if ($search) {
            //Assuming you want to filter by the 'nome' field. Adjust as necessary.
            $armazenQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all filial records
        $armazens = $armazenQuery
            ->where('empresa_id', $idEmpresa)
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

    public function alterarPredefinido(string $id)
    {
        $armazem = Armazem::find($id);

        if (!$armazem) {
            return response()->json(['message' => 'Armazém não encontrado'], 404);
        }

        try {
            DB::transaction(function () use ($armazem) {
                // Reset all armazéns da mesma empresa (e mesma filial, se aplicável)
                $query = Armazem::where('empresa_id', $armazem->empresa_id);

                if (!is_null($armazem->filial_id)) {
                    $query->where('filial_id', $armazem->filial_id);
                }

                $query->update(['predefinido' => false]);

                // Marcar o escolhido como predefinido
                $armazem->predefinido = true;
                $armazem->save();
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erro ao atualizar armazém predefinido',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json(['message' => 'Armazém predefinido alterado com sucesso', 'armazem' => $armazem]);
    }
}
