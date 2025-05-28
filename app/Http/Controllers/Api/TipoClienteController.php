<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TipoCliente;
use Illuminate\Support\Facades\Validator;

class TipoClienteController extends Controller
{
    public function index()
    {
        $tipoClientes = TipoCliente::all();
        return response()->json($tipoClientes, 200);
    }

    public function show(string $id)
    {
        $tipoCliente = TipoCliente::find($id);
        if (!$tipoCliente) {
            return response()->json(['message' => 'Tipo cliente not found'], 404);
        }
        return response()->json($tipoCliente, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descricao' => 'required|string|max:255',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
            'empresa_id' => 'required|integer|exists:empresas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipoCliente = TipoCliente::create($request->all());
        return response()->json($tipoCliente, 201);
    }

    public function update(Request $request, $id)
    {
        $tipoCliente = TipoCliente::find($id);
        if (!$tipoCliente) {
            return response()->json(['message' => 'TipoCliente not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'descricao' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|required|boolean',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipoCliente->update($request->all());
        return response()->json($tipoCliente, 200);
    }

    public function destroy($id)
    {
        $tipoCliente = TipoCliente::find($id);
        if (!$tipoCliente) {
            return response()->json(['message' => 'Tipo cliente not found'], 404);
        }

        $tipoCliente->delete();
        return response()->json(['message' => 'Tipo cliente deleted successfully'], 200);
    }
}
