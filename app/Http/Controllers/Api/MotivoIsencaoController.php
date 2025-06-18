<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MotivoIsencao;

class MotivoIsencaoController extends Controller
{
    public function index()
    {
        $motivosIsencao = MotivoIsencao::all();
        return response()->json($motivosIsencao);
    }
}
