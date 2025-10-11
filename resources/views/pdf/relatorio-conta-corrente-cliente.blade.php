<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Conta Corrente de Cliente</title>
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

        /* ==== CLIENTE ==== */
        .cliente-info {
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 15px;
            padding: 8px 10px;
            background-color: #f9f9f9;
        }

        .cliente-info h3 {
            margin: 0 0 4px 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #333;
        }

        .cliente-info p {
            margin: 1px 0;
            font-size: 10px;
            color: #444;
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
        <h2>{{ $dadosEmpresa['nome'] }}</h2>
        <p>NIF: {{ $dadosEmpresa['nif'] }}</p>
        <p>{{ $dadosEmpresa['endereco'] }}</p>
        <p>Tel: {{ $dadosEmpresa['telefone'] }} | Email: {{ $dadosEmpresa['email'] }}</p>
    </header>

    {{-- Título do Relatório --}}
    <h1>Relatório de Conta Corrente de Cliente</h1>
    @if ($dataInicial)
        <div class="intervalo">De {{ $dataInicial }} até {{ $dataFinal }}</div>
    @endif

    {{-- Dados do Cliente --}}
    <div class="cliente-info">
        <h3>Cliente</h3>
        <p><strong>Nome:</strong> {{ $cliente['nome'] ?? 'N/D' }}</p>
        <p><strong>NIF:</strong> {{ $cliente['nif'] ?? 'N/D' }}</p>
        <p><strong>Telefone:</strong> {{ $cliente['telefone'] ?? 'N/D' }}</p>
        <p><strong>Email:</strong> {{ $cliente['email'] ?? 'N/D' }}</p>
        <p><strong>Endereço:</strong> {{ $cliente['endereco'] ?? 'N/D' }}</p>
    </div>

    {{-- Tabela de Documentos --}}
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Documento</th>
                <th>Débito</th>
                <th>Crédito</th>
                <th style="text-align:right;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @if (count($movimentos) > 0)
                @foreach ($movimentos as $item)
                    <tr>
                        <td>{{ $item['data'] }}</td>
                        <td>{{ $item['documento'] }}</td>
                        <td>{{ number_format($item['debito'], 2, ',', '.') }}</td>
                        <td>{{ number_format($item['credito'], 2, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item['saldo'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="text-align:center;">Nenhum dado encontrado</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Tabela de Totais --}}
    <table class="totais">
        <tr>
            <th>Total Débito</th>
            <td>{{ number_format($totais->total_debito, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Crédito</th>
            <td>{{ number_format($totais->total_credito, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Saldo Total</th>
            <td><strong>{{ number_format($saldoFinal, 2, ',', '.') }}</strong></td>
        </tr>
    </table>
</body>

</html>
