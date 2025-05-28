<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        return response()->json($clientes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'nullable|email|unique:clientes,email',
            'telefone' => 'nullable|string|max:20',
            'nif' => 'nullable|string|max:20|unique:clientes,nif',
            'numero_bi' => 'nullable|string|max:20|unique:clientes,numero_bi',
            'endereco' => 'nullable|string|max:255',
            'data_nasc' => 'nullable|date',
            'tipo_cliente_id' => 'required|integer|exists:tipo_clientes,id',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cliente = Cliente::create($request->all());
        return response()->json($cliente, 201);
    }

    public function show($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente not found'], 404);
        }

        return response()->json($cliente);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:clientes,email,' . $id,
            'telefone' => 'sometimes|required|string|max:20',
            'nif' => 'sometimes|required|string|max:20|unique:clientes,nif,' . $id,
            'numero_bi' => 'sometimes|required|string|max:20|unique:clientes,numero_bi,' . $id,
            'endereco' => 'nullable|string|max:255',
            'data_nasc' => 'sometimes|required|date',
            'estado' => 'sometimes|required|boolean',
            'tipo_cliente_id' => 'sometimes|required|integer|exists:tipo_clientes,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cliente->update($request->all());
        return response()->json($cliente);
    }

    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente not found'], 404);
        }

        $cliente->delete();
        return response()->json(['message' => 'Cliente deleted successfully']);
    }
}
