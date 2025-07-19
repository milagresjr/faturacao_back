<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use Illuminate\Http\Request;

class BancoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Logic to return a list of bancos
        return Banco::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Logic to create a new banco
        $validatedData = $request->validate([
            'nome' => 'required|string|max:255|unique:bancos,nome',
            'codigo' => 'nullable|string|max:10|unique:bancos,codigo',
            'descricao' => 'nullable|string|max:255',
            'estado' => 'boolean'
        ]);

        $banco = Banco::create($validatedData);

        return response()->json($banco, 201);
    }

    // Other methods like show, update, destroy can be added here
}
