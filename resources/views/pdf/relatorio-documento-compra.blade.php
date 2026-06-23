<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Factura de Compra</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #333;
        }

        header {
            border-bottom: 1px solid #ccc;
            margin-bottom: 15px;
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
            margin: 10px 0 20px 0;
            font-size: 14px;
            text-transform: uppercase;
            color: #444;
        }

        .intervalo {
            text-align: center;
            font-size: 10px;
            color: #444;
            text-transform: uppercase;
            margin: 0 0 10px 0;
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
            width: 40%;
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
        @if(!empty($dadosPersonalizacaoFatura) && $dadosPersonalizacaoFatura->mostrar_logo && $dadosPersonalizacaoFatura->logo && $src)
            <div style="text-align: center; margin-bottom: 10px;">
                <img src="{{ $src }}" alt="Logo" style="width: 120px; height: 80px; object-fit: contain;">
            </div>
        @endif
        <h2>{{ $dadosEmpresa['nome'] }}</h2>
        <p>NIF: {{ $dadosEmpresa['nif'] }}</p>
        <p>{{ $dadosEmpresa['endereco'] }}</p>
        <p>Tel: {{ $dadosEmpresa['telefone'] }} | Email: {{ $dadosEmpresa['email'] }}</p>
    </header>

    {{-- Título do Relatório --}}
    <h1>Relatório de Factura de Compra</h1>
    @if ($dataInicial)
        <span class="intervalo">De {{ $dataInicial }} até {{ $dataFinal }}</span>
    @endif

    {{-- Tabela de Documentos --}}
    <table>
        <thead>
            <tr>
                <th>Documento</th>
                <th>Fornecedor</th>
                <th>NIF</th>
                <th>Data</th>
                <th style="text-align:right;">Total S/IVA</th>
                {{-- <th>Utilizador</th> --}}
            </tr>
        </thead>
        <tbody>
            @if (count($documentos) > 0)
                @foreach ($documentos as $item)
                    <tr>
                        <td>{{ $item->num_fatura }}</td>
                        <td>{{ $item->fornecedor_nome }}</td>
                        <td>{{ $item->fornecedor_nif }}</td>
                        <td>{{ $item->data_fatura }}</td>
                        <td style="text-align:right;">{{ number_format($item->total_geral, 2, ',', '.') }}</td>
                        {{-- <td>{{ $item->utilizador }}</td> --}}
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5"style="text-align:center;">Nenhum dado encontrado</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Tabela de Totais --}}
    <table class="totais">
        <tr>
            <th>Subtotal</th>
            <td>{{ number_format($totalGeral, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>IVA</th>
            <td>{{ number_format($totalGeral * 0.14, 2, ',', '.') }}</td> {{-- Exemplo IVA 14% --}}
        </tr>
        <tr>
            <th>Total Geral</th>
            <td><strong>{{ number_format($totalGeral + $totalGeral * 0.14, 2, ',', '.') }}</strong></td>
        </tr>
    </table>
</body>

</html>
