<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Pagamentos em Falta</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #333;
        }

        header {
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        header h2 {
            margin: 0;
            font-size: 16px;
            color: #222;
        }

        header p {
            margin: 1px 0;
            font-size: 10px;
            color: #555;
        }

        h1 {
            text-align: center;
            margin: 10px 0 5px 0;
            font-size: 14px;
            text-transform: uppercase;
            color: #444;
        }

        .intervalo {
            text-align: center;
            font-size: 10px;
            color: #444;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            text-align: left;
        }

        table th {
            background-color: #f5f5f5;
            font-size: 11px;
            text-align: center;
            color: #333;
        }

        table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        table td {
            font-size: 10px;
        }

        .totais {
            width: 45%;
            float: right;
            margin-top: 15px;
        }

        .totais th,
        .totais td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: right;
            font-size: 11px;
        }

        .totais th {
            background: #f5f5f5;
            font-weight: normal;
            color: #333;
        }

        .totais td strong {
            font-size: 12px;
            color: #000;
        }
    </style>
</head>

<body>
    {{-- Cabeçalho da Empresa --}}
    <header>
        <h2>{{ $dadosEmpresa['nome'] }}</h2>
        <p>NIF: {{ $dadosEmpresa['nif'] }}</p>
        <p>{{ $dadosEmpresa['endereco'] }}</p>
        <p>Tel: {{ $dadosEmpresa['telefone'] }} | Email: {{ $dadosEmpresa['email'] }}</p>
    </header>

    {{-- Título do Relatório --}}
    <h1>Relatório de Pagamentos em Falta</h1>
    @if ($dataInicial)
        <div class="intervalo">De {{ $dataInicial }} até {{ $dataFinal }}</div>
    @endif

    {{-- Tabela de Documentos --}}
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Data Emissão</th>
                <th style="width: 15%;">Data Vencimento</th>
                <th style="width: 20%;">Documento</th>
                <th style="width: 25%;">Cliente</th>
                <th style="width: 10%; text-align:right;">Total</th>
                <th style="width: 10%; text-align:right;">Pago</th>
                <th style="width: 10%; text-align:right;">Em Falta</th>
            </tr>
        </thead>
        <tbody>
            @if (count($resultados) > 0)
                @foreach ($resultados as $item)
                    @php
                        $vencido = \Carbon\Carbon::parse($item->data_vencimento)->lt(now());
                    @endphp
                    <tr style="{{ $vencido ? 'background-color:#ffe6e6;' : '' }}">
                        <td>{{ \Carbon\Carbon::parse($item->data_emissao)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($item->data_vencimento)->format('d/m/Y') }}</td>
                        <td style="text-align:center;">{{ $item->num_fatura }}</td>
                        <td>{{ $item->cliente_nome }}</td>
                        <td style="text-align:right;">{{ number_format($item->total_geral, 2, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item->total_pago, 2, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item->valor_em_falta, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="7" style="text-align:center;">Nenhum dado encontrado</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Totais --}}
    @php
        $hoje = now();
        $totalDocumentos = count($resultados);
        $totalVencido = $resultados
            ->filter(fn($item) => \Carbon\Carbon::parse($item->data_vencimento)->lt($hoje))
            ->sum('valor_em_falta');
        $totalNaoVencido = $resultados
            ->filter(fn($item) => \Carbon\Carbon::parse($item->data_vencimento)->gte($hoje))
            ->sum('valor_em_falta');
        $totalEmFalta = $resultados->sum('valor_em_falta');
    @endphp

    <table class="totais">
        <tr>
            <th>Total de Documentos</th>
            <td>{{ number_format($totalDocumentos, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Vencido</th>
            <td>{{ number_format($totalVencido, 2, ',', '.') }} Kz</td>
        </tr>
        <tr>
            <th>Total Não Vencido</th>
            <td>{{ number_format($totalNaoVencido, 2, ',', '.') }} Kz</td>
        </tr>
        <tr>
            <th>Total em Falta</th>
            <td><strong>{{ number_format($totalEmFalta, 2, ',', '.') }} Kz</strong></td>
        </tr>
    </table>
</body>

</html>
