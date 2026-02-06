<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModuloPermissao;
use App\Models\Permissao;
use Illuminate\Http\Request;

class PermissaoController extends Controller
{
    public function index()
    {
        $moduloPermissoes = ModuloPermissao::with('permissoes:id,nome,descricao,modulo_id')->get();
        return response()->json($moduloPermissoes);
    }
}
