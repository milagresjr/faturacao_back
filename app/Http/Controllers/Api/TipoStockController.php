<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TipoStock;
use Illuminate\Support\Facades\Validator;

class TipoStockController extends Controller
{
    public function index()
    {
        // Return a list of all tipo_stock records
        $tiposStock = TipoStock::all();
        return response()->json($tiposStock);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'tipo' => 'required|string',
            'sigla' => 'nullable|string',
            'motivo_isencao_id' => 'nullable|integer'
        ], [
            'tipo.required' => 'Campo tipo obrigatorio',
            'tipo.string' => 'O tipo deve ser uma string',
            'sigla.string' => 'A sigla deve ser uma string',
            'motivo_isencao_id.integer' => 'O motivo de isencao deve ser um numero inteiro'
        ]);

        if($validated->fails()){
            return response()->json([
                'message' => 'Erro de validacao',
                'errors' => $validated->errors()
            ]);
        }

        $data = $request->all();

        // Garante que o id da empresa seja do utilizador autenticado, se disponível
        if (isset($request->empresa_id)) {
            $data['empresa_id'] = $request->empresa_id;
        }

        $tipoStock = TipoStock::create($data);  

        return response()->json($tipoStock, 201); 
    }

    public function show(string $id)
    {
        $tipoStock = TipoStock::find($id);

        if(!$tipoStock) {
            return response()->json(['message' => 'Tipo de Stock não encontrado']);
        }

        return $tipoStock;
    }

    public function update(Request $request, string $id)
    {
        $tipoStock = TipoStock::find($id);

        if(!$tipoStock) {
            return response()->json(['message' => 'Tipo de Stock não encontrado'], 404);
        }

         $validated = Validator::make($request->all(),[
            'tipo' => 'sometimes|required|string',
            'sigla' => 'sometimes|nullable|string',
            'motivo_isencao_id' => 'sometimes|nullable|integer'
        ], [
            'tipo.required' => 'Campo tipo obrigatorio',
            'tipo.string' => 'O tipo deve ser uma string',
            'sigla.string' => 'A sigla deve ser uma string',
            'motivo_isencao_id.integer' => 'O motivo de isencao deve ser um numero inteiro'
        ]);

        if($validated->fails()){
            return response()->json([
                'message' => 'Erro de validacao',
                'errors' => $validated->errors()
            ]);
        }

        $tipoStockUpdated = $tipoStock->update($request->all());

        return response()->json($tipoStockUpdated, 201); 
    }

    public function destroy(string $id)
    {
        $tipoStock = TipoStock::find($id);

        if(!$tipoStock) {
            return response()->json(['message' => 'Tipo de Stock não encontrado'], 404);
        }

        $deleted = $tipoStock->delete();

        return response()->json(['message' => 'Tipo Stock excluido!']);
    }
}
