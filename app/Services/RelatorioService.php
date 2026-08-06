<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Cliente;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioService
{
    public function listFaturas(Request $request, string $tipoSigla)
    {
        $per_page = $request->input('per_page', 10);
        $search = $request->query('search');
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
                    ->orWhere('total_geral', 'like', "%{$search}%");
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
            $query->where('cliente_id', $entidadeId);
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

        $query->whereIn('tipo_sigla', explode(',', $tipoSigla))
            ->whereNotIn('estado_documento', ['rascunho', 'anulado', 'cancelado'])
            ->orderBy('created_at', 'desc');

        return $query->paginate($per_page);
    }

    public function listContaCorrenteCliente(int $clienteId, Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $idEmpresa = $request->input('empresa_id');
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');

        $query = Documento::where('cliente_id', $clienteId)
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FG', 'NC', 'RC'])
            ->where('estado_documento', 'emitido');

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        }

        $query->orderBy('data_emissao', 'asc');

        $documentos = $query->paginate($per_page);

        $saldo = 0;
        foreach ($documentos as $doc) {
            if (in_array($doc->tipo_sigla, ['FT', 'FR', 'FG'])) {
                $saldo += $doc->total_geral;
            } elseif (in_array($doc->tipo_sigla, ['NC', 'RC'])) {
                $saldo -= $doc->total_geral;
            }
            $doc->saldo_atual = $saldo;
        }

        $cliente = Cliente::find($clienteId);

        return response()->json([
            'cliente' => $cliente,
            'documentos' => $documentos,
            'saldo_final' => $saldo,
        ]);
    }

    public function listPagamentosEmFalta(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $idEmpresa = $request->input('empresa_id');

        $query = Documento::whereIn('estado_pagamento', ['por_pagar', 'parcialmente_pago'])
            ->where('estado_documento', 'emitido')
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FG']);

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        return $query->orderBy('data_vencimento', 'asc')->paginate($per_page);
    }

    public function listPagamentosEfetuados(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $idEmpresa = $request->input('empresa_id');

        $query = Documento::where('estado_pagamento', 'pago')
            ->where('estado_documento', 'emitido');

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        return $query->orderBy('updated_at', 'desc')->paginate($per_page);
    }

    public function listFaturacaoPorItem(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $idEmpresa = $request->input('empresa_id');
        $search = $request->query('search');

        $query = Documento::whereIn('tipo_sigla', ['FT', 'FR', 'FG', 'NC'])
            ->where('estado_documento', 'emitido');

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        }

        $query->orderBy('data_emissao', 'desc');

        return $query->paginate($per_page);
    }

    public function listFaturacaoPorColaborador(?int $utilizadorId, Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $dataInicial = $request->query('data_inicial');
        $dataFinal = $request->query('data_final');
        $idEmpresa = $request->input('empresa_id');

        $query = Documento::whereIn('tipo_sigla', ['FT', 'FR', 'FG'])
            ->where('estado_documento', 'emitido');

        if ($utilizadorId) {
            $query->where('utilizador_id', $utilizadorId);
        }

        if ($idEmpresa) {
            $query->where('empresa_id', $idEmpresa);
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('data_emissao', [$dataInicial, $dataFinal]);
        }

        return $query->orderBy('data_emissao', 'desc')->paginate($per_page);
    }

    private function gerarPdfRelatorio(string $view, array $data, string $filename)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view($view, $data)->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

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
}
