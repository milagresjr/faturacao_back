<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TipoStock;

class TipoStockController extends Controller
{
    public function index()
    {
        // Return a list of all tipo_stock records
        $tiposStock = TipoStock::all();
        return response()->json($tiposStock);
    }
}
