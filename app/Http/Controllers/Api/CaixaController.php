<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Caixa;

class CaixaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Logic to list all caixas

        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $caixaQuery = Caixa::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $caixaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all Caixa records
        $categoriasProduto = $caixaQuery->with(['armazem', 'usuario'])
        ->orderByDesc('id')->paginate($per_page);

        return response()->json($categoriasProduto);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logic to create a new caixa
        $validatedData = $request->validate([
            'nome' => 'required|string|max:150',
            'localizacao' => 'nullable|string|max:255',
            'tipo' => 'nullable|in:fisico,virtual,movel',
            'estado' => 'nullable|in:aberto,fechado,inativo',
            'saldo_inicial' => 'nullable|numeric',
            'saldo_atual' => 'nullable|numeric',
            'data_abertura' => 'nullable|date',
            'data_fechamento' => 'nullable|date',
            'turno' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
            'imprimir_abertura' => 'nullable|boolean',
            'documento_predefinido' => 'nullable|string|max:100',
            'aspecto' => 'nullable|string|max:100',
            'metodo_impressao' => 'nullable|string|max:50',
            'modelo_impressao' => 'nullable|string|max:100',
            'impressao_papel' => 'nullable|string|max:50',
            'modelo_email' => 'nullable|string|max:100',
            'finalizar_avancado' => 'nullable|boolean',
            'referencia_produtos' => 'nullable|string|max:255',
            'precos_produtos' => 'nullable|string|max:255',
            'modo_funcionamento' => 'nullable|string|max:50',
            'listar_produtos' => 'nullable|boolean',
            'grupo_precos' => 'nullable|string|max:100',
            'permite_movimento_negativo' => 'nullable|boolean',
            'permite_multiplos_usuarios' => 'nullable|boolean',
            'usuario_id' => 'required|integer|exists:utilizadores,id',
            'armazem_id' => 'required|integer|exists:armazens,id',
            // Add other fields validation as necessary
        ]);

        $data = $request->all();

        // Garante que o id da empresa seja do utilizador autenticado, se disponível
        if (isset($request->empresa_id)) {
            $data['empresa_id'] = $request->empresa_id;
        }

        $caixa = Caixa::create($data);

        return response()->json($caixa, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Logic to show a specific caixa
        $caixa = Caixa::findOrFail($id);
        return response()->json($caixa);
    }

    /**
     * Get all caixas by armazem ID.
     */
    public function getByArmazem(Request $request, string $armazemId)
    {
        $per_page = $request->input('per_page', 10);

        $search = $request->query('search');

        $caixaQuery = Caixa::query();

        if ($search) {
            // Supondo que você queira filtrar pelo campo 'nome'. Altere conforme sua necessidade.
            $caixaQuery->where('nome', 'like', '%' . $search . '%');
        }

        // Return a list of all Caixa records
        $categoriasProduto = $caixaQuery->where('armazem_id', $armazemId)
        ->orderByDesc('id')->paginate($per_page);

        return response()->json($categoriasProduto);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Logic to update a specific caixa
        $caixa = Caixa::findOrFail($id);
        $validatedData = $request->validate([
            'nome' => 'sometimes|required|string|max:150',
            'localizacao' => 'nullable|string|max:255',
            'tipo' => 'sometimes|required|in:fisico,virtual,movel',
            'estado' => 'sometimes|required|in:aberto,fechado,inativo',
            'saldo_inicial' => 'sometimes|required|numeric',
            'saldo_atual' => 'sometimes|required|numeric',
            'data_abertura' => 'nullable|date',
            'data_fechamento' => 'nullable|date',
            'turno' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:500',
            'imprimir_abertura' => 'nullable|boolean',
            'documento_predefinido' => 'nullable|string|max:100',
            'aspecto' => 'nullable|string|max:100',
            'metodo_impressao' => 'nullable|string|max:50',
            'modelo_impressao' => 'nullable|string|max:100',
            'impressao_papel' => 'nullable|string|max:50',
            'modelo_email' => 'nullable|string|max:100',
            'finalizar_avancado' => 'nullable|boolean',
            'referencia_produtos' => 'nullable|string|max:255',
            'precos_produtos' => 'nullable|string|max:255',
            'modo_funcionamento' => 'nullable|string|max:50',
            'listar_produtos' => 'nullable|boolean',
            'grupo_precos' => 'nullable|string|max:100',
            'permite_movimento_negativo' => 'nullable|boolean',
            'permite_multiplos_usuarios' => 'nullable|boolean',
            'usuario_id' => 'sometimes|required|integer|exists:utilizadores,id',
            'armazem_id' => 'sometimes|required|integer|exists:armazens,id',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
        ]);
        $caixa->update($validatedData);
        return response()->json($caixa);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Logic to delete a specific caixa
        $caixa = Caixa::findOrFail($id);
        $caixa->delete();
        return response()->json(null, 204);
    }
}
