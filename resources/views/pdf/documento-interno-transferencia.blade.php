<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Documento Interno de Transferência</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .header {
            width: 100%;
            margin-bottom: 20px;
        }

        .header table {
            width: 100%;
        }

        .empresa {
            font-size: 14px;
            font-weight: bold;
        }

        .doc-info {
            text-align: right;
        }

        .titulo {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
        }

        .info {
            margin-bottom: 15px;
        }

        .info table {
            width: 100%;
            border-collapse: collapse;
        }

        .info td {
            padding: 5px;
        }

        table.itens {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.itens th,
        table.itens td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }

        table.itens th {
            background: #f2f2f2;
        }

        .totais {
            margin-top: 15px;
            width: 100%;
        }

        .totais table {
            width: 40%;
            float: right;
            border-collapse: collapse;
        }

        .totais td {
            border: 1px solid #000;
            padding: 6px;
        }

        .rodape {
            margin-top: 60px;
            text-align: center;
            font-size: 11px;
        }
    </style>
</head>

<body>

{{-- HEADER --}}
<div class="header">
    @if ($src)
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="{{ $src }}" alt="Logo" style="width: 120px; height: 80px; object-fit: contain;">
        </div>
    @endif
    <table>
        <tr>
            <td>
                <div class="empresa">{{ $documento->empresa_nome }}</div>
                <div>NIF: {{ $documento->empresa_nif }}</div>
                <div>Tel: {{ $documento->empresa_telefone }}</div>
                <div>Email: {{ $documento->empresa_email }}</div>
            </td>
            <td class="doc-info">
                <div><strong>Documento:</strong> {{ $documento->tipo_nome }}</div>
                <div><strong>Nº:</strong> {{ $documento->num_fatura }}</div>
                <div><strong>Data:</strong> {{ \Carbon\Carbon::parse($documento->data_emissao)->format('d/m/Y') }}</div>
                <div><strong>Utilizador:</strong> {{ $documento->utilizador }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- TITULO --}}
<div class="titulo">
    Documento de Transferência
</div>

{{-- INFO ARMAZÉNS --}}
<div class="info">
    <table>
        <tr>
            <td><strong>Armazém Origem:</strong></td>
            <td>{{ $documento->armazem_origem ?? '—' }}</td>
            <td><strong>Armazém Destino:</strong></td>
            <td>{{ $documento->armazem_destino ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- TABELA ITENS --}}
<table class="itens">
    <thead>
        <tr>
            <th>#</th>
            <th>Código</th>
            <th>Produto</th>
            <th>Qtd</th>
            <th>Preço Unit.</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($documento->itens as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->produto_codigo }}</td>
                <td>{{ $item->produto_nome }}</td>
                <td>{{ $item->quantidade }}</td>
                <td>{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                <td>{{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- TOTAIS --}}
<div class="totais">
    <table>
        <tr>
            <td><strong>Total Geral</strong></td>
            <td><strong>{{ number_format($documento->total_geral, 2, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td colspan="2"
                style="text-align: left; padding: 4px 2px 0 2px; font-size: 8pt; font-style: italic; border: none;">
                {{ $totalPorExtenso }}
            </td>
        </tr>
    </table>
</div>

<div style="clear: both;"></div>

</body>
</html>
