<?php

use App\Http\Controllers\Api\ArmazemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BancoController;
use App\Http\Controllers\Api\CaixaController;
use App\Http\Controllers\Api\CategoriaProdutoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ConfiguracaoFaturaController;
use App\Http\Controllers\Api\ContaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\DocumentoInternoController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\FaturaCompraController;
use App\Http\Controllers\Api\FilialController;
use App\Http\Controllers\Api\FornecedorController;
use App\Http\Controllers\Api\LoteController;
use App\Http\Controllers\Api\MarcaController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\ProdutoController;
use App\Http\Controllers\Api\SubCategoriaController;
use App\Http\Controllers\Api\TipoClienteController;
use App\Http\Controllers\Api\TipoProdutoController;
use App\Http\Controllers\Api\MotivoIsencaoController;
use App\Http\Controllers\Api\MovimentoStockController;
use App\Http\Controllers\Api\PagamentoDocumentoCompraController;
use App\Http\Controllers\Api\PermissaoController;
use App\Http\Controllers\Api\SaftController;
use App\Http\Controllers\Api\SerieController;
use App\Http\Controllers\Api\TipoStockController;
use App\Http\Controllers\Api\TipoTaxaIvaController;
use App\Http\Controllers\Api\UnidadeController;
use App\Http\Controllers\Api\UtilizadorController;
use App\Http\Middleware\AuthenticateWithRememberToken;
use App\Http\Middleware\ForcePasswordChange;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware('api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    Route::post('/empresas', [EmpresaController::class, 'store']);
});

Route::post('/forgot-password', [UtilizadorController::class, 'sendCodePasswordReset']);
Route::patch('/reset-new-password', [UtilizadorController::class, 'resetNewPassword']);

Route::get('/calcular-hash-agt/{id}', [DocumentoController::class, 'calcularHashAGT']);

