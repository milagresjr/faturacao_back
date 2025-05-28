<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Produto;
use Illuminate\Support\Facades\Validator;

class ProdutoController extends Controller
{
    public function index()
    {
        $produtos = Produto::all();
        return response()->json($produtos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descricao' => 'required|string|max:255',
            'preco_custo' => 'required|numeric',
            'preco_venda' => 'required|numeric',
            'stock_min' => 'required|integer',
            'stock_max' => 'required|integer',
            'stock_ideial' => 'required|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|string|max:255',
            'movimenta_stock' => 'required|boolean',
            'marca_id' => 'required|integer|exists:marcas,id',
            'tipo_id' => 'required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'required|integer|exists:armazens,id',
            'categoria_id' => 'required|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'fornecedor_id' => 'required|integer|exists:fornecedores,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $produto = Produto::create($request->all());
        return response()->json($produto, 201);
    }

    public function show($id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto not found'], 404);
        }

        return response()->json($produto);
    }

    public function update(Request $request, $id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'descricao' => 'sometimes|required|string|max:255',
            'preco_custo' => 'sometimes|required|numeric',
            'preco_venda' => 'sometimes|required|numeric',
            'stock_min' => 'sometimes|required|integer',
            'stock_max' => 'sometimes|required|integer',
            'stock_ideial' => 'sometimes|required|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|string|max:255',
            'movimenta_stock' => 'sometimes|required|boolean',
            'estado' => 'sometimes|required|boolean',
            'marca_id' => 'sometimes|required|integer|exists:marcas,id',
            'tipo_id' => 'sometimes|required|integer|exists:tipos,id',
            'armazem_id' => 'sometimes|required|integer|exists:armazens,id',
            'categoria_id' => 'sometimes|required|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'fornecedor_id' => 'sometimes|required|integer|exists:fornecedores,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $produto->update($request->all());
        return response()->json($produto);
    }

    public function destroy($id)
    {
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['message' => 'Produto not found'], 404);
        }

        $produto->delete();
        return response()->json(['message' => 'Produto deleted successfully']);
    }
}
