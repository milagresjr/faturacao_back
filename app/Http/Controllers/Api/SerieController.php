<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Serie;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SerieController extends Controller
{
    /**
     * Listar séries
     */
    public function index(Request $request)
    {
        $query = Serie::query();

        $paginate = $request->input('paginate', false);
        $per_page = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('tipo_documento')) {
            $query->where('tipo_documento', $request->tipo_documento)
                ->orderByDesc('padrao'); // Ordenar por padrão primeiro
        }

        $query->latest();

        if ($paginate === true || $paginate === 'true') {
            $series = $query->paginate($per_page, ['*'], 'page', $page);
            return response()->json($series);
        }

        $series = $query->get();
        return response()->json($series);
    }

    /**
     * Criar série
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:255'],
            'ano' => ['required', 'string', 'max:4'],
            'prefixo' => ['required', 'string', 'max:20'],
            'tipo_documento' => [
                'required',
                'in:factura,factura_recibo,recibo,proforma,nota_credito,nota_debito,
                orcamento,encomenda,guia_remessa,guia_transporte,entrada,saida,
                entrada_inventario,saida_inventario,nota_quebra,transferencia'
            ],
            'sequencia_atual' => ['nullable', 'integer', 'min:0'],
            'padrao' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação.',
                'errors' => $validated->errors(),
            ], 422);
        }

        $data = $validated->validated();

        $idEmpresa = $request->input('empresa_id');

        /*
        |--------------------------------------------------------------------------
        | GARANTIR APENAS 1 SÉRIE PADRÃO POR TIPO
        |--------------------------------------------------------------------------
        */


        if (($data['padrao'] ?? false) === true) {
            Serie::where('empresa_id', $data['empresa_id'])
                ->where('tipo_documento', $data['tipo_documento'])
                ->update([
                    'padrao' => false
                ]);
        }

        $serie = Serie::create([
            ...$data,
            'empresa_id' => $idEmpresa,
            'sequencia_atual' => $data['sequencia_atual'] ?? 0,
            'padrao' => $data['padrao'] ?? false,
            'ativo' => $data['ativo'] ?? true,
        ]);

        return response()->json([
            'message' => 'Série criada com sucesso.',
            'data' => $serie,
        ], 201);
    }

    /**
     * Mostrar série
     */
    public function show(string $id)
    {
        $serie = Serie::find($id);

        if (!$serie) {
            return response()->json([
                'message' => 'Série não encontrada.'
            ], 404);
        }

        return response()->json($serie);
    }

    /**
     * Atualizar série
     */
    public function update(Request $request, string $id)
    {
        $serie = Serie::find($id);

        if (!$serie) {
            return response()->json([
                'message' => 'Série não encontrada.'
            ], 404);
        }

        $validated = Validator::make($request->all(), [
            'nome' => ['sometimes', 'string', 'max:255'],
            'ano' => ['sometimes', 'string', 'max:4'],
            'prefixo' => ['sometimes', 'string', 'max:20'],
            'tipo_documento' => [
                'sometimes',
                'in:factura,factura_recibo,recibo,proforma,nota_credito,nota_debito,
                orcamento,encomenda,guia_remessa,guia_transporte,entrada,saida,
                entrada_inventario,saida_inventario,nota_quebra,transferencia'
            ],
            'sequencia_atual' => ['sometimes', 'integer', 'min:0'],
            'padrao' => ['sometimes', 'boolean'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação.',
                'errors' => $validated->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | GARANTIR APENAS 1 PADRÃO
        |--------------------------------------------------------------------------
        */

        $data = $validated->validated();

        if (($data['padrao'] ?? false) === true) {

            $tipoDocumento = $data['tipo_documento']
                ?? $serie->tipo_documento;

            Serie::where('empresa_id', $serie->empresa_id)
                ->where('tipo_documento', $tipoDocumento)
                ->where('id', '!=', $serie->id)
                ->update([
                    'padrao' => false
                ]);
        }

        $serie->update($data);

        return response()->json([
            'message' => 'Série atualizada com sucesso.',
            'data' => $serie,
        ]);
    }

    /**
     * Remover série
     */
    public function destroy(string $id)
    {
        $serie = Serie::find($id);

        if (!$serie) {
            return response()->json([
                'message' => 'Série não encontrada.'
            ], 404);
        }

        $serie->delete();

        return response()->json([
            'message' => 'Série removida com sucesso.'
        ]);
    }

    //Buscar serie apartir do tipo documento e empresa
    public function getSeriesByTipoDocumento(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'tipo_documento' => [
                'required',
                'in:factura,factura_recibo,recibo,proforma,nota_credito,nota_debito,
                orcamento,encomenda,guia_remessa,guia_transporte,entrada,saida,
                entrada_inventario,saida_inventario,nota_quebra,transferencia'
            ],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Erro de validação.',
                'errors' => $validated->errors(),
            ], 422);
        }

        $data = $validated->validated();

        $idEmpresa = $request->input('empresa_id');

        //Pegar todas as series da base de dados ordenar pelo padrao
        $serie = Serie::where('empresa_id', $idEmpresa)
            ->where('tipo_documento', $data['tipo_documento'])
            ->orderByDesc('padrao')
            ->get();

        if (!$serie) {
            return response()->json([
                'message' => 'Série padrão não encontrada para o tipo de documento informado.'
            ], 404);
        }

        return response()->json($serie);
    }

    public function definirComoPadrao(string $id)
    {
        $serie = Serie::find($id);

        if (!$serie) {
            return response()->json([
                'message' => 'Série não encontrada.'
            ], 404);
        }

        Serie::where('empresa_id', $serie->empresa_id)
            ->where('tipo_documento', $serie->tipo_documento)
            ->update([
                'padrao' => false
            ]);

        $serie->update([
            'padrao' => true
        ]);

        return response()->json([
            'message' => 'Série definida como padrão com sucesso.',
            'data' => $serie,
        ]);
    }

    public function alterarAtivo(string $id)
    {
        $serie = Serie::find($id);

        if (!$serie) {
            return response()->json([
                'message' => 'Série não encontrada.'
            ], 404);
        }

        $serie->update([
            'ativo' => !$serie->ativo
        ]);

        return response()->json([
            'message' => 'Estado da série alterado com sucesso.',
            'data' => $serie,
        ]);
    }


    public function gerarProximaSerieDocumento(
        string $idSerie,
        string $empresaId,
    ): string {

        $empresa = DB::table("empresas")->find($empresaId);

        // Conta quantos documentos desse tipo e ano já existem
        $serie = DB::table("series")
            ->where("id", $idSerie)
            ->first();

        $contador = DB::table("documentos")
            ->where("serie_id", $idSerie)
            ->count();

        $sequencial = $contador + 1;

        // Formato final: FR T11P2025/2
        return "{$serie->prefixo} {$empresa->ano}/{$sequencial}";
    }
}
