<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Documentos</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        header {
            text-align: left;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        header h2, header p {
            margin: 2px 0;
        }

        h1 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 18px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
        }

        table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .totais {
            width: 40%;
            float: right;
        }

        .totais th, .totais td {
            border: 1px solid #000;
            padding: 6px;
            text-align: right;
        }

        .totais th {
            background: #f2f2f2;
        }
    </style>
</head>
<body>
    {{-- Cabeçalho da Empresa --}}
    <header>
        <h2>{{ $documentos[0]->empresa_nome }}</h2>
        <p>NIF: {{ $documentos[0]->empresa_nif }}</p>
        <p>{{ $documentos[0]->empresa_endereco }}</p>
        <p>Tel: {{ $documentos[0]->empresa_telefone }}</p>
        <p>Email: {{ $documentos[0]->empresa_email }}</p>
    </header>

    {{-- Título do Relatório --}}
    <h1>Relatório de Documentos</h1>

    {{-- Tabela de Documentos --}}
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Documento</th>
                <th>Entidade</th>
                <th>NIF</th>
                <th>Total Faturado</th>
                <th>Utilizador</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($documentos as $item)
                <tr>
                <td>{{ $item->data_emissao }}</td>
                <td>{{ $item->num_fatura }}</td>
                <td>{{ $item->cliente_nome }}</td>
                <td>{{ $item->cliente_nif }}</td>
                <td>{{ $item->total_geral }}</td>
                <td>{{ $item->utilizador }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tabela de Totais --}}
    <table class="totais">
        <tr>
            <th>Subtotal</th>
            <td>{{ number_format($totalGeral,2,','.',') }}</td>
        </tr>
        <tr>
            <th>IVA</th>
            <td>11.250,00</td>
        </tr>
        <tr>
            <th>Total Geral</th>
            <td><strong>{{ number_format($totalGeral,2,','.',') }}</strong></td>
        </tr>
    </table>
</body>
</html>
