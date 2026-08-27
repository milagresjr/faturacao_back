<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConfiguracaoAgt;
use Illuminate\Support\Facades\Validator;

class ConfiguracaoAgtController extends Controller
{
    public function show(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $config = ConfiguracaoAgt::where('empresa_id', $idEmpresa)->first();

        if (!$config) {
            return response()->json([
                'message' => 'Configuração AGT não encontrada',
                'data' => null,
            ]);
        }

        return response()->json($config);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'empresa_id' => 'required|integer|exists:empresas,id',
            'numero_validacao_software' => 'nullable|string|max:255',
            'certificado_digital' => 'nullable|string',
            'ambiente' => 'nullable|string|in:testes,producao',
            'comunicacao_ativa' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $config = ConfiguracaoAgt::where('empresa_id', $request->input('empresa_id'))->first();

        if ($config) {
            $config->update($request->all());
        } else {
            $config = ConfiguracaoAgt::create($request->all());
        }

        return response()->json([
            'message' => 'Configuração AGT guardada com sucesso!',
            'data' => $config,
        ]);
    }
}
