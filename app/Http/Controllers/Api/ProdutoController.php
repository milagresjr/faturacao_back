<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Produto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            // ->where('empresa_id', $request->empresa_id) // Filtra por empresa_id
            ->with(['marca', 'categoria', 'subCategoria', 'armazem', 'tipoIva', 'motivoIsencao', 'fornecedor', 'movimentosStock'])
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
            'stock_min' => 'nullable|integer',
            'stock_max' => 'nullable|integer',
            'stock_ideial' => 'nullable|integer',
            'modelo' => 'nullable|string|max:255',
            'imagem' => 'nullable|file|image|max:2048',
            'movimenta_stock' => 'nullable|boolean',
            'codigo_produto' => 'nullable|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'data_validade' => 'nullable|date',
            'imposto' => 'required|string|integer|max:255',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'unidade' => 'nullable|string|max:50',
            'tipo_stock_id' => 'required|integer|exists:tipo_stock,id',
            'marca_id' => 'nullable|integer|exists:marcas,id',
            'tipo_id' => 'required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'nullable|integer|exists:armazens,id',
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

        $codigoProduto = $this->gerarCodigoProduto($request->nome, $request->tipo_id == 1 ? 'P' : 'S', $request->empresa_id);

        if (empty($data['codigo_produto'])) {
            $data['codigo_produto'] = $codigoProduto; // Gera o código se não for fornecido
        } else {
            // Verifica se o código já existe
            if (Produto::where('codigo_produto', $data['codigo_produto'])->exists()) {
                return response()->json(['error' => 'Código de produto já existe'], 422);
            }
        }

        // Garante que o id da empresa seja do utilizador autenticado, se disponível
        if (isset($request->empresa_id)) {
            $data['empresa_id'] = $request->empresa_id;
        }

        if ($request->hasFile('imagem')) {
            $file = $request->file('imagem');
            $filename = $file->hashName(); // ex: gpGxW5fiTCtyjYQ3EMatApbXRdfSQv0s2AOiZOKM.jpg
            $file->storeAs('produtos', $filename, 'public');
            $data['imagem'] = $filename; // salva só o nome do arquivo no banco
        }

        $produto = Produto::create($data);

        return response()->json($produto->load(['marca', 'categoria', 'subCategoria', 'armazem', 'tipoIva', 'motivoIsencao', 'fornecedor', 'movimentosStock']), 201);
    }


    function gerarCodigoProduto(string $nomeProduto, string $tipo, string $empresaId): string
    {
        $prefixoMarca = strtoupper(Str::substr(Str::slug($nomeProduto, ''), 0, 3)); // Ex: "Asus x8" → "ASU"
        $prefixo =  $tipo == 'P' ? 'P' . $prefixoMarca : 'S' . $prefixoMarca; // Ex: 'VASU'

        $data = Carbon::now()->format('ymd'); // Ex: 250708

        // Contar quantos produtos já foram cadastrados hoje com esse prefixo
        $hoje = Carbon::today();

        $ultimoProduto = Produto::where('empresa_id', $empresaId)
            ->orderByDesc('id')->first();

        $ultimoId = $ultimoProduto ? $ultimoProduto->id : 0;

        /* $sequencia = Produto::where('codigo_produto', 'like', "{$prefixo}-{$data}%")
            ->whereDate('created_at', $hoje)
            ->count() + 1; */

        $nextId = $ultimoId + 1; // Incrementa o ID do último produto

        $codigo = "{$prefixo}" . str_pad($nextId, 2, '0', STR_PAD_LEFT) . "-{$data}";

        return $codigo;
    }

    public function show($id)
    {
        $produto = Produto::with('movimentosStock')->findOrFail($id);

        // Cálculo do stock por armazém (mesma lógica do index)
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
            'unidade' => 'nullable|string|max:50',
            'estado' => 'sometimes|nullable|boolean',
            'marca_id' => 'sometimes|nullable|integer|exists:marcas,id',
            'motivo_isencao_id' => 'nullable|integer|exists:motivo_isencao,id',
            'tipo_stock_id' => 'nullable|integer|exists:tipo_stock,id',
            'tipo_id' => 'sometimes|required|integer|exists:tipo_produtos,id',
            'armazem_id' => 'sometimes|nullable|integer|exists:armazens,id',
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
