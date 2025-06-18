<?php

use App\Http\Controllers\Api\ArmazemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaProdutoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\FilialController;
use App\Http\Controllers\Api\FornecedorController;
use App\Http\Controllers\Api\MarcaController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\ProdutoController;
use App\Http\Controllers\Api\SubCategoriaController;
use App\Http\Controllers\Api\TipoClienteController;
use App\Http\Controllers\Api\TipoProdutoController;
use App\Http\Controllers\Api\MotivoIsencaoController;
use App\Http\Controllers\Api\MovimentoStockController;
use App\Http\Controllers\Api\TipoStockController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::middleware('api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

//Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Add your protected routes here
    Route::apiResource('empresas', EmpresaController::class);
    Route::apiResource('perfis', PerfilController::class);
    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('tipo-produtos', TipoProdutoController::class);
    Route::apiResource('categoria-produtos', CategoriaProdutoController::class);
    Route::apiResource('sub-categoria-produtos', SubCategoriaController::class);
    Route::apiResource('fornecedores', FornecedorController::class);
    Route::apiResource('filiais',FilialController::class);
    Route::apiResource('armazens', ArmazemController::class);
    Route::apiResource('tipo-clientes', TipoClienteController::class);
    Route::apiResource('clientes', ClienteController::class);
    Route::apiResource('produtos', ProdutoController::class);
    Route::get('motivo-isencao', [MotivoIsencaoController::class, 'index']);
    Route::get('tipo-stock', [TipoStockController::class, 'index']);
    Route::apiResource('movimento-stock', MovimentoStockController::class);
    //Route::apiResource('utilizadores', UtilizadorController::class);
//});