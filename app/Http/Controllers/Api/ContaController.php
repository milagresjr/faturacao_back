<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Conta;
use Illuminate\Support\Facades\Validator;

class ContaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Logic to return a list of contas
        $empresaId = $request->query('empresa_id');
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $contaQuery = Conta::query();

        if($empresaId) {
            $contaQuery->where('empresa_id', $empresaId);
        }

        if ($search) {
            $contaQuery->where(function ($q) use ($search) {
                $q->where('banco.sigla', 'like', '%' . $search . '%')
                    ->orWhere('banco.descricao', 'like', '%' . $search . '%');
            });
        }

        $contas = $contaQuery->with(['empresa', 'banco'])
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json($contas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'banco_id' => 'required|exists:bancos,id',
                'utilizador_id' => 'required|exists:utilizadores,id',
                'numero_conta' => 'required|string|max:255|unique:contas,numero_conta',
                'descricao' => 'nullable|string|max:255',
                'saldo' => 'required|numeric|min:0',
                'tipo' => 'required|string|in:corrente,poupanca,ordem',
                'moeda' => 'required|string|max:3',
                'iban' => 'nullable|string|max:34|unique:contas,iban',
                'swift' => 'nullable|string|max:11',
                'titular' => 'nullable|string|max:255',
                'estado' => 'boolean',
                'empresa_id' => 'nullable|exists:empresas,id',
            ], [
                'required' => 'O campo :attribute é obrigatório.',
                'exists' => 'O :attribute selecionado não existe.',
                'unique' => 'Já existe uma conta com este :attribute.',
                'numeric' => 'O campo :attribute deve ser um número.',
                'max' => 'O campo :attribute não pode ter mais de :max caracteres.',
                'min' => 'O campo :attribute deve ser pelo menos :min.',
                'in' => 'O campo :attribute deve ser um dos seguintes valores: :values.',
                'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            ]);

            // Normalização do IBAN
            if (!empty($data['iban'])) {
                $iban = preg_replace('/\s+/', '', $data['iban']);

                if (!preg_match('/^AO06/i', $iban)) {
                    $iban = 'AO06' . $iban;
                }

                $data['iban'] = trim(implode(' ', str_split($iban, 4)));
            }

            $conta = Conta::create($data);

            return response()->json($conta, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Logic to return a specific conta
        if (!is_numeric($id)) {
            return response()->json(['error' => 'ID inválido'], 400);
        }
        $conta = Conta::with(['empresa', 'banco'])->find($id);
        if (!$conta) {
            return response()->json(['error' => 'Conta não encontrada'], 404);
        }
        return response()->json($conta);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Logic to update a specific conta
        $conta = Conta::find($id);
        if (!$conta) {
            return response()->json(['error' => 'Conta não encontrada'], 404);
        }
        $validatedData = Validator::make($request->all(), [
            'banco_id' => 'sometimes|exists:bancos,id',
            'utilizador_id' => 'sometimes|exists:utilizadores,id',
            'numero_conta' => 'sometimes|string|max:255|unique:contas,numero_conta,' . $id,
            'descricao' => 'nullable|string|max:255',
            'saldo' => 'sometimes|numeric|min:0',
            'tipo' => 'sometimes|string|in:corrente,poupanca,ordem',
            'moeda' => 'sometimes|string|max:3',
            'iban' => 'nullable|string|max:34|unique:contas,iban,' . $id,
            'swift' => 'nullable|string|max:11',
            'titular' => 'nullable|string|max:255',
            'estado' => 'boolean'
        ]);

        if ($validatedData->fails()) {
            return response()->json($validatedData->errors(), 422);
        }

        $conta->update($request->all());

        return response()->json($conta);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Logic to delete a specific conta
        $conta = Conta::find($id);
        if (!$conta) {
            return response()->json(['error' => 'Conta não encontrada'], 404);
        }
        $conta->delete();
        return response()->json(['message' => 'Conta deletada com sucesso'], 204);
    }
}
