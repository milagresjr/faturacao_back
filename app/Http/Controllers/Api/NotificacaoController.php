<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NotificacaoValidade;
use App\Models\AlertaStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class NotificacaoController extends Controller
{
    private function getEmpresaId(Request $request)
    {
        $empresaId = $request->input('empresa_id');
        if ($empresaId) {
            return $empresaId;
        }

        $user = Auth::user();
        return $user?->empresa_id;
    }

    private function formatarItem($notificacao, $fonte)
    {
        if ($fonte === 'stock') {
            return $this->formatarStock($notificacao);
        }
        return $this->formatarValidade($notificacao);
    }

    private function formatarValidade($notificacao)
    {
        $lote = $notificacao->lote;

        return [
            'id' => $notificacao->id,
            'fonte' => 'validade',
            'tipo' => $notificacao->tipo,
            'nivel' => $notificacao->nivel,
            'mensagem' => $notificacao->mensagem,
            'lida' => (bool) $notificacao->lida,
            'data_envio' => $notificacao->created_at ? $notificacao->created_at->format('d/m/Y H:i') : null,
            'dias_restantes' => $notificacao->dias_restantes,
            'quantidade_afetada' => $notificacao->quantidade_afetada,
            'produto' => $lote?->produto ? [
                'id' => $lote->produto->id,
                'nome' => $lote->produto->nome,
                'codigo' => $lote->produto->codigo,
            ] : null,
            'lote' => $lote ? [
                'id' => $lote->id,
                'codigo_lote' => $lote->codigo_lote,
                'data_validade' => $lote->data_validade ? $lote->data_validade->format('d/m/Y') : null,
                'armazem' => $lote->armazem?->nome,
            ] : null,
        ];
    }

    private function formatarStock(AlertaStock $alerta)
    {
        $produto = $alerta->produto;

        return [
            'id' => $alerta->id,
            'fonte' => 'stock',
            'tipo' => 'stock_baixo',
            'nivel' => 'warning',
            'mensagem' => $produto
                ? "Stock mínimo atingido para: {$produto->nome}"
                : 'Stock mínimo atingido',
            'lida' => (bool) $alerta->lida,
            'data_envio' => $alerta->created_at ? $alerta->created_at->format('d/m/Y H:i') : null,
            'produto' => $produto ? [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'codigo' => $produto->codigo,
            ] : null,
            'stock' => [
                'stock_atual' => $alerta->stock_atual,
                'stock_min' => $alerta->stock?->stock_min,
                'armazem' => $alerta->armazem?->nome,
            ],
        ];
    }

    public function index(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);
        $fonte = $request->input('tipo', 'validade');
        $lida = $request->input('lida');

        $perPage = (int) $request->input('per_page', 10);
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
        $search = $request->input('search');

        if ($fonte === 'stock') {
            $query = AlertaStock::with(['produto', 'armazem', 'stock'])
                ->orderByDesc('created_at');

            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }
            if ($lida !== null && $lida !== '') {
                $query->where('lida', (bool) $lida);
            }
            if ($search) {
                $query->whereHas('produto', function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }
            if ($dataInicio) {
                $query->whereDate('created_at', '>=', Carbon::parse($dataInicio));
            }
            if ($dataFim) {
                $query->whereDate('created_at', '<=', Carbon::parse($dataFim));
            }

            $items = $query->get()->map(function ($alert) {
                return $this->formatarStock($alert);
            });
        } else {
            $query = NotificacaoValidade::with(['lote.produto', 'lote.armazem'])
                ->orderByDesc('created_at');

            if ($empresaId) {
                $query->whereHas('lote', function ($q) use ($empresaId) {
                    $q->where('empresa_id', $empresaId);
                });
            }
            if ($lida !== null && $lida !== '') {
                $query->where('lida', (bool) $lida);
            }
            if ($search) {
                $query->whereHas('lote.produto', function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }
            if ($dataInicio) {
                $query->whereDate('data_envio', '>=', Carbon::parse($dataInicio));
            }
            if ($dataFim) {
                $query->whereDate('data_envio', '<=', Carbon::parse($dataFim));
            }

            $items = $query->get()->map(function ($notif) {
                return $this->formatarValidade($notif);
            });
        }

        $page = (int) $request->input('page', 1);
        $total = $items->count();

        return response()->json([
            'data' => $items->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function stats(Request $request)
    {
        $empresaId = $this->getEmpresaId($request);

        $validadeQuery = NotificacaoValidade::query();
        if ($empresaId) {
            $validadeQuery->whereHas('lote', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            });
        }

        $stockQuery = AlertaStock::query();
        if ($empresaId) {
            $stockQuery->where('empresa_id', $empresaId);
        }

        $naoLidasValidade = (clone $validadeQuery)->where('lida', false)->count();
        $naoLidasStock = (clone $stockQuery)->where('lida', false)->count();

        return response()->json([
            'validade' => [
                'total' => $validadeQuery->count(),
                'nao_lidas' => $naoLidasValidade,
            ],
            'stock' => [
                'total' => $stockQuery->count(),
                'nao_lidas' => $naoLidasStock,
            ],
            'total_nao_lidas' => $naoLidasValidade + $naoLidasStock,
        ]);
    }

    public function marcarLida(Request $request, $id)
    {
        $request->validate([
            'tipo' => 'required|in:validade,stock',
        ]);

        $tipo = $request->input('tipo');
        $user = Auth::user();

        if ($tipo === 'stock') {
            $item = AlertaStock::findOrFail($id);
        } else {
            $item = NotificacaoValidade::findOrFail($id);
        }

        $item->lida = true;
        $item->data_leitura = now();
        $item->lida_por = $user?->id;
        $item->save();

        return response()->json(['message' => 'Notificação marcada como lida'], 200);
    }

    public function marcarTodas(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:validade,stock',
        ]);

        $tipo = $request->input('tipo');
        $empresaId = $this->getEmpresaId($request);
        $user = Auth::user();

        if ($tipo === 'stock') {
            $query = AlertaStock::where('lida', false);
            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }
            $query->update([
                'lida' => true,
                'data_leitura' => now(),
                'lida_por' => $user?->id,
            ]);
        } else {
            $query = NotificacaoValidade::where('lida', false);
            if ($empresaId) {
                $query->whereHas('lote', function ($q) use ($empresaId) {
                    $q->where('empresa_id', $empresaId);
                });
            }
            $query->update([
                'lida' => true,
                'data_leitura' => now(),
                'lida_por' => $user?->id,
            ]);
        }

        return response()->json(['message' => 'Notificações marcadas como lidas'], 200);
    }

    private function listarParaExportar($fonte, $empresaId, $lida, $search = null)
    {
        if ($fonte === 'stock') {
            $query = AlertaStock::with(['produto', 'armazem', 'stock'])->orderByDesc('created_at');
            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }
            if ($lida !== null && $lida !== '') {
                $query->where('lida', (bool) $lida);
            }
            if ($search) {
                $query->whereHas('produto', function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            }
            return $query->get()->map(function ($alert) {
                return $this->formatarStock($alert);
            });
        }

        $query = NotificacaoValidade::with(['lote.produto', 'lote.armazem'])->orderByDesc('created_at');
        if ($empresaId) {
            $query->whereHas('lote', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            });
        }
        if ($lida !== null && $lida !== '') {
            $query->where('lida', (bool) $lida);
        }
        if ($search) {
            $query->whereHas('lote.produto', function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            });
        }
        return $query->get()->map(function ($notif) {
            return $this->formatarValidade($notif);
        });
    }

    public function exportarPdf(Request $request)
    {
        $fonte = $request->input('tipo', 'validade');
        $items = collect($this->listarParaExportar($fonte, $this->getEmpresaId($request), $request->input("lida"), $request->input("search")));

        $empresa = Auth::user()?->empresa;

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view('pdf.relatorio-notificacoes', [
            'items' => $items,
            'fonte' => $fonte,
            'empresa' => $empresa,
            'data' => now()->format('d/m/Y H:i'),
        ])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($dompdf) {
                $dompdf->stream('notificacoes', ['Attachment' => false]);
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="relatorio-notificacoes.pdf"',
            ]
        );
    }

    public function exportarCsv(Request $request)
    {
        $fonte = $request->input('tipo', 'validade');
        $items = collect($this->listarParaExportar($fonte, $this->getEmpresaId($request), $request->input("lida"), $request->input("search")));

        $filename = $fonte === 'stock' ? 'notificacoes-stock' : 'notificacoes-validade';

        if ($fonte === 'stock') {
            $headings = ['ID', 'Produto', 'Código', 'Armazém', 'Stock Atual', 'Stock Mínimo', 'Data'];
            $rows = $items->map(function ($item) {
                return [
                    $item['id'],
                    $item['produto']['nome'] ?? '-',
                    $item['produto']['codigo'] ?? '-',
                    $item['stock']['armazem'] ?? '-',
                    $item['stock']['stock_atual'],
                    $item['stock']['stock_min'] ?? '-',
                    $item['data_envio'],
                ];
            });
        } else {
            $headings = ['ID', 'Tipo', 'Nível', 'Produto', 'Lote', 'Dias Restantes', 'Qtd Afetada', 'Data'];
            $rows = $items->map(function ($item) {
                return [
                    $item['id'],
                    $item['tipo'],
                    $item['nivel'],
                    $item['produto']['nome'] ?? '-',
                    $item['lote']['codigo_lote'] ?? '-',
                    $item['dias_restantes'] ?? '-',
                    $item['quantidade_afetada'] ?? '-',
                    $item['data_envio'],
                ];
            });
        }

        $csv = fopen('php://temp', 'r+');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, $headings, separator: ',', enclosure: '"', escape: '\\');
        foreach ($rows as $row) {
            fputcsv($csv, $row, separator: ',', enclosure: '"', escape: '\\');
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }
}