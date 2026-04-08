<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoteProduto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoteController extends Controller
{
    public function index()
    {
        // Lógica para listar lotes
        $lotes = LoteProduto::with(['produto', 'armazem'])->get();
        return response()->json($lotes);
    }

    public function store(Request $request)
    {
        // Lógica para criar um novo lote
        $validated = Validator::make($request->all(), [
            'produto_id' => 'required|exists:produtos,id',
            'armazem_id' => 'required|exists:armazens,id',
            'lote' => 'nullable|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'codigo_lote' => 'nullable|string|max:255',
            'data_fabricacao' => 'nullable|string',
            'data_validade' => 'nullable|string',
            'qtd_inicial' => 'nullable|numeric|min:0',
            'observacao' => 'nullable|string'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        $data['empresa_id'] = $request->input('empresa_id'); // Certifique-se de que o ID da empresa está sendo passado na requisição

        $data = $validated->validated();

        if (!isset($request['codigo_lote']) || empty($request['codigo_lote'])) {
            $data['codigo_lote'] = $this->gerarCodigoLote();
        }

        try {
            $lote = LoteProduto::create($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar lote',
                'error' => $e->getMessage()
            ], 500);
        }
        return response()->json($lote, 201);
    }

    public function show($id)
    {
        // Lógica para mostrar um lote específico
        $lote = LoteProduto::with('produto')->find($id);
        if (!$lote) {
            return response()->json(['message' => 'Lote não encontrado'], 404);
        }
        return response()->json($lote);
    }

    //Buscar todos os lotes de uma empresa
    public function getLotesByEmpresa($empresaId, Request $request)
    {
        $lotes = LoteProduto::where('empresa_id', $empresaId)->with('produto')->get();
        return response()->json($lotes);
    }

    public function getLotesByProduto($produtoId, Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = LoteProduto::where('produto_id', $produtoId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('lote', 'like', "%{$search}%")
                    ->orWhere('codigo_barra', 'like', "%{$search}%")
                    ->orWhere('codigo_lote', 'like', "%{$search}%");
            });
        }
        
        if ($status) {
            $query->where('status', $status);
        }

        $lotes = $query->with('armazem')->get();
        return response()->json($lotes);
    }

    public function update(Request $request, $id)
    {
        // Lógica para atualizar um lote
        $lote = LoteProduto::find($id);
        if (!$lote) {
            return response()->json(['message' => 'Lote não encontrado'], 404);
        }

        $validated = Validator::make($request->all(), [
            'produto_id' => 'sometimes|required|exists:produtos,id',
            'lote' => 'nullable|string|max:255',
            'codigo_barra' => 'nullable|string|max:255',
            'codigo_lote' => 'nullable|string|max:255',
            'data_fabricacao' => 'nullable|string',
            'data_validade' => 'nullable|string',
            'qtd_inicial' => 'sometimes|required|numeric|min:0',
            'observacao' => 'nullable|string'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validated->errors()
            ], 422);
        }

        try {
            $lote->update($validated->validated());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar lote',
                'error' => $e->getMessage()
            ], 500);
        }
        return response()->json($lote);
    }

    public function destroy($id)
    {
        // Lógica para deletar um lote
        $lote = LoteProduto::find($id);
        if (!$lote) {
            return response()->json(['message' => 'Lote não encontrado'], 404);
        }
        try {
            $lote->delete();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar lote',
                'error' => $e->getMessage()
            ], 500);
        }
        return response()->json(['message' => 'Lote deletado com sucesso']);
    }

    private function gerarCodigoLote()
    {
        // Formato: LOTE-{data atual YYYYMMDD}-{sequencial do dia}
        $dataHoje = now()->format('Ymd');
        $prefixo = "LOTE-{$dataHoje}";

        // Buscar último lote criado hoje
        $ultimoLote = LoteProduto::where('codigo_lote', 'LIKE', $prefixo . '%')
            ->orderBy('codigo_lote', 'desc')
            ->first();

        if ($ultimoLote) {
            // Extrair o número sequencial do último lote
            $partes = explode('-', $ultimoLote->codigo_lote);
            $sequencial = intval(end($partes)) + 1;
        } else {
            $sequencial = 1;
        }

        // Formatar sequencial com 3 dígitos (001, 002, etc)
        $sequencialFormatado = str_pad($sequencial, 3, '0', STR_PAD_LEFT);

        return "{$prefixo}-{$sequencialFormatado}";
    }
}
