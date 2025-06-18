<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Produto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProdutoController extends Controller
{
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $search = $request->query('search');

        $produtoQuery = Produto::query();

        if ($search) {
            $produtoQuery->where(function ($q) use ($search) {
                $q->where('nome', 'like', '%' . $search . '%')
                    ->orWhere('descricao', 'like', '%' . $search . '%');
            });
        }

        $produtos = $produtoQuery
            ->with(['marca', 'categoria', 'subCategoria', 'armazem', 'fornecedor', 'movimentosStock'])
            ->orderByDesc('id')
            ->paginate($per_page);

        // Adiciona as quantidades por armazém a cada produto
        $produtos->getCollection()->transform(function ($produto) {
            $quantidades = $produto->movimentosStock
                ->groupBy('armazem_id')
                ->map(function ($movimentos) {
                    return $movimentos->sum(function ($movimento) {
                        if (in_array(strtolower($movimento->operacao), ['saida', 'ajuste negativo'])) {
                            return -$movimento->quantidade;
                        } else {
                            return $movimento->quantidade;
                        }
                    });
                });

            $produto->quantidades = $quantidades;

            return $produto;
        });

        return response()->json($produtos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:255',
            'preco_custo' => 'required|numeric',
            'preco_venda' => 'required|numeric',
            'preco_final' => 'required|numeric',
            'margem_lucro' => 'required|numeric',
            'valor_iva' => 'required|numeric',
            'stock_min' => 'required|integer',
            'stock_max' => 'required|integer',
            'stock_ideial' => 'required|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|file|image|max:2048',
            'movimenta_stock' => 'required|boolean',
            'codigo_produto' => 'nullable|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'data_validade' => 'nullable|date',
            'imposto' => 'required|string|max:255',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'tipo_stock_id' => 'nullable|integer|exists:tipo_stock,id',
            'marca_id' => 'nullable|integer|exists:marcas,id',
            'tipo_id' => 'required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'required|integer|exists:armazens,id',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'fornecedor_id' => 'nullable|integer|exists:fornecedores,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('imagem')) {
            $file = $request->file('imagem');
            $filename = $file->hashName(); // ex: gpGxW5fiTCtyjYQ3EMatApbXRdfSQv0s2AOiZOKM.jpg
            $file->storeAs('produtos', $filename, 'public');
            $data['imagem'] = $filename; // salva só o nome do arquivo no banco
        }

        $produto = Produto::create($data);
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
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        // Upload da imagem ANTES da validação
        $filename = null;
        if ($request->hasFile('imagem')) {
            // Deleta a imagem antiga
            if ($produto->imagem) {
                Storage::disk('public')->delete('produtos/' . $produto->imagem);
            }

            $file = $request->file('imagem');
            $filename = $file->hashName();
            $file->storeAs('produtos', $filename, 'public');
        }

        // Validação
        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|nullable|string|max:255',
            'preco_custo' => 'sometimes|required|numeric',
            'preco_venda' => 'sometimes|required|numeric',
            'preco_final' => 'sometimes|required|numeric',
            'margem_lucro' => 'sometimes|required|numeric',
            'valor_iva' => 'sometimes|required|numeric',
            'stock_min' => 'sometimes|nullable|integer',
            'stock_max' => 'sometimes|nullable|integer',
            'stock_ideial' => 'sometimes|nullable|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|file|image|max:2048',
            'movimenta_stock' => 'sometimes|required|boolean',
            'codigo_produto' => 'sometimes|required|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'data_validade' => 'nullable|date',
            'imposto' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|nullable|boolean',
            'marca_id' => 'sometimes|nullable|integer|exists:marcas,id',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'tipo_stock_id' => 'nullable|integer|exists:tipo_stock,id',
            'tipo_id' => 'sometimes|required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'sometimes|required|integer|exists:armazens,id',
            'categoria_id' => 'sometimes|nullable|integer|exists:categorias,id',
            'sub_categoria_id' => 'nullable|integer|exists:sub_categorias,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'fornecedor_id' => 'sometimes|nullable|integer|exists:fornecedores,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Se houve upload, substitui o campo imagem
        if ($filename) {
            $data['imagem'] = $filename;
        }

        // Atualiza
        $produto->update($data);

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
