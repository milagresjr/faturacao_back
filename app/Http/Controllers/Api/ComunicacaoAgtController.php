<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ComunicacaoAgt;
use App\Models\Documento;
use App\Services\AgtComunicacaoService;

class ComunicacaoAgtController extends Controller
{
    protected $agtService;

    public function __construct(AgtComunicacaoService $agtService)
    {
        $this->agtService = $agtService;
    }

    public function index(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);
        $status = $request->query('status');
        $tipo = $request->query('tipo_comunicacao');

        $query = ComunicacaoAgt::where('empresa_id', $idEmpresa)
            ->with(['documento'])
            ->orderByDesc('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($tipo) {
            $query->where('tipo_comunicacao', $tipo);
        }

        return response()->json($query->paginate($per_page));
    }

    public function pendentes(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');
        $per_page = $request->input('per_page', 10);

        $comunicacaoIds = ComunicacaoAgt::where('empresa_id', $idEmpresa)
            ->whereIn('status', ['pendente', 'enviado'])
            ->pluck('documento_id');

        $documentos = Documento::where('empresa_id', $idEmpresa)
            ->where('estado_documento', 'emitido')
            ->whereNotIn('id', $comunicacaoIds)
            ->whereNotNull('hash')
            ->orderByDesc('data_emissao')
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function enviar(Request $request)
    {
        $documentoId = $request->input('documento_id');

        $documento = Documento::find($documentoId);

        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        $resultado = $this->agtService->enviarDocumento($documento);

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    public function enviarVarios(Request $request)
    {
        $documentosIds = $request->input('documentos_ids', []);

        if (empty($documentosIds)) {
            return response()->json(['message' => 'Nenhum documento selecionado'], 422);
        }

        $resultados = [];
        foreach ($documentosIds as $docId) {
            $documento = Documento::find($docId);
            if ($documento) {
                $resultados[] = $this->agtService->enviarDocumento($documento);
            }
        }

        return response()->json(['resultados' => $resultados]);
    }

    public function consultar(Request $request, $id)
    {
        $comunicacao = $this->agtService->consultarEstado($id);

        if (!$comunicacao) {
            return response()->json(['message' => 'Comunicação não encontrada'], 404);
        }

        return response()->json($comunicacao);
    }

    public function reenviar(Request $request, $id)
    {
        $resultado = $this->agtService->reenviar($id);

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }
}
