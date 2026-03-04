<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Documento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getSummary(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        // Buscar total faturado
        $faturas = Documento::where('tipo_sigla', ['FT', 'FR', 'RC', 'RG'])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', 'anulado')
            ->sum('total_geral');


        //Buscar total recebido
        $totalRecebido = Documento::where('tipo_sigla', ['RC', 'RG'])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', 'anulado')
            ->sum('total_geral');


        $baseQuery = DB::table("documentos as d")
            ->leftJoin("documento_relacoes as dr", "dr.documento_relacionado_id", "=", "d.id")
            ->leftJoin("meios_pagamento_documento as mp", "mp.documento_id", "=", "dr.documento_id")
            ->where("d.empresa_id", $idEmpresa)
            ->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "ND"])
            ->groupBy("d.id", "d.total_geral")
            ->selectRaw("
        (d.total_geral - COALESCE(SUM(mp.valor), 0)) as valor_em_falta
    ")
            ->havingRaw("(d.total_geral - COALESCE(SUM(mp.valor), 0)) > 0");

        $totalEmFalta = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery)
            ->sum("valor_em_falta");


        //Clientes Ativos
        $clientesAtivos = Cliente::where('empresa_id', $idEmpresa)
            ->where('estado', '1')->count();

        //Faturas vencidas
        // $faturasVencidas = Documento::where();

        return response()->json([
            'faturacao_total' => $faturas,
            'total_recebido' => $totalRecebido,
            "total_em_falta" => $totalEmFalta ?? 0,
            'clientes_ativos' => $clientesAtivos
        ]);
    }

    public function getMonthlyData(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $anoAtual = now()->year;

        $dados = DB::table('documentos')
            ->select(
                DB::raw('MONTH(created_at) as mes'),
                DB::raw('SUM(total_geral) as total')
            )
            ->where('empresa_id', $idEmpresa)
            ->whereYear('created_at', $anoAtual)
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FA'])
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total', 'mes');

        $resultado = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $resultado[] = $dados[$mes] ?? 0;
        }

        return response()->json($resultado);
    }

    public function getMonthlyValue(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $anoAtual = now()->year;

        $faturado = DB::table('documentos')
            ->select(
                DB::raw('MONTH(created_at) as mes'),
                DB::raw('COUNT(*) as total')
            )
            ->where('empresa_id', $idEmpresa)
            ->whereYear('created_at', $anoAtual)
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FA'])
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total', 'mes');

        $recebido = DB::table('documentos')
            ->select(
                DB::raw('MONTH(created_at) as mes'),
                DB::raw('COUNT(*) as total')
            )
            ->where('empresa_id', $idEmpresa)
            ->whereYear('created_at', $anoAtual)
            ->whereIn('tipo_sigla', ['RC', 'RG'])
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total', 'mes');

        $resultadoFat = [];

        $resultadoRec = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $resultadoFat[] = $faturado[$mes] ?? 0;
        }

        for ($mes = 1; $mes <= 12; $mes++) {
            $resultadoRec[] = $recebido[$mes] ?? 0;
        }

        return response()->json([
            'faturado' => $resultadoFat,
            'recebido' => $resultadoRec
        ]);
    }

    public function percentagemTiposDocumentosMesAtual(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $mesAtual = now()->month;
        $anoAtual = now()->year;

        $total = DB::table('documentos')
            ->where('empresa_id', $idEmpresa)
            ->whereMonth('created_at', $mesAtual)
            ->whereYear('created_at', $anoAtual)
            ->count();

        if ($total == 0) {
            return response()->json([
                'labels' => [],
                'series' => []
            ]);
        }

        $dados = DB::table('documentos')
            ->select(['tipo_sigla', 'tipo_nome', DB::raw('COUNT(*) as quantidade')])
            ->where('empresa_id', $idEmpresa)
            ->whereMonth('created_at', $mesAtual)
            ->whereYear('created_at', $anoAtual)
            ->groupBy('tipo_sigla', 'tipo_nome')
            ->get();

        $labels = [];
        $series = [];

        foreach ($dados as $item) {
            $labels[] = $item->tipo_nome . '(' . $item->tipo_sigla . ')';
            $series[] = round(($item->quantidade / $total) * 100, 2);
        }

        return response()->json([
            'labels' => $labels,
            'series' => $series
        ]);
    }

    public function percentagemEstadoFaturasMesAtual(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $mesAtual = now()->month;
        $anoAtual = now()->year;

        $total = DB::table('documentos')
            ->where('empresa_id', $idEmpresa)
            ->where('tipo_sigla', 'FT')
            ->whereMonth('created_at', $mesAtual)
            ->whereYear('created_at', $anoAtual)
            ->count();

        if ($total == 0) {
            return response()->json([
                'labels' => [],
                'series' => []
            ]);
        }

        $dados = DB::table('documentos')
            ->select('estado_pagamento', DB::raw('COUNT(*) as quantidade'))
            ->where('empresa_id', $idEmpresa)
            ->where('tipo_sigla', 'FT')
            ->whereMonth('created_at', $mesAtual)
            ->whereYear('created_at', $anoAtual)
            ->groupBy('estado_pagamento')
            ->get();

        $labels = [];
        $series = [];

        foreach ($dados as $item) {
            $labels[] = ucfirst(strtolower($item->estado_pagamento));
            $series[] = round(($item->quantidade / $total) * 100, 2);
        }

        return response()->json([
            'labels' => $labels,
            'series' => $series
        ]);
    }

    public function topClientesDevedores(Request $request)
    {
        $idEmpresa = $request->input('empresa_id');

        $baseQuery = DB::table("documentos as d")
            ->leftJoin("documento_relacoes as dr", "dr.documento_relacionado_id", "=", "d.id")
            ->leftJoin("meios_pagamento_documento as mp", "mp.documento_id", "=", "dr.documento_id")
            ->where('d.empresa_id', $idEmpresa)
            ->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "ND"])
            ->groupBy("d.id", "d.cliente_id", "d.total_geral")
            ->selectRaw("
            d.cliente_id,
            (d.total_geral - COALESCE(SUM(mp.valor), 0)) as valor_em_falta
        ")
            ->havingRaw("(d.total_geral - COALESCE(SUM(mp.valor), 0)) > 0");

        $top5 = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery)
            ->join("clientes as c", "c.id", "=", "sub.cliente_id")
            ->selectRaw("
            c.nome as cliente,
            c.email,
            c.nif,
            SUM(sub.valor_em_falta) as total_em_divida
        ")
            ->groupBy("sub.cliente_id", "c.nome", "c.email", "c.nif")
            ->orderByDesc("total_em_divida")
            ->limit(5)
            ->get();

        return response()->json($top5);
    }
}
