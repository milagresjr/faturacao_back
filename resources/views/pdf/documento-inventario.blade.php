<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>{{ $documento->tipo_nome }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
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

        .descricao {
            margin-bottom: 15px;
            font-style: italic;
        }

        table.itens {
            width: 100%;
            border-collapse: collapse;
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

        .assinaturas {
            margin-top: 60px;
            width: 100%;
        }

        .assinaturas td {
            text-align: center;
            padding-top: 40px;
        }

        .rodape {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
        }
    </style>
</head>

<body>

{{-- HEADER --}}
<div class="header">
    @if(!empty($dadosPersonalizacaoFatura) && $dadosPersonalizacaoFatura->mostrar_logo && $dadosPersonalizacaoFatura->logo && $src)
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
    {{ $documento->tipo_nome }}
</div>

{{-- DESCRIÇÃO DINÂMICA --}}
<div class="descricao">
    @if ($documento->tipo_sigla === 'EI')
        Documento utilizado para registo de entrada manual de produtos no inventário,
        proveniente de contagem física, ajuste ou correção de stock.
    @else
        Documento utilizado para registo de saída manual de produtos do inventário,
        proveniente de contagem física, ajuste ou correção de stock.
    @endif
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
            <td>
                <strong>
                    @if ($documento->tipo_sigla === 'EI')
                        Total da Entrada
                    @else
                        Total da Saída
                    @endif
                </strong>
            </td>
            <td>
                <strong>{{ number_format($documento->total_geral, 2, ',', '.') }}</strong>
            </td>
        </tr>
    </table>
</div>

<div style="clear: both;"></div>

{{-- ASSINATURAS --}}
<div class="assinaturas">
    <table width="100%">
        <tr>
            <td>
                ___________________________<br>
                Responsável pelo Inventário
            </td>
            <td>
                ___________________________<br>
                Supervisor / Gerência
            </td>
        </tr>
    </table>
</div>

{{-- RODAPÉ --}}
{{-- <div class="rodape">
    Documento interno de inventário<br>
    Gerado em {{ now()->format('d/m/Y H:i') }}
</div> --}}

</body>
</html>
