<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracaoFaturaController extends Controller
{
    public function show($empresaId)
    {
        $config = ConfiguracaoFatura::where('empresa_id', $empresaId)->first();

        if (!$config) {
            return response()->json([
                'message' => 'Configuração não encontrada'
            ], 404);
        }

        return response()->json($config);
    }

    public function store(Request $request)
    {
        $data = $request->validate([

            'template' => 'in:classic,modern,minimal',
            'rodape' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
        ]);

        $idEmpresa = $request->empresa_id;
        $data['empresa_id'] = $idEmpresa;
        // Upload do logo
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $config = ConfiguracaoFatura::create(array_merge(
            $this->defaultBooleans(),
            $data
        ));

        return response()->json($config, 201);
    }

    /**
     * Atualizar configuração
     */
    public function update(Request $request, $empresaId)
    {
        $config = ConfiguracaoFatura::where('empresa_id', $empresaId)->firstOrFail();

        $data = $request->validate([
            'template' => 'in:classic,modern,minimal',
            'rodape' => 'nullable|string',
            'logo' => 'nullable|string',

            'nome_empresa' => 'boolean',
            'nif' => 'boolean',
            'email' => 'boolean',
            'telefone' => 'boolean',
            'endereco' => 'boolean',
            'website' => 'boolean',
            'endereco_cliente' => 'boolean',
            'mostrar_utilizador' => 'boolean',
            'mostrar_logo' => 'boolean',
            'mostrar_nif' => 'boolean',
            'mostrar_rodape' => 'boolean',

            'num_via' => 'integer|min:1'
        ]);

        // 👉 Se vier null, ignora
        if (is_null($request->logo)) {
            unset($data['logo']);
        }

        // 👉 Se vier base64, salva imagem
        if ($request->logo && str_contains($request->logo, 'base64')) {
            $image = $request->logo;

            [$type, $image] = explode(';', $image);
            [, $image] = explode(',', $image);

            $image = base64_decode($image);

            $fileName = uniqid() . '.png';

            Storage::disk('public')->put("logos-fatura/{$fileName}", $image);

            $data['logo'] = $fileName;
        }

        $config->update($data);

        return response()->json($config);
    }

    /**
     * Defaults (boa prática)
     */
    private function defaultBooleans()
    {
        return [
            'nome_empresa' => true,
            'nif' => true,
            'email' => true,
            'telefone' => true,
            'endereco' => true,
            'website' => true,
            'endereco_cliente' => true,
            'mostrar_utilizador' => true,
            'mostrar_logo' => true,
            'mostrar_nif' => true,
            'mostrar_rodape' => true,
        ];
    }
}
