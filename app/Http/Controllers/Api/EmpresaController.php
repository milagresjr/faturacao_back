<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\ConfiguracaoFatura;
use App\Models\Empresa;
use App\Models\Filial;
use App\Models\TipoStock;
use App\Models\Utilizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    public function index()
    {
        $empresas = Empresa::all();
        return response()->json($empresas);
    }

    public function show(String $id)
    {
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        return response()->json($empresa);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'nome' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:empresas,email',
            'nif' => 'nullable|string|max:255|unique:empresas,nif',
            'regime_tributario' => 'nullable|string|in:regime_geral,regime_simplificado,regime_exclusao',
            'telefone' => 'nullable|string|max:255',
            'senha' => 'required|string|min:6|confirmed',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validated->errors()
            ], 422);
        }

        //Busca a ultima empresa cadastrada
        $totEmpresas = Empresa::count();

        try {
            DB::beginTransaction();

            $empresa = Empresa::create([
                'nome' => 'Empresa' . $totEmpresas + 1,
                'email' => $request->input('email'),
                'nif' => NULL,
                'regime_tributario' => NULL,
                'telefone' => $request->input('telefone') || '',
                'pais' => 'Angola',
                'morada' => ''
            ]);

            $storeUser = Utilizador::create([
                'nome_pessoal' => 'Utilizador1',
                'nome_de_utilizador' => $request->input('nome_de_utilizador'),
                'email' => $request->input('email'),
                'senha' => Hash::make($request->input('senha')),
                'nivel_acesso' => 'Admin',
                'perfil_id' => '1',
                'estado' => '1',
                'empresa_id' => $empresa->id,
                'must_change_password' => false,
                'must_fill_data_empresa' => true
            ]);

            //Cadastrar informacoes/personalizacao da fatura
            ConfiguracaoFatura::create([
                'empresa_id' => $empresa->id,
                'mostrar_logo' => '0'
            ]);

            //Cadastrar tipos de Stock
            TipoStock::create([
                'tipo' => 'Mercadoria',
                'sigla' => 'P',
                'motivo_isencao_id' => '7',
                'empresa_id' => $empresa->id
            ]);

            $filialCreated = Filial::create([
                'nome' => 'Filial 1',
                'empresa_id' => $empresa->id,
                'utilizador_id' => $storeUser->id
            ]);

            //Cadastrar Loja principal
            Armazem::create([
                'nome' => 'Armazém Principal',
                'predefinido' => '1',
                'filial_id' => $filialCreated->id,
                'empresa_id' => $empresa->id,
                'utilizador_id' => $storeUser->id
            ]);

            DB::commit();
            return response()->json($storeUser->load('empresa'), 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating company',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        // Validate and update an existing company
        $empresa = Empresa::find($id);

        $user = Utilizador::where('empresa_id', $empresa->id)->first();

        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        if ($request->filled('website')) {
            $website = $request->website;

            if (!preg_match('/^https?:\/\//i', $website)) {
                $website = 'https://' . $website;
            }

            $request->merge([
                'website' => $website
            ]);
        }

        $validated = Validator::make($request->all(), [
            'nome' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:empresas,email,' . $id,
            'nif' => 'sometimes|required|string|max:255|unique:empresas,nif,' . $id,
            'regime_tributario' => 'sometimes|required|string|in:regime_geral,regime_simplificado,regime_exclusao',
            'telefone' => 'sometimes|required|string|max:255',
            'morada' => 'sometimes|nullable|string|max:255',
            'logo' => 'sometimes|nullable|string|max:255',
            'indicativo_fatura' => 'sometimes|nullable|string|max:255',
            'slogan' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'pais' => 'sometimes|nullable|string|max:255',
            'provincia' => 'sometimes|nullable|string|max:255',
            'municipio' => 'sometimes|nullable|string|max:255',
            'bairro' => 'sometimes|nullable|string|max:255',
            'rua' => 'sometimes|nullable|string|max:255',
            'codigo_postal' => 'sometimes|nullable|string|max:50',
            'status' => 'sometimes|nullable|string|max:50',
            'num_via' => 'nullable'
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validated->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $empresa->update($validated->validated());

            if ($request->filled('num_via') && $request->input('num_via') !== null && $request->input('num_via') !== '' && $request->input('num_via') !== 'undefined') {
                ConfiguracaoFatura::where('empresa_id', $id)->update([
                    'num_via' => $request->input('num_via')
                ]);
            }

            $user->must_fill_data_empresa = false;
            $user->save();

            DB::commit();
            return response()->json($empresa);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating company',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    function fillDataEmpresaUser(Request $request)
    {
        $user = $request->user();
        $empresa = $user->empresa;

        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $validated = Validator::make($request->all(), [
            'nif' => 'required|string|max:255',
            'nome_empresa' => 'required|string|max:255',
            'regime_tributario' => 'required|string|in:regime_geral,regime_simplificado,regime_exclusao',
            'nome_pessoal' => 'required|string|max:255',
            'telefone' => 'sometimes|required|string|max:255',
            // 'pais' => 'sometimes|nullable|string|max:255',
            'provincia' => 'sometimes|nullable|string|max:255',
            'municipio' => 'sometimes|nullable|string|max:255',
            'bairro' => 'sometimes|nullable|string|max:255',
            'rua' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validated->errors()
            ], 422);
        }

        $data['empresa_id'] = $empresa->id;

        $data = $validated->validated();

        $empresa->update([
            'nome' => $data['nome_empresa'],
            'nif' => $data['nif'],
            'regime_tributario' => $data['regime_tributario'],
            'telefone' => $data['telefone'],
            'pais' => 'Angola',
            'provincia' => $data['provincia'],
            'municipio' => $data['municipio'],
            'bairro' => $data['bairro'],
            'rua' => $data['rua'],
        ]);

        $user->update([
            'nome_pessoal' => $data['nome_pessoal'],
            'telefone' => $data['telefone'],
            'must_fill_data_empresa' => false
        ]);

        return response()->json($user, 201);
    }

    public function destroy($id)
    {
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $empresa->delete();
        return response()->json(['message' => 'Company deleted successfully']);
    }
}
