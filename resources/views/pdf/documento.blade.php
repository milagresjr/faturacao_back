<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Factura Recibo</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
            position: relative;
        }

        .header, .footer {
            text-align: center;
            font-weight: bold;
        }

        .section {
            margin-bottom: 20px;
        }

        .table-main {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

         .table-main th {
            border-top: 2px solid #333;
            border-bottom: 1px solid #333;
            padding: 2px;
            text-align: left;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .table th, .table td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
        }

        .table-imposto th {
            border-top: 2px solid #333;
            border-bottom: 1px solid #333;
            padding: 1px;
            text-align: left;
        }

        .table-meio-pag th {
            border-top: 2px solid #333;
            border-bottom: 1px solid #333;
            padding: 1px;
            text-align: left;
        }

         .table-totais th {
            border-top: 2px solid #333;
            border-bottom: 1px solid #333;
            padding: 1px;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .small {
            font-size: 10px;
        }

        .borderless td {
            border: none !important;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .col-left {
            display: block;
            float: left;
            width: 48%;
        }

        .col-right {
            display: block;
            float: right;
            width: 30%;
        }

      
    </style>
</head>
<body>

   <div class="section" style="width: 100%; position: relative; margin-bottom: 100px;">
     <div class="col-left">
        <h2>{{ $documento->empresa_nome ?? 'NEXPERIENCE LDA' }}</h2>
        <span>{{ $documento->empresa_endereco ?? 'Rua da Lionesa, 446, G20 - 4465-671 Leça do Balio' }}</span> <br>
        <span>Contribuinte: {{ $documento->empresa_nif ?? '509442013' }}</span> <br>
    </div>

    <div class="col-right" style="text-align: right;">

    <strong>Factura Recibo</strong><br>
    <small>Original</small> <br>
         
    <strong style="margin-top: 15px;">{{ $documento->cliente_nome ?? "Milagres jr" }}</strong><br>
    <span>{{ $documento->cliente_endereco ?? "Luanda, viana" }}</span>
               
    </div>
   </div>

    <div class="section">
        <h3 style="margin-bottom: 3px;">FR T09P2025/11</h3>
        <table style="width: 100%; border-top: 2px solid #000;">
            <tr style="">
                <th style="border-bottom: 1px solid #000; text-align: left; margin: 3px;">Data de Emissão</th>
                <th style="border-bottom: 1px solid #000; text-align: left; margin: 3px;">Vencimento</th>
                <th style="border-bottom: 1px solid #000; text-align: left; margin: 3px;">Contribuinte</th>
                <th style="border-bottom: 1px solid #000; text-align: left; margin: 3px;">V/ Ref.</th>
            </tr>
            <tr>
                <td>{{ \Carbon\Carbon::parse($documento->data_emissao)->format('Y-m-d') }}</td>
                <td>{{ \Carbon\Carbon::parse($documento->data_vencimento)->format('Y-m-d') }}</td>
                <td>{{ $documento->cliente_nif ?? "9999999999" }}</td>
                <td>{{ $documento->tipo_sigla ?? "FR" }} {{ $documento->id ?? "123" }}</td>
            </tr>
        </table>
    </div>

    <div style="font-size: 10px; margin-top: -25px;">
        <p><strong>Observações:</strong> {{ $documento->observacoes ?? 'Documento emitido para fins de Formação. Não tem validade fiscal.' }}</p>
    </div>

    <div class="section">
        <table class="table-main">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>Preço Uni.</th>
                    <th>Uni.</th>
                    <th>Qtd.</th>
                    <th>IVA</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documento->itens as $item)
                    <tr>
                        <td>{{ $item->produto_codigo ?? "23" }}</td>
                        <td>{{ $item->produto_nome ?? "Produto01" }}</td>
                        <td >{{ number_format($item->preco_unitario, 2, ',', '.') }} Kz</td>
                        <td>Uni</td>
                        <td >{{ $item->quantidade ?? "23" }}</td>
                        <td >{{ $item->iva_percent ?? "14" }}</td>
                        <td  class="right">{{ number_format($item->total, 2, ',', '.') }} Kz</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section row" style="width: 100%;">
        <!-- Impostos à esquerda -->
        <div class="col-left">
            <div>
            <table class="table-imposto" style="width: 80%;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Taxa</th>
                        <th style="text-align: left;">Incidência</th>
                        <th style="text-align: right;">Imposto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quadroImposto as $taxa => $valores)
                        <tr>
                            <td>{{ $taxa == 0 ? 'Isento' : $taxa . '%' }}</td>
                            <td>{{ number_format($valores['incidencia'], 2, ',', '.') }} Kz</td>
                            <td style="text-align: right;">{{ number_format($valores['imposto'], 2, ',', '.') }} Kz</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="" style="margin-top: 15px;">
            <table class="table-meio-pag" style="width: 80%;">
                <tr style="border-top: 1px solid #000;">
                    <th class="text-align: left;">Meio de Pagamento</th>
                    <th></th>
                </tr>
                <tr>
                    <td>{{ $documento->forma_pagamento ?? 'Dinheiro' }}</td>
                    <td style="text-align: right;">{{ number_format($documento->total_geral, 2, ',', '.') }} Kz</td>
                </tr>
            </table>
        </div>
        </div>

       <!-- Totais à direita -->
        <div style="margin-top: 150px; width: 250px; display: block; float: right;">
            <table style="width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000;">
                <tr>
                    <td style="text-align: left; padding: 2px;">Total liquido</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_sem_desconto, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">Total desconto</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->desconto_total, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">IVA</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_impostos, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">Retenção</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format(0, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">Subtotal</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total, 2, ',', '.') }}
                    </td>
                </tr>
                <tr style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
                    <th style="text-align: left; padding: 2px;">Total (Kz)</th>
                    <td style="text-align: right; padding: 2px;">
                        <strong>{{ number_format($documento->total_geral, 2, ',', '.') }}</strong>
                    </td>
                </tr>
            </table>
        </div>

    </div>



</body>
</html>
