<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Serie;
use App\Models\TipoTaxaIva;
use App\Services\AgtComunicacaoService;
use App\Services\AgtHashService;
use App\Services\CalculoImpostoService;
use App\Services\DocumentoFinalizeService;
use App\Services\DocumentoNumeroService;
use App\Services\DocumentoService;
use App\Services\DocumentoTransformService;
use App\Services\FaturaPdfService;
use App\Services\FaturaService;
use App\Services\NotaCreditoService;
use App\Services\ReciboService;
use App\Services\RelatorioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentoController extends Controller
{
    public function __construct(
        private FaturaService $faturaService,
        private NotaCreditoService $notaCreditoService,
        private ReciboService $reciboService,
        private DocumentoTransformService $transformService,
        private DocumentoFinalizeService $finalizeService,
        private FaturaPdfService $pdfService,
        private RelatorioService $relatorioService,
        private DocumentoNumeroService $numeroService,
        private AgtHashService $hashService,
        private CalculoImpostoService $impostoService,
        private DocumentoService $documentoService,
        private AgtComunicacaoService $agtComunicacaoService,
    ) {}

    public function index(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $search = $request->query('search');
        $tipo = $request->query('tipo');
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $status = $request->query('status');
        $entidadeId = $request->query('entidade_id');
        $idEmpresa = $request->input('empresa_id');

        $query = Documento::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('num_fatura', 'like', "%{$search}%")
                    ->orWhere('cliente_nome', 'like', "%{$search}%")
                    ->orWhere('utilizador', 'like', "%{$search}%")
                    ->orWhere('total_geral', 'like', "%{$search}%");
            });
        }

        if ($tipo) {
            $tipos = is_array($tipo) ? $tipo : [$tipo];
            $query->where(function ($q) use ($tipos) {
                $q->whereIn('tipo_sigla', $tipos)->orWhereIn('tipo_nome', $tipos);
            });
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate('data_emissao', '>=', $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate('data_emissao', '<=', $dataFinal);
        }

        if ($entidadeId) {
            $query->where('entidade_id', $entidadeId);
        }

        if ($status === 'pago') {
            $query->where('estado_pagamento', 'pago');
        } elseif ($status === 'por_pagar') {
            $query->where('estado_pagamento', 'por_pagar');
        } elseif ($status === 'vencido') {
            $query->where('estado_vencimento', 'vencido');
        }

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        $query->orderBy('created_at', 'desc');

        return response()->json($query->paginate($per_page));
    }

    public function listFaturas(Request $request)
    {
        return $this->relatorioService->listFaturas($request, 'FT,FR,FA,FG');
    }

    public function listFaturaProforma(Request $request)
    {
        return $this->relatorioService->listFaturas($request, 'PP,EC,OR,OT');
    }

    public function listGuias(Request $request)
    {
        return $this->relatorioService->listFaturas($request, 'GR,GT');
    }

    public function listNotaCredito(Request $request)
    {
        return $this->relatorioService->listFaturas($request, 'NC');
    }

    public function store(Request $request, DocumentoService $documentoService)
    {
        $validated = Validator::make($request->all(), [
            'tipo_fatura' => 'required|string',
            'sigla_fatura' => 'required|string',
            'serie_id' => 'required|integer',
            'empresa_id' => 'nullable|integer',
            'cliente_nome' => 'required|string',
            'cliente_nif' => 'required|string',
            'caixa' => 'required|string',
            'data_emissao' => 'required|date',
            'data_vencimento' => 'required|date',
            'movimenta_stock' => 'required|boolean',
            'itens' => 'required|array|min:1',
            'itens.*.produto_nome' => 'required|string',
            'itens.*.codigo_produto' => 'required|string',
            'itens.*.preco_venda' => 'required|numeric',
            'itens.*.quantidade' => 'required|integer',
            'itens.*.desconto_percent' => 'required|numeric',
            'itens.*.desconto_fixo' => 'required|numeric',
            'itens.*.iva_percent' => 'nullable|numeric',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Erro de validação.', 'errors' => $validated->errors()], 422);
        }

        $validacaoData = $this->validateInvoiceDate(
            $request->cliente_id,
            $request->data_emissao,
            $request->empresa_id
        );

        if (!$validacaoData['allowed']) {
            return response()->json([
                'message' => $validacaoData['message'],
                'error' => 'INVALID_INVOICE_DATE',
                'details' => $validacaoData
            ], 422);
        }

        return $this->faturaService->criar($request->all());
    }

    public function storeNotaCredito(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'documento_id' => 'required|integer',
            'serie_id' => 'required|integer',
            'data_emissao' => 'required|date',
            'motivo_emissao' => 'required|string',
            'meiosPagamento' => 'required|array',
            'itens' => 'required|array|min:1',
            'utilizador_id' => 'required|integer',
            'utilizador' => 'required|string',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Erro de validação.', 'errors' => $validated->errors()], 422);
        }

        return $this->notaCreditoService->criar($request->all());
    }

    public function storeRecibo(Request $request, $tipoRelacao = 'RECIBO_FATURA')
    {
        $validated = Validator::make($request->all(), [
            'tipo_fatura' => 'required|string',
            'sigla_fatura' => 'required|string',
            'data_emissao' => 'required|date',
            'total_geral' => 'required|numeric',
            'empresa_id' => 'required|integer',
            'meiosPagamento' => 'required|array|min:1',
            'meiosPagamento.*.descricao' => 'required|string',
            'meiosPagamento.*.valor' => 'required|numeric',
            'documento_relacionado_id' => 'required|integer|exists:documentos,id',
            'utilizador_id' => 'required|integer',
            'utilizador' => 'required|string',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Erro de validação.', 'errors' => $validated->errors()], 422);
        }

        return $this->reciboService->criar($request->all(), $tipoRelacao);
    }

    public function transformarDocumento(Request $request, $id)
    {
        $validated = Validator::make($request->all(), [
            'tipo_destino' => 'required|string',
            'tipo_nome_destino' => 'required|string',
            'serie_id' => 'required|integer',
            'caixa' => 'required|string',
            'movimenta_stock' => 'required|boolean',
            'itens' => 'nullable|array',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Erro de validação.', 'errors' => $validated->errors()], 422);
        }

        return $this->transformService->transformar($request->all(), $id);
    }

    public function finalizarDocRascunho(Request $request, string $id)
    {
        $validated = Validator::make($request->all(), [
            'tipo_fatura' => 'required|string',
            'sigla_fatura' => 'required|string',
            'cliente_nome' => 'required|string',
            'cliente_nif' => 'required|string',
            'caixa' => 'required|string',
            'data_emissao' => 'required|date',
            'data_vencimento' => 'required|date',
            'movimenta_stock' => 'required|boolean',
            'itens' => 'required|array|min:1',
        ]);

        if ($validated->fails()) {
            return response()->json(['message' => 'Erro de validação.', 'errors' => $validated->errors()], 422);
        }

        return $this->finalizeService->finalizar($request->all(), (int) $id);
    }

    public function destroyDocRascunho(string $id)
    {
        return $this->finalizeService->destruirRascunho((int) $id);
    }

    public function anularDocumento(string $id)
    {
        return $this->finalizeService->anularDocumento((int) $id);
    }

    public function calcularHashAGT($id)
    {
        try {
            $hash = $this->hashService->calcular((int) $id);
            return response()->json(['hash' => $hash]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }
    }

    public function gerarNumeroDocumento(int $serieId): string
    {
        return $this->numeroService->gerarNumeroDocumento($serieId);
    }

    public function gerarPdf(string $id, Request $request)
    {
        return $this->pdfService->gerarPdf((int) $id);
    }

    public function gerarPdfRecibo(string $id)
    {
        return $this->pdfService->gerarPdfRecibo((int) $id);
    }

    public function gerarPdfFaturaCompra(string $id)
    {
        return $this->pdfService->gerarPdfFaturaCompra((int) $id);
    }

    public function show(string $id)
    {
        $documento = Documento::with([
            'itens',
            'impostosDocumento',
            'meiosPagamento',
            'documentosRelacionados',
            'relacionadoEm',
        ])->find($id);

        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        return response()->json($documento);
    }

    public function NumLastDoc(string $idSerie)
    {
        $serie = DB::table('series')
            ->where('id', $idSerie)
            ->lockForUpdate()
            ->first();

        if (!$serie) {
            return response()->json(['message' => 'Série não encontrada.'], 404);
        }

        return response()->json([
            'ultimo_numero' => $serie->sequencia_atual,
            'proximo_numero' => $serie->sequencia_atual + 1,
            'formato' => "{$serie->prefixo} {$serie->ano}/" . ($serie->sequencia_atual + 1),
        ]);
    }

    public function pdfRelatorioDocumento(Request $request)
    {
        $documentos = $this->relatorioService->listFaturas($request, 'FT,FR,FA,FG,NC,RC');

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.relatorio-documentos', ['documentos' => $documentos])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('relatorio-documentos', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="relatorio-documentos.pdf"',
            ]
        );
    }

    public function listContaCorrenteCliente(Request $request, $clienteId)
    {
        return $this->relatorioService->listContaCorrenteCliente((int) $clienteId, $request);
    }

    public function pdfContaCorrenteCliente(Request $request, $clienteId)
    {
        $response = $this->relatorioService->listContaCorrenteCliente((int) $clienteId, $request);
        $data = $response->getData();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.conta-corrente', [
            'cliente' => $data->cliente ?? null,
            'documentos' => $data->documentos ?? [],
        ])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('conta-corrente', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="conta-corrente.pdf"',
            ]
        );
    }

    public function listPagamentosEmFalta(Request $request)
    {
        return $this->relatorioService->listPagamentosEmFalta($request);
    }

    public function pdfPagamentosEmFalta(Request $request)
    {
        $documentos = $this->relatorioService->listPagamentosEmFalta($request);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.pagamentos-em-falta', ['documentos' => $documentos])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('pagamentos-em-falta', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="pagamentos-em-falta.pdf"',
            ]
        );
    }

    public function listPagamentosEfetuados(Request $request)
    {
        return $this->relatorioService->listPagamentosEfetuados($request);
    }

    public function pdfPagamentosEfetuados(Request $request)
    {
        $documentos = $this->relatorioService->listPagamentosEfetuados($request);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.pagamentos-efetuados', ['documentos' => $documentos])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('pagamentos-efetuados', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="pagamentos-efetuados.pdf"',
            ]
        );
    }

    public function listFaturacaoPorItem(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $idEmpresa = $request->input('empresa_id');
        $search = $request->query('search');

        $query = Documento::with('itens')
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FG', 'NC'])
            ->where('estado_documento', 'emitido');

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        }

        $query->orderBy('data_emissao', 'desc');

        return response()->json($query->paginate($per_page));
    }

    public function pdfRelatorioFaturacaoPorItem(Request $request)
    {
        $data = $this->listFaturacaoPorItem($request)->getData();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.faturacao-item', ['documentos' => $data->data ?? []])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('faturacao-item', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="faturacao-item.pdf"',
            ]
        );
    }

    public function listFaturacaoPorColaborador(Request $request, ?string $utilizadorId = null)
    {
        return $this->relatorioService->listFaturacaoPorColaborador(
            $utilizadorId ? (int) $utilizadorId : null,
            $request
        );
    }

    public function pdfRelatorioFaturacaoPorColaborador(Request $request)
    {
        $response = $this->relatorioService->listFaturacaoPorColaborador(null, $request);
        $data = $response->getData();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $html = view('pdf.faturacao-colaborador', ['documentos' => $data->data ?? []])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('faturacao-colaborador', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="faturacao-colaborador.pdf"',
            ]
        );
    }

    public function listFaturacaoPorItem2(Request $request)
    {
        return $this->listFaturacaoPorItem($request);
    }

    private function validateInvoiceDate($clienteId, $novaDataEmissao, $empresaId = null)
    {
        if (!$clienteId) {
            return ['allowed' => true];
        }

        $query = Documento::where('cliente_id', $clienteId)
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FG'])
            ->where('estado', 'emitido');

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $ultimaFatura = $query->orderBy('data_emissao', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimaFatura) {
            $ultimaData = Carbon::parse($ultimaFatura->data_emissao);
            $novaData = Carbon::parse($novaDataEmissao);

            if ($novaData->lt($ultimaData)) {
                return [
                    'allowed' => false,
                    'message' => 'Não é permitido emitir fatura com data anterior à última fatura emitida.',
                    'ultima_data' => $ultimaData->format('d/m/Y'),
                    'ultima_fatura' => $ultimaFatura->num_fatura,
                    'data_solicitada' => $novaData->format('d/m/Y'),
                ];
            }
        }

        return ['allowed' => true];
    }

    public function comunicacaoAgtEnviar($id)
    {
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(['message' => 'Documento não encontrado.'], 404);
        }

        $resultado = $this->agtComunicacaoService->enviarDocumento($documento);

        return response()->json($resultado);
    }

    public function comunicacaoAgtConsultar($id)
    {
        $comunicacao = $this->agtComunicacaoService->consultarEstado((int) $id);

        if (!$comunicacao) {
            return response()->json(['message' => 'Comunicação não encontrada.'], 404);
        }

        return response()->json(['comunicacao' => $comunicacao]);
    }

    public function comunicacaoAgtReenviar($id)
    {
        $resultado = $this->agtComunicacaoService->reenviar((int) $id);
        return response()->json($resultado);
    }
}
