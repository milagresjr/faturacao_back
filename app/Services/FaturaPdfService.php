<?php

namespace App\Services;

use App\Models\BancoDocumento;
use App\Models\ConfiguracaoFatura;
use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\ImpostoDocumento;
use App\Models\ImpostoDocumentoCompra;
use App\Models\InfoGuia;
use App\Models\MeioPagamentoDocumento;
use App\Models\PagamentoDocumentoCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaturaPdfService
{
    public function __construct(
        private LogotipoService $logotipoService,
        private NumeroPorExtensoService $numeroPorExtensoService,
    ) {}

    public function gerarPdf(int $id)
    {
        $documento = Documento::with([
            'documentosRelacionados',
            'relacionadoEm',
            'itens',
        ])->findOrFail($id);

        $bancos = BancoDocumento::where('documento_id', $id)->get();
        $infoGuia = InfoGuia::where('id', $documento->info_guia_id)->first();

        if ($infoGuia) {
            $infoGuia->data_origem = Carbon::parse($infoGuia->data_origem)->format('Y-m-d - H:i');
            $infoGuia->data_destino = Carbon::parse($infoGuia->data_destino)->format('Y-m-d - H:i');
        }

        $meiosPagamento = MeioPagamentoDocumento::where('documento_id', $id)->get();
        $quadroImposto = ImpostoDocumento::where('documento_id', $id)->get();
        $quadroImpostoAgrupado = $this->agruparImpostos($quadroImposto);

        $itens = collect($documento->itens);
        $dadosPersonalizacaoFatura = ConfiguracaoFatura::where('empresa_id', $documento->empresa_id)->first();

        $maxLinhas = $this->calcularMaxLinhas(!empty($documento->empresa_logo));
        $paginas = $this->paginarItens($itens, $maxLinhas);

        $src = null;
        if ($documento->empresa_logo) {
            $src = $this->logotipoService->obterSrc($documento->empresa_logo);
        }

        $configFat = ConfiguracaoFatura::where('empresa_id', $documento->empresa_id)->first();
        $numVias = $configFat->num_via;
        $vias = $this->calcularVias($documento, $numVias);
        $totalPaginasPorVia = count($paginas);

        $documento->vezes_impresso += 1;
        $documento->save();

        $qrCode = $this->gerarQrCode($documento);

        $totalPorExtenso = $this->numeroPorExtensoService->converter($documento->total_geral);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $template = in_array($documento->template, ['classic', 'modern', 'minimal']) ? $documento->template : 'classic';
        $templateView = $template === 'modern' ? 'pdf.documento-modern' : 'pdf.documento-classic';

        $html = view($templateView, compact(
            'documento',
            'paginas',
            'quadroImpostoAgrupado',
            'bancos',
            'meiosPagamento',
            'infoGuia',
            'src',
            'dadosPersonalizacaoFatura',
            'vias',
            'totalPaginasPorVia',
            'qrCode',
            'totalPorExtenso'
        ))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = str_replace([' ', '/'], '_', $documento->num_fatura);

        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                $dompdf->stream($filename, ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    public function gerarPdfRecibo(int $id)
    {
        $documento = Documento::with([
            'documentosRelacionados',
            'relacionadoEm',
        ])->findOrFail($id);

        $docRelacionado = $documento->relacionadoEm->first();
        $pagamento = MeioPagamentoDocumento::where('documento_id', $id)->first();
        $valorPago = $pagamento?->valor ?? 0;

        if ($docRelacionado) {
            $totalRecibos = $docRelacionado->documentosRelacionados()
                ->wherePivot('tipo_relacao', 'RECIBO_FATURA')
                ->sum('total_geral');

            $totalNotasCredito = $docRelacionado->documentosRelacionados()
                ->wherePivot('tipo_relacao', 'NOTA_DE_CREDITO_FATURA')
                ->sum('total_geral');

            $saldoDevedor = max(0, $docRelacionado->total_geral - $totalRecibos - $totalNotasCredito);
        } else {
            $saldoDevedor = 0;
        }

        $bancos = BancoDocumento::where('documento_id', $id)->get();
        $meiosPagamento = MeioPagamentoDocumento::where('documento_id', $id)->get();

        $src = null;
        if ($documento->empresa_logo) {
            $src = $this->logotipoService->obterSrc($documento->empresa_logo);
        }
        $dadosPersonalizacaoFatura = ConfiguracaoFatura::where('empresa_id', $documento->empresa_id)->first();

        $configFat = ConfiguracaoFatura::where('empresa_id', $documento->empresa_id)->first();
        $numVias = $configFat->num_via;
        $vias = $this->calcularVias($documento, $numVias);

        $qrCode = $this->gerarQrCode($documento);

        $totalPorExtenso = $this->numeroPorExtensoService->converter($valorPago);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = view('pdf.recibo', compact(
            'documento',
            'docRelacionado',
            'bancos',
            'meiosPagamento',
            'valorPago',
            'saldoDevedor',
            'src',
            'dadosPersonalizacaoFatura',
            'vias',
            'qrCode',
            'totalPorExtenso'
        ))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $canvas->text(40, $canvas->get_height() - 38, 'FzBf-Processado por programa validado n. /AGT/2019', $fontMetrics->get_font('Helvetica', 'normal'), 10);
            $canvas->text(40, $canvas->get_height() - 26, "Página $pageNumber / $pageCount", $fontMetrics->get_font('Helvetica', 'normal'), 10);
            $canvas->line(40, $canvas->get_height() - 43, $canvas->get_width() - 40, $canvas->get_height() - 43, [0, 0, 0], 1);
        });

        $filename = str_replace([' ', '/'], '_', $documento->num_fatura);

        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    public function gerarPdfFaturaCompra(int $id)
    {
        $documento = DocumentoCompra::with([
            'itens',
            'otherItens',
            'impostosDocumento',
            'pagamentos',
        ])->findOrFail($id);

        $meiosPagamento = PagamentoDocumentoCompra::where('documento_compra_id', $id)->get();
        $quadroImposto = ImpostoDocumentoCompra::where('documento_compra_id', $id)->get();
        $quadroImpostoAgrupado = $this->agruparImpostos($quadroImposto);

        $src = null;
        $dadosPersonalizacaoFatura = ConfiguracaoFatura::where('empresa_id', $documento->empresa_id)->first();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = view('pdf.documento-compra', compact(
            'documento',
            'quadroImpostoAgrupado',
            'meiosPagamento',
            'src',
            'dadosPersonalizacaoFatura',
        ))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $canvas->text(40, $canvas->get_height() - 38, 'Página ' . $pageNumber . ' / ' . $pageCount, $fontMetrics->get_font('Helvetica', 'normal'), 10);
            $canvas->line(40, $canvas->get_height() - 43, $canvas->get_width() - 40, $canvas->get_height() - 43, [0, 0, 0], 1);
        });

        $filename = str_replace([' ', '/'], '_', $documento->num_fatura);

        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                $dompdf->stream($filename, ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    private function agruparImpostos($quadroImposto): array
    {
        $agrupado = [];
        foreach ($quadroImposto as $linha) {
            $taxa = (float) $linha['taxa'];
            if (!isset($agrupado[$taxa])) {
                $agrupado[$taxa] = [
                    'taxa' => $taxa,
                    'codigo' => $linha['codigo'],
                    'incidencia' => 0,
                    'imposto' => 0,
                    'motivos' => [],
                ];
            }
            $agrupado[$taxa]['incidencia'] += $linha['incidencia'];
            $agrupado[$taxa]['imposto'] += $linha['imposto'];
            if (!empty($linha['motivo_isencao']) && !in_array($linha['motivo_isencao'], $agrupado[$taxa]['motivos'])) {
                $agrupado[$taxa]['motivos'][] = $linha['motivo_isencao'];
            }
        }

        foreach ($agrupado as &$linha) {
            $linha['motivos'] = implode('; ', $linha['motivos']);
        }
        unset($linha);

        usort($agrupado, fn($a, $b) => (float) $b['taxa'] <=> (float) $a['taxa']);

        return $agrupado;
    }

    private function calcularMaxLinhas(bool $temLogo): int
    {
        return $temLogo ? 22 : 25;
    }

    private function paginarItens($itens, int $maxLinhas): array
    {
        $paginas = [];
        $subtotalTransportar = 0;

        foreach ($itens->chunk($maxLinhas) as $chunk) {
            $subtotalPagina = $chunk->sum('total');
            $paginas[] = [
                'itens' => $chunk,
                'valor_transportado' => $subtotalTransportar,
                'valor_transportar' => $subtotalTransportar + $subtotalPagina,
            ];
            $subtotalTransportar += $subtotalPagina;
        }

        return $paginas;
    }

    private function calcularVias(Documento $documento, int $numVias): array
    {
        $viasBase = ['Original', 'Duplicado', 'Triplicado', 'Quadruplicado', 'Quintuplicado'];
        if ($documento->vezes_impresso == 0) {
            return array_slice($viasBase, 0, $numVias);
        }
        return ['Duplicado'];
    }

    private function gerarQrCode(Documento $documento): string
    {
        $qrString = 'A:' . $documento->empresa_nif .
            '*B:' . $documento->cliente_nif .
            '*C:AO' .
            '*D:' . $documento->tipo_sigla .
            '*F:' . $documento->num_fatura .
            '*G:' . $documento->data_emissao .
            '*H:' . $documento->total_geral;

        $logoPath = storage_path('app/public/AGT/Logo-AGT.png');

        $builder = new Builder(
            writer: new PngWriter(),
            data: $qrString,
            size: 100,
            margin: 2,
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            logoPath: file_exists($logoPath) ? $logoPath : '',
            logoResizeToWidth: 32,
            logoPunchoutBackground: true
        );

        $result = $builder->build();
        return base64_encode($result->getString());
    }
}
