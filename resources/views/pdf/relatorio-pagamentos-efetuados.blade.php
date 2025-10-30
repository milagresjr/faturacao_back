<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Pagamentos Efetuados</title>
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
            margin: 10px 0;
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
            margin-top: 15px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }

        table th {
            background-color: #f5f5f5;
            font-size: 11px;
            text-align: center;
            color: #333;
        }

        table td {
            font-size: 10px;
        }

        table tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .totais {
            width: 35%;
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
    <header>
        <h2>{{ $dadosEmpresa['nome'] }}</h2>
        <p>NIF: {{ $dadosEmpresa['nif'] }}</p>
        <p>{{ $dadosEmpresa['endereco'] }}</p>
        <p>Tel: {{ $dadosEmpresa['telefone'] }} | Email: {{ $dadosEmpresa['email'] }}</p>
    </header>

    <h1>Pagamentos Efetuados (Recibos)</h1>

    <div class="intervalo">
        @if ($dataInicial && $dataFinal)
            De {{ $dataInicial }} até {{ $dataFinal }}
        @elseif ($dataInicial)
            A partir de {{ $dataInicial }}
        @elseif ($dataFinal)
            Até {{ $dataFinal }}
        @endif
    </div>

    {{-- <p style="font-size:10px; text-align:center;">Pagamentos efetuados à data de {{ $dataAtual }}</p> --}}

    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Nº de Recibo</th>
                <th>Nº de Fatura</th>
                <th>Vencimento</th>
                <th style="text-align:right;">Valor Pago (Kz)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($resultados as $item)
                <tr>
                    <td>{{ $item->cliente_nome }}</td>
                    <td>Recibo</td>
                    <td>{{ $item->num_recibo }}</td>
                    <td>{{ $item->num_fatura }}</td>
                    <td>{{ \Carbon\Carbon::parse($item->data_vencimento)->format('d/m/Y') }}</td>
                    <td style="text-align:right;">{{ number_format($item->valor_pago, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;">Nenhum pagamento encontrado</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totais">
        <tr>
            <th>Total Geral</th>
            <td><strong>{{ number_format($totalGeral, 2, ',', '.') }} Kz</strong></td>
        </tr>
    </table>
</body>

</html>
