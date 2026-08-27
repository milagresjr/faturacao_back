<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgtSerieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SerieAgtController extends Controller
{
    public function __construct(
        private AgtSerieService $agtSerieService,
    ) {}

    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        if (!$idEmpresa) {
            return response()->json(['message' => 'empresa_id é obrigatório'], 422);
        }

        $result = $this->agtSerieService->listar($idEmpresa, [
            'tipo_documento' => $request->query('tipo_documento'),
            'series_year' => $request->query('series_year'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return response()->json($result);
    }

    public function show(string $id)
    {
        $serie = \App\Models\SerieAgt::find($id);

        if (!$serie) {
            return response()->json(['message' => 'Série AGT não encontrada'], 404);
        }

        return response()->json($serie);
    }

    public function solicitar(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        if (!$idEmpresa) {
            return response()->json(['message' => 'empresa_id é obrigatório'], 422);
        }

        $validator = Validator::make($request->all(), [
            'tipo_documento' => 'required|string|in:FT,FR,FG,FA,GF,NC,ND,RC,RG,RE,AC,AR,TV,AF,RP,RA,CS,LD',
            'series_year' => 'required|string|size:4',
            'armazem_id' => 'nullable|integer|exists:armazens,id',
            'establishment_number' => 'nullable|string|max:200',
            'contingency_indicator' => 'nullable|string|in:N,C',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $resultado = $this->agtSerieService->solicitar($request->all(), (int) $idEmpresa);

            return response()->json($resultado, $resultado['success'] ? 201 : 400);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function sincronizar(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        if (!$idEmpresa) {
            return response()->json(['message' => 'empresa_id é obrigatório'], 422);
        }

        try {
            $resultado = $this->agtSerieService->sincronizar((int) $idEmpresa, [
                'seriesCode' => $request->input('seriesCode'),
                'series_year' => $request->input('series_year'),
                'documentType' => $request->input('documentType'),
            ]);

            return response()->json($resultado);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function toggleAtivo(string $id)
    {
        try {
            $serie = $this->agtSerieService->toggleAtivo((int) $id);
            return response()->json(['success' => true, 'serie' => $serie]);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 404);
        }
    }
}
