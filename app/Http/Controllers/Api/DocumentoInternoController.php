<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoInterno;
use App\Services\LogotipoService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoInternoController extends Controller
{
    public function show(string $id)
    {
        $documento = DocumentoInterno::with('itens')->find($id);

        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        return response()->json($documento);
    }

    public function gerarPdfDocTransferencia(string $id)
    {
        $documento = DocumentoInterno::with('itens')->find($id);

        $src = null;
        if ($documento->empresa_logo) {
            $src = app(LogotipoService::class)->obterSrc($documento->empresa_logo);
        }
        $dadosPersonalizacaoFatura = app(LogotipoService::class)->carregar($documento->empresa_id)['dadosPersonalizacaoFatura'];

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.documento-interno-transferencia",
            compact([
                "documento",
                "src",
                "dadosPersonalizacaoFatura",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            // $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            // $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = str_replace([" ", "/"], "_", $documento["documento-transferencia"]);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function gerarPdfDocNotaQuebra(string $id)
    {
        $doc = DocumentoInterno::where('tipo_sigla', 'NQ')->with('itens')
        ->find($id);
        
        if (! $doc) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        $logoData = app(LogotipoService::class)->carregar($doc->empresa_id);
        $src = null;
        if ($doc->empresa_logo) {
            $src = app(LogotipoService::class)->obterSrc($doc->empresa_logo);
        }
        $dadosPersonalizacaoFatura = $logoData['dadosPersonalizacaoFatura'];

        $opts = new Options();
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsRemoteEnabled(true);

        $pdf = new Dompdf($opts);

        $html = view('pdf.documento-interno-nota-quebra', [
            'documento' => $doc,
            'src' => $src,
            'dadosPersonalizacaoFatura' => $dadosPersonalizacaoFatura,
        ])->render();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getFontMetrics();

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Página {$pageNumber} / {$pageCount}";
            $font = $fontMetrics->get_font('Helvetica', 'normal');
            $size = 10;

            $marginX = 40;
            $y = $canvas->get_height() - 50;
            $lineY = $y - 5;

            $canvas->line(
                $marginX,
                $lineY,
                $canvas->get_width() - $marginX,
                $lineY,
                [0, 0, 0],
                1
            );

            $canvas->text($marginX, $y + 12, $text, $font, $size);
        });

        $filename = preg_replace('/[\/\s]+/', '_', (string) (data_get($doc, 'nota_quebra') ?? data_get($doc, 'documento') ?? "nota_quebra_{$id}"));

        return new StreamedResponse(
            function () use ($pdf, $filename) {
                $pdf->stream($filename, ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Access-Control-Allow-Origin' => 'https://softseven-faturacao-front.vercel.app',
            ]
        );
    }

    public function gerarPdfDocInventario(string $id)
    {
        $doc = DocumentoInterno::whereIn('tipo_sigla',['SI','EI'])->with('itens')->find($id);

        if (! $doc) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        $logoData = app(LogotipoService::class)->carregar($doc->empresa_id);
        $src = null;
        if ($doc->empresa_logo) {
            $src = app(LogotipoService::class)->obterSrc($doc->empresa_logo);
        }
        $dadosPersonalizacaoFatura = $logoData['dadosPersonalizacaoFatura'];

        $opts = new Options();
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsRemoteEnabled(true);

        $pdf = new Dompdf($opts);

        $html = view('pdf.documento-inventario', [
            'documento' => $doc,
            'src' => $src,
            'dadosPersonalizacaoFatura' => $dadosPersonalizacaoFatura,
        ])->render();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getFontMetrics();

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Página {$pageNumber} / {$pageCount}";
            $font = $fontMetrics->get_font('Helvetica', 'normal');
            $size = 10;

            $marginX = 40;
            $y = $canvas->get_height() - 50;
            $lineY = $y - 5;

            $canvas->line(
                $marginX,
                $lineY,
                $canvas->get_width() - $marginX,
                $lineY,
                [0, 0, 0],
                1
            );

            $canvas->text($marginX, $y + 12, $text, $font, $size);
        });

        $filename = preg_replace('/[\/\s]+/', '_', (string) (data_get($doc, 'inventario') ?? data_get($doc, 'documento') ?? "inventario_{$id}"));

        return new StreamedResponse(
            function () use ($pdf, $filename) {
                $pdf->stream($filename, ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Access-Control-Allow-Origin' => 'https://softseven-faturacao-front.vercel.app',
            ]
        );
    }
}
