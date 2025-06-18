<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovimentoStock;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MovimentoStockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $movimentoStockQuery = MovimentoStock::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $movimentoStockQuery->where(function ($query) use ($search) {
                // Pesquisa pelo nome do produto
                $query->whereHas('produto', function ($q) use ($search) {
                    $q->where('nome', 'like', '%' . $search . '%');
                })
                    // Pesquisa pelo nome da armazem relacionada ao produto
                    ->orWhereHas('armazem', function ($q) use ($search) {
                        $q->where('nome', $search);
                    });
            });
        }

        $movimentoStock = $movimentoStockQuery->with(['produto.categoria', 'armazem', 'utilizador'])->orderByDesc('id')->paginate($per_page);

        return response()->json($movimentoStock);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Validação do array de objetos
        $validator = Validator::make($data, [
            '*.produto_id' => ['required', 'exists:produtos,id'],
            '*.armazem_id' => ['required', 'exists:armazens,id'],
            '*.quantidade' => ['required', 'integer'],
            '*.operacao' => ['required', Rule::in(['entrada', 'saida', 'ajuste'])],
            '*.observacao' => ['nullable', 'string'],
            '*.origem_movimento' => ['nullable', 'string'],
            '*.utilizador_id' => ['nullable', 'exists:utilizadores,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
            ], 422);
        }

        $movimentos = collect($data)->flatMap(function ($item) {
            // Se for AJUSTE, então insere dois movimentos: saída do stock atual e entrada do novo valor

            if (strtolower($item['operacao']) === 'saida') {
                // Calcular stock atual no armazém
                $stockAtual = MovimentoStock::where('produto_id', $item['produto_id'])
                    ->where('armazem_id', $item['armazem_id'])
                    ->get()
                    ->sum(function ($mov) {
                        return in_array(strtolower($mov->operacao), ['saida', 'ajuste negativo']) ? -$mov->quantidade : $mov->quantidade;
                    });

                if ($item['quantidade'] > $stockAtual) {
                    $produtoNome = optional(Produto::find($item['produto_id']))->nome ?? 'Produto desconhecido';
                    throw new \Exception("A quantidade de saída ({$item['quantidade']}) excede o stock disponível ({$stockAtual}) para o produto '{$produtoNome}'.");
                    return;
                }
            }

            if (strtolower($item['operacao']) === 'ajuste') {
                // Calcular stock atual no armazém
                $stockAtual = MovimentoStock::where('produto_id', $item['produto_id'])
                    ->where('armazem_id', $item['armazem_id'])
                    ->get()
                    ->sum(function ($mov) {
                        return in_array(strtolower($mov->operacao), ['saida', 'ajuste negativo']) ? -$mov->quantidade : $mov->quantidade;
                    });

                return [
                    MovimentoStock::create([
                        'produto_id' => $item['produto_id'],
                        'armazem_id' => $item['armazem_id'],
                        'quantidade' => -$stockAtual,
                        'operacao' => 'ajuste',
                        'observacao' => $item['observacao'],
                        'origem_movimento' => $item['origem_movimento'],
                        'utilizador_id' => $item['utilizador_id'],
                    ]),
                    MovimentoStock::create([
                        'produto_id' => $item['produto_id'],
                        'armazem_id' => $item['armazem_id'],
                        'quantidade' => $item['quantidade'],
                        'operacao' => 'ajuste',
                        'observacao' => $item['observacao'],
                        'origem_movimento' => $item['origem_movimento'],
                        'utilizador_id' => $item['utilizador_id'],
                    ]),
                ];
            }

            // Caso contrário (entrada ou saída normal)
            return [MovimentoStock::create($item)];
        });

        return response()->json([
            'message' => 'Movimentações registradas com sucesso',
            'data' => $movimentos,
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Lógica para mostrar um movimento de stock específico
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Lógica para atualizar um movimento de stock
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Lógica para remover um movimento de stock
    }
}
