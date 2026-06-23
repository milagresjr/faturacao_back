<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Facturação</title>
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
    <h1>Relatório de facturação por itens</h1>
    @if ($dataInicial)
        <span class="intervalo">De {{ $dataInicial }} até {{ $dataFinal }}</span>
    @endif

    {{-- Tabela de Documentos --}}
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nome</th>
                <th>Quantidade</th>
                <th>Valor S/Desc.</th>
                <th style="text-align:right;">Total</th>
                {{-- <th>Utilizador</th> --}}
            </tr>
        </thead>
        <tbody>
            @if (count($itensAgrupados) > 0)
                @foreach ($itensAgrupados as $item)
                    <tr>
                        <td>{{ $item['codigo'] }}</td>
                        <td>{{ $item['nome'] }}</td>
                        <td>{{ $item['quantidade'] }}</td>
                        <td>{{ number_format($item['valor'], 2, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format($item['total'], 2, ',', '.') }}</td>
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
            <th>Qtd Total</th>
            <td>{{ $totalQuantidade }}</td>
        </tr>
        <tr>
            <th>Total S/Desc</th>
            <td>{{ number_format($totalValor, 2, ',', '.') }}</td> {{-- Exemplo IVA 14% --}}
        </tr>
        <tr>
            <th>Total Geral</th>
            <td><strong>{{ number_format($totalGeral, 2, ',', '.') }}</strong></td>
        </tr>
    </table>
</body>

</html>
