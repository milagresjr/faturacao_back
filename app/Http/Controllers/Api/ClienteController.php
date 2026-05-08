<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Documento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{

    /**
     * Validações comuns para evitar duplicação
     */
    private function validationRulesStore(): array
    {
        return [
            'email' => [
                'nullable',
                'email',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],

            'nif' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],

            'numero_bi' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],

            'nome' => 'required|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'data_nasc' => 'nullable|date',
            'tipo_cliente_id' => 'required|integer|exists:tipo_clientes,id',
            'gestor_id' => 'nullable|integer|exists:utilizadores,id',
            'vencimento' => 'nullable|integer',
            'telemovel' => 'nullable|string|max:20',
            'fatura_eletronica' => 'nullable|boolean',
            'website' => 'nullable|string|max:255',
            'grupo_preco_id' => 'nullable|integer|exists:grupo_precos,id',
            'observacoes' => 'nullable|string',
            'faz_retencao' => 'nullable|boolean',
            'pais' => 'nullable|string|max:100',
            'empresa_id' => 'required|integer|exists:empresas,id',
            'utilizador_id' => 'required|integer|exists:utilizadores,id',
        ];
    }

    public function index(Request $request)
    {
        $queryParams = [
            'paginate' => $request->input('paginate', false),
            'perPage' => $request->input('per_page', 10),
            'search' => $request->query('search'),
            'empresaId' => $request->input('empresa_id'),
        ];

        $clientesQuery = Cliente::query()
            ->with(['tipoCliente', 'empresa', 'utilizador'])
            ->where('empresa_id', $queryParams['empresaId'])
            ->orderByDesc('id');

        // Aplica filtro de busca se fornecido
        if (!empty($queryParams['search'])) {
            $searchTerm = '%' . $queryParams['search'] . '%';
            $clientesQuery->where(function ($query) use ($searchTerm) {
                $query->where('nome', 'like', $searchTerm)
                    ->orWhere('nif', 'like', $searchTerm);
            });
        }

        // Executa consulta com ou sem paginação
        $clientes = $queryParams['paginate']
            ? $clientesQuery->paginate($queryParams['perPage'])
            : $clientesQuery->get();

        return response()->json($clientes);
    }

    public function store(Request $request)
    {
        // Validação com mensagens personalizadas
        $validator = Validator::make($request->all(), $this->validationRulesStore(), [
            // Mensagens específicas para cada campo
            'email.unique' => 'Já existe cliente cadastrado com este e-mail.',
            'nif.unique' => 'Já existe cliente cadastrado com este NIF.',
            'numero_bi.unique' => 'Já existe cliente com este número de BI',
            'email.email' => 'Por favor, informe um e-mail válido.',
            'nome.required' => 'O nome do cliente é obrigatório.',
            'tipo_cliente_id.required' => 'O tipo de cliente é obrigatório.',
            'tipo_cliente_id.exists' => 'O tipo de cliente selecionado é inválido.',
            'empresa_id.required' => 'A empresa é obrigatória.',
            'empresa_id.exists' => 'A empresa selecionada é inválida.',
            'utilizador_id.required' => 'O utilizador responsável é obrigatório.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(), // Já vem formatado campo => [erros]
                'fields' => $validator->errors()->keys() // Array com campos que têm erro
            ], 422);
        }

        $cliente = Cliente::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cliente cadastrado com sucesso!',
            'data' => $cliente
        ], 201);
    }

    public function show($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return $this->notFoundResponse();
        }

        return response()->json($cliente);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não encontrado'
            ], 404);
        }

        // Validação com regras condicionais (ignora o próprio ID)
        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],
            'nif' => [
                'sometimes',
                'nullable',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],
            'numero_bi' => [
                'sometimes',
                'nullable',
                Rule::unique('clientes')->where(function ($query) {
                    return $query->where('empresa_id', request('empresa_id'));
                }),
            ],
            'telefone' => 'sometimes|required|string|max:20',
            'endereco' => 'nullable|string|max:255',
            'data_nasc' => 'sometimes|required|date',
            'estado' => 'sometimes|required|boolean',
            'tipo_cliente_id' => 'sometimes|required|integer|exists:tipo_clientes,id',
            'gestor_id' => 'sometimes|nullable|integer|exists:utilizadores,id',
            'vencimento' => 'sometimes|nullable|integer',
            'telemovel' => 'sometimes|nullable|string|max:20',
            'fatura_eletronica' => 'sometimes|nullable|boolean',
            'website' => 'sometimes|nullable|string|max:255',
            'grupo_preco_id' => 'sometimes|nullable|integer|exists:grupo_precos,id',
            'observacoes' => 'sometimes|nullable|string',
            'faz_retencao' => 'sometimes|nullable|boolean',
            'pais' => 'sometimes|nullable|string|max:100',
            'empresa_id' => 'sometimes|required|integer|exists:empresas,id',
            'utilizador_id' => 'sometimes|required|integer|exists:utilizadores,id',
        ], [
            'email.unique' => 'Este e-mail já está cadastrado por outro cliente.',
            'nif.unique' => 'Este NIF já está cadastrado por outro cliente.',
            'numero_bi.unique' => 'Este número de BI já está cadastrado por outro cliente.',
            'email.email' => 'Por favor, informe um e-mail válido.',
            'nome.required' => 'O nome do cliente é obrigatório.',
            'tipo_cliente_id.required' => 'O tipo de cliente é obrigatório.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors(),
                'fields' => $validator->errors()->keys()
            ], 422);
        }

        // VERIFICAR SE O NIF FOI ALTERADO
        if ($request->has('nif') && $request->input('nif') != $cliente->nif) {
            $temDocumentos = Documento::where('cliente_id', $id)
                ->where('estado', '!=', 'rascunho')
                ->exists();

            if ($temDocumentos) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é permitido alterar o NIF pois o cliente já possui documentos emitidos.',
                    'error_code' => 'NIF_CHANGE_NOT_ALLOWED',
                    'field' => 'nif' // Informa qual campo gerou o erro
                ], 422);
            }
        }

        $cliente->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cliente atualizado com sucesso!',
            'data' => $cliente
        ]);
    }

    public function destroy($id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return $this->notFoundResponse();
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente removido com sucesso',
            'deleted_id' => $id
        ]);
    }

    /**
     * Valida se o NIF pode ser alterado baseado em documentos existentes
     */
    private function validateNifChange(Request $request, Cliente $cliente): ?\Illuminate\Http\JsonResponse
    {
        // Se NIF não foi alterado ou não está na requisição, retorna sem erro
        if (!$request->has('nif') || $request->input('nif') == $cliente->nif) {
            return null;
        }

        $hasDocumentsWithState = Documento::where('cliente_id', $cliente->id)
            ->where('estado', '!=', 'rascunho')
            ->exists();

        if ($hasDocumentsWithState) {
            return response()->json([
                'message' => 'Não é permitido alterar o NIF do cliente pois ele já possui documentos emitidos.',
                'error_code' => 'NIF_CHANGE_NOT_ALLOWED'
            ], 422);
        }

        return null;
    }

    /**
     * Resposta padronizada para recurso não encontrado
     */
    private function notFoundResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => 'Cliente não encontrado',
            'error_code' => 'RESOURCE_NOT_FOUND'
        ], 404);
    }
}
