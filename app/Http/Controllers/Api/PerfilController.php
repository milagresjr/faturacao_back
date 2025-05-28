<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    public function index()
    {
        // Fetch all profiles
        $perfis = Perfil::all();
        return response()->json($perfis);
    }

    public function show($id)
    {
        // Fetch a specific profile by ID
        $perfil = Perfil::find($id);
        if (!$perfil) {
            return response()->json(['message' => 'Profile not found'], 404);
        }
        return response()->json($perfil);
    }

    public function store(Request $request)
    {
        // Validate and create a new profile
        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'required|string|max:255',
            'estado' => 'required|boolean',
        ]);

        $perfil = Perfil::create($request->all());
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
        ]);

        $perfil->update($request->all());
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
