<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PerfilController extends Controller
{
    public function index()
    {
        // Fetch all profiles
        $perfis = Perfil::where('empresa_id', NULL)->get();
        return response()->json($perfis);
    }

    public function listByEmpresa(Request $request)
    {
        $empresaId = $request['empresa_id'];

        $perfis = Perfil::where('empresa_id', $empresaId)->get();

        return response()->json($perfis);
    }

    public function show($id)
    {
        // Fetch a specific profile by ID
        $perfil = Perfil::with('permissoes')->find($id);
        if (!$perfil) {
            return response()->json(['message' => 'Profile not found'], 404);
        }
        return response()->json($perfil);
    }

    public function store(Request $request)
    {
        // Validate and create a new profile
        $validated = Validator::make($request->all(), [
            'nome' => 'required|string|max:255|unique:perfis',
            'descricao' => 'nullable|string|max:255',
            // 'estado' => 'nullable|boolean',
            'permissoes' => 'required|array',
            'permissoes.*' => 'integer|exists:permissoes,id',
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()], 422);
        }

        $empresaId = $request['empresa_id'];
        DB::beginTransaction();
        try {
            $perfil = Perfil::create([
                'empresa_id' => $empresaId,
                'nome' => $request->input('nome'),
                'descricao' => $request->input('descricao'),
                'estado' => true,
            ]);

            // Attach permissions to the profile
            $perfil->permissoes()->attach($request->input('permissoes'));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating profile', 'error' => $e->getMessage()], 500);
        }

        return response()->json($perfil, 201);
    }

    public function update(Request $request, $id)
    {
        // Validate and update an existing profile
        $perfil = Perfil::find($id);
        if (!$perfil) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $request->validate([
            'nome' => 'sometimes|required|string|max:255',
            'descricao' => 'sometimes|required|string|max:255',
            'estado' => 'sometimes|required|boolean',
            'permissoes' => 'sometimes|required|array',
            'permissoes.*' => 'integer|exists:permissoes,id',
        ]);
        DB::beginTransaction();
        try {

            $perfil->update($request->all());

            $perfil->permissoes()->sync($request->input('permissoes'));

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating profile', 'error' => $th->getMessage()], 500);
        }

        return response()->json($perfil);
    }

    public function destroy($id)
    {
        // Delete a profile
        $perfil = Perfil::find($id);
        if (!$perfil) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $perfil->delete();
        return response()->json(['message' => 'Profile deleted successfully']);
    }
}
