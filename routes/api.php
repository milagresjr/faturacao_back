<?php

use App\Http\Controllers\Api\ArmazemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BancoController;
use App\Http\Controllers\Api\CaixaController;
use App\Http\Controllers\Api\CategoriaProdutoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ContaController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\FaturaCompraController;
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
use App\Http\Controllers\Api\TipoTaxaIvaController;
use App\Http\Controllers\Api\UnidadeController;
use App\Http\Middleware\AuthenticateWithRememberToken;

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

Route::apiResource('produtos', ProdutoController::class);
Route::apiResource('perfis', PerfilController::class);
Route::middleware([AuthenticateWithRememberToken::class])->group(function () {



    // Add your protected routes here
    Route::apiResource('marcas', MarcaController::class);
    Route::apiResource('tipo-produtos', TipoProdutoController::class);
    Route::apiResource('categoria-produtos', CategoriaProdutoController::class);
    Route::apiResource('sub-categoria-produtos', SubCategoriaController::class);
    Route::apiResource('fornecedores', FornecedorController::class);
    Route::apiResource('filiais', FilialController::class);
    Route::apiResource('armazens', ArmazemController::class);
    Route::apiResource('tipo-clientes', TipoClienteController::class);
    Route::apiResource('clientes', ClienteController::class);
    //Route::apiResource('produtos', ProdutoController::class);
    Route::get('motivo-isencao', [MotivoIsencaoController::class, 'index']);
    Route::apiResource('tipo-stock', TipoStockController::class);
    Route::apiResource('movimento-stock', MovimentoStockController::class);
    
    //Route::apiResource('utilizadores', UtilizadorController::class);
    
    Route::apiResource('tipos-taxa-iva', TipoTaxaIvaController::class);
    
    Route::apiResource('contas', ContaController::class);
    Route::apiResource('bancos', BancoController::class);
    
    Route::apiResource('caixas', CaixaController::class);
});
Route::apiResource('unidades', UnidadeController::class);
Route::patch('/unidades/{id}/definir-predefinida', [UnidadeController::class, 'definirComoPredefinida']);
Route::apiResource('empresas', EmpresaController::class);
Route::post('documentos/recibo', [DocumentoController::class, 'storeRecibo']);
Route::post('documentos/nota-credito', [DocumentoController::class, 'storeNotaCredito']);
Route::post('documentos/fatura-compra', [DocumentoController::class, 'storeFaturaCompra']);
Route::post('documentos/{id}/anular', [DocumentoController::class, 'anularDocumento']);
Route::get('documentos/relatorio', [DocumentoController::class, 'pdfRelatorioDocumento']);
Route::get('documentos/faturacao-item', [DocumentoController::class, 'listFaturacaoPorItem']);
Route::get('documentos/conta-corrente-cliente/{clienteId}', [DocumentoController::class, 'listContaCorrenteCliente']);
Route::get('documentos/relatorio-conta-corrente/{clienteId}', [DocumentoController::class, 'pdfContaCorrenteCliente']);
Route::get('documentos/pagamentos-em-falta', [DocumentoController::class, 'listPagamentosEmFalta']);
Route::get('documentos/relatorio-pagamentos-em-falta', [DocumentoController::class, 'pdfPagamentosEmFalta']);
Route::get('documentos/pagamentos-efetuados', [DocumentoController::class, 'listPagamentosEfetuados']);
Route::get('documentos/relatorio-pagamentos-efetuados', [DocumentoController::class, 'pdfPagamentosEfetuados']);
Route::get('documentos/relatorio-faturacao-item', [DocumentoController::class, 'pdfRelatorioFaturacaoPorItem']);
Route::get('documentos/faturacao-por-colaborador/{utilizadorId}',[DocumentoController::class, 'listFaturacaoPorColaborador']);
Route::get('documentos/relatorio-faturacao-por-colaborador', [DocumentoController::class, 'pdfRelatorioFaturacaoPorColaborador']);
Route::apiResource('documentos', DocumentoController::class);
Route::patch('documentos/{id}/finalizar', [DocumentoController::class, 'finalizarDocRascunho']);
Route::delete('documentos/{id}/delete-rascunho',[DocumentoController::class, 'destroyDocRascunho']);
Route::post('/documentos/{id}/transformar', [DocumentoController::class, 'transformarDocumento']);
Route::get('documentos/tipo/fatura', [DocumentoController::class, 'listFaturas']);
Route::get('documentos/tipo/fatura-proforma', [DocumentoController::class, 'listFaturaProforma']);
Route::get('documentos/tipo/guia', [DocumentoController::class, 'listGuias']);
Route::get('documentos/tipo/nota-credito', [DocumentoController::class, 'listNotaCredito']);
Route::apiResource('fatura-compra', FaturaCompraController::class);
Route::get('caixas/armazem/{armazemId}', [CaixaController::class, 'getByArmazem']);
Route::get('/documento/{id}/pdf', [DocumentoController::class, 'gerarPdf']);
Route::get('/documento/{id}/pdf/recibo', [DocumentoController::class, 'gerarPdfRecibo']);
Route::get('/documento/{id}/pdf/fatura-compra', [DocumentoController::class, 'gerarPdfFaturaCompra']);
Route::get('/documento/num-last-doc', [DocumentoController::class, 'NumLastDoc']);

//});