Route::get('/generate-saft', [SaftController::class, 'gerarSaft']);
Route::middleware([AuthenticateWithRememberToken::class])->group(function () {


    Route::get('/list-saft-faturas', [SaftController::class, 'listFaturas']);

    Route::get('/relatorio-produtos', [ProdutoController::class, 'relatorioProdutos']);

    Route::apiResource('utilizadores', UtilizadorController::class);
    Route::middleware('auth:sanctum')->post('utilizadores/change/password', [UtilizadorController::class, 'changePassword']);
    Route::middleware('auth:sanctum')->post('utilizadores/change/estado/{id}', [UtilizadorController::class, 'changeEstado']);
    Route::middleware('auth:sanctum')->patch('empresas/fill-data', [EmpresaController::class, 'fillDataEmpresaUser']);

    Route::middleware('auth:sanctum')->post('utilizadores/check-password-change', [UtilizadorController::class, 'changeNewPassword']);

    Route::prefix('configuracoes-fatura')->group(function () {
        Route::get('/{empresaId}', [ConfiguracaoFaturaController::class, 'show']);
        Route::post('/', [ConfiguracaoFaturaController::class, 'store']);
        Route::put('/{id}', [ConfiguracaoFaturaController::class, 'update']);
    });

    Route::middleware(ForcePasswordChange::class)->group(function () {

        Route::apiResource('produtos', ProdutoController::class);
        Route::patch('/produtos/{id}/change-estado', [ProdutoController::class, 'changeEstado']);

        Route::get('/dashboard/summary', [DashboardController::class, 'getSummary']);

        Route::get('/dashboard/monthly-sales', [DashboardController::class, 'getMonthlyData']);
        Route::get('/dashboard/faturas-qtd-mensal', [DashboardController::class, 'getMonthlyValue']);
        Route::get('/dashboard/percent-tipo-doc', [DashboardController::class, 'percentagemTiposDocumentosMesAtual']);
        Route::get('/dashboard/percent-estado-doc', [DashboardController::class, 'percentagemEstadoFaturasMesAtual']);
        Route::get('/dashboard/top-clientes-devedores', [DashboardController::class, 'topClientesDevedores']);

        // Add routes that require password change here
        Route::apiResource('perfis', PerfilController::class);
        Route::get('perfis/list/empresa', [PerfilController::class, 'listByEmpresa']);

        Route::apiResource('permissoes', PermissaoController::class);

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

        //Route::apiResource('utilizadores', UtilizadorController::class);

        Route::apiResource('tipos-taxa-iva', TipoTaxaIvaController::class);

        Route::apiResource('contas', ContaController::class);
        Route::apiResource('bancos', BancoController::class);

        Route::apiResource('caixas', CaixaController::class);
        Route::apiResource('movimento-stock', MovimentoStockController::class);
        Route::patch('/alterar-stock-minimo/{idArmazem}/{idProduto}', [MovimentoStockController::class, 'alterarStockMinimo']);
        Route::apiResource('fatura-compra/pagamento', PagamentoDocumentoCompraController::class);



        Route::patch('/unidades/{id}/definir-predefinida', [UnidadeController::class, 'definirComoPredefinida']);
        Route::apiResource('empresas', EmpresaController::class)->except(['store']);

        Route::patch('/armazens/{id}/definir-predefinido', [ArmazemController::class, 'alterarPredefinido']);

        Route::get('documento-interno/{id}', [DocumentoInternoController::class, 'show']);
        Route::get('documento-interno/transferencia/{id}/pdf', [DocumentoInternoController::class, 'gerarPdfDocTransferencia']);
        Route::get('documento-interno/nota-quebra/{id}/pdf', [DocumentoInternoController::class, 'gerarPdfDocNotaQuebra']);
        Route::get('documento-interno/inventario/{id}/pdf', [DocumentoInternoController::class, 'gerarPdfDocInventario']);

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
        Route::get('documentos/faturacao-por-colaborador/{utilizadorId?}', [DocumentoController::class, 'listFaturacaoPorColaborador']);
        Route::get('documentos/relatorio-faturacao-por-colaborador', [DocumentoController::class, 'pdfRelatorioFaturacaoPorColaborador']);
        Route::apiResource('documentos', DocumentoController::class);

        Route::get('/documento/{id}/pdf', [DocumentoController::class, 'gerarPdf']);

        Route::patch('documentos/{id}/finalizar', [DocumentoController::class, 'finalizarDocRascunho']);
        Route::delete('documentos/{id}/delete-rascunho', [DocumentoController::class, 'destroyDocRascunho']);
        Route::post('/documentos/{id}/transformar', [DocumentoController::class, 'transformarDocumento']);
        Route::get('documentos/tipo/fatura', [DocumentoController::class, 'listFaturas']);
        Route::get('documentos/tipo/fatura-proforma', [DocumentoController::class, 'listFaturaProforma']);
        Route::get('documentos/tipo/guia', [DocumentoController::class, 'listGuias']);
        Route::get('documentos/tipo/nota-credito', [DocumentoController::class, 'listNotaCredito']);

        Route::apiResource('fatura-compra', FaturaCompraController::class);
        Route::get('relatorio/fatura-compra', [FaturaCompraController::class, 'pdfRelatorioDocumentoCompra']);

        Route::get('caixas/armazem/{armazemId}', [CaixaController::class, 'getByArmazem']);
        Route::get('/documento/{id}/pdf/fatura-compra', [DocumentoController::class, 'gerarPdfFaturaCompra']);
        Route::get('/documento/num-last-doc/{idSerie}', [DocumentoController::class, 'NumLastDoc']);

        //rota para lotes
        Route::apiResource('lotes', LoteController::class);
        Route::get('lotes/produto/{produtoId}', [LoteController::class, 'getLotesByProduto']);
        Route::get('lotes/empresa/{empresaId}', [LoteController::class, 'getLotesByEmpresa']);

        Route::get('/tipo-documento/series', [SerieController::class, 'getSeriesByTipoDocumento']);
        Route::apiResource('series', SerieController::class);


        Route::get('/documento/{id}/pdf/recibo', [DocumentoController::class, 'gerarPdfRecibo']);

        //});
        Route::patch('/series/{id}/definir-padrao', [SerieController::class, 'definirComoPadrao']);
        Route::patch('/series/{id}/definir-ativo', [SerieController::class, 'alterarAtivo']);
        Route::apiResource('unidades', UnidadeController::class);
    });
});
