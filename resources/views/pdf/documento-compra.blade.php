<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <title>{{ $documento->num_fatura }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 20 20 20 50px;
            /* margem esquerda maior */
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            position: relative;
        }

        .header,
        .footer {
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

        .table th,
        .table td {
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



        .table-banco th {
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

        @if(!empty($dadosPersonalizacaoFatura) && $dadosPersonalizacaoFatura->mostrar_logo && $dadosPersonalizacaoFatura->logo && $src)
        <div style="border-bottom: 1px solid #069; width: 100%; margin-bottom: 10px;">
            <div style="display: inline-block; vertical-align: top;">
                <img src="{{ $src }}"
                    alt="Logo"
                    style="width: 120px; height: 100px; z-index: 10; object-fit: contain; display: block;">
            </div>
            <div style="display: block; float: right; width: 200px; text-align: right; margin-top: 20px;">
                <span style="font-size: 12pt; font-weight: bold;">{{ $documento->tipo_nome }}</span> <br>
                <span style="font-size: 10pt;">{{ $documento->via }}</span>
            </div>
        </div>
        @endif

        <div class="col-left">
            <span style="font-size: 12pt; font-weight: bold;">{{ $documento->fornecedor_nome ?? '' }}</span> <br>
            <span>{{ $documento->empresa_endereco ?? '' }}</span> <br>
            <span><b>Contribuinte:</b> {{ $documento->fornecedor_nif ?? '' }}</span> <br>
            <span><b>E-mail:</b> {{ $documento->fornecedor_email ?? '' }}</span> <br>
            <span><b>Tel:</b> {{ $documento->fornecedor_telefone ?? '' }}</span> <br>
        </div>

        <div class="col-right" style="text-align: right;">
            @if(empty($dadosPersonalizacaoFatura) || !$dadosPersonalizacaoFatura->mostrar_logo || !$dadosPersonalizacaoFatura->logo || !$src)
            <span style="font-size: 12pt; font-weight: bold;">{{ $documento->tipo_nome ?? 'Fatura' }}</span> <br>
            @endif
            <span>Exmo.(s) Sr.(s)</span> <br>
            <strong style="margin-top: 15px;">{{ $documento->empresa_nome ?? '' }}</strong><br>
            <span>{{ $documento->empresa_endereco ?? '' }}</span>

        </div>

    </div>

    <div class="section">
        <h3 style="margin-bottom: 3px;">Fatura nº {{ $documento->num_fatura }}</h3>
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
                <td>{{ $documento->cliente_nif ?? '' }}</td>
                <td>{{ $documento->num_fatura }}</td>
            </tr>
        </table>
    </div>

    <div style="font-size: 10px; margin-top: 10px;">
        <p style="border-bottom: 1px solid #000;"><strong>Observações:</strong></p>
        <p>{{ $documento->observacoes ?? 'Documento emitido para fins de Formação. Não tem validade fiscal.' }}</p>
    </div>

    <div style="font-size: 10px; margin-top: 10px;">
        <p style="border-bottom: 1px solid #000;"><strong>Local de Entrega:</strong></p>
        <p>{{ $documento->local_entrega ?? '' }}</p>
    </div>

    <div class="section"
        style="border: 1px solid #000; min-height: 400px; max-height: 400px; padding: 10px; margin-top: 20px;">
        <table class="table-main">
            <thead>
                <tr>
                    <th style="width: 20%;">Código</th>
                    <th style="width: 30%;">Descrição</th>
                    <th style="width: 15%;">Preço Uni.</th>
                    <th style="width: 7%;">Qtd.</th>
                    <th style="width: 7%;">IVA</th>
                    <th style="width: 16%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                {{-- @for ($i = 1; $i <= 7; $i++) --}}
                @foreach ($documento->itens as $item)
                <tr>
                    <td>{{ $item->produto_codigo ?? '' }}</td>
                    <td>{{ $item->produto_nome ?? '' }}</td>
                    <td>{{ number_format($item->preco_custo, 2, ',', '.') }} Kz</td>
                    <td>{{ $item->quantidade ?? '' }}</td>
                    <td>{{ $item->iva_percent ?? '' }}%</td>
                    <td class="right" style="line-height: 1; padding: 0;">
                        {{ number_format($item->total, 2, ',', '.') }} Kz
                    </td>
                </tr>
                {{-- @if (!$documento->descricao !== '') --}}
                <tr class="borderless">
                    <td colspan="4" style="font-size: 10px; color: #555;">
                        <em style="font-size:8px; line-height:8px; margin:0; padding:0; display:block;">
                            {{ $item->descricao }}
                        </em>
                    </td>
                </tr>
                {{-- @endif --}}
                @endforeach
                {{-- @endfor --}}
            </tbody>
        </table>
    </div>

    <div class="section row" style="width: 100%;">
        <!-- Impostos à esquerda -->

        <div class="col-left" style="width: 70%; float: left; margin-top: 10px;">

            <table class="table-imposto" style="width: 420px; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="text-align: left;">Taxa</th>
                        <th style="text-align: left;">Incidência</th>
                        <th style="text-align: left;">Imposto</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quadroImpostoAgrupado as $valores)
                    <tr>
                        <td style="font-size: 8pt;">
                            {{ $valores['taxa'] == 0 ? '0%' : (int) $valores['taxa'] . '%' }}
                        </td>
                        <td style="font-size: 8pt;">
                            {{ number_format($valores['incidencia'], 2, ',', '.') }} Kz
                        </td>
                        <td style="font-size: 8pt;">
                            {{ number_format($valores['imposto'], 2, ',', '.') }} Kz
                        </td>
                        <td style="font-size: 8pt; text-align: right;">{{ number_format($valores['incidencia'] + $valores['imposto'], 2, ',', '.') }} Kz</td>
                    </tr>

                    @if ($valores['taxa'] == 0 && !empty($valores['motivos']))
                    <tr class="borderless">
                        <td colspan="4" style="font-size: 10px; color: #555;">
                            @foreach (explode(';', $valores['motivos']) as $motivo)
                            @if (trim($motivo) !== '')
                            <em
                                style="font-size:8px; line-height:8px; margin:0; padding:0; display:block;">
                                {{ trim($motivo) }}
                            </em>
                            @endif
                            @endforeach
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>


        </div>

        <!-- Totais à direita -->
        <div style="margin-top: 10px; width: 250px; display: block; float: right;">
            <table
                style="width: 100%; border-collapse: collapse; border-top: 1px solid #000; border-bottom: 1px solid #000;">
                <tr>
                    <td style="text-align: left; padding: 2px;">S/IVA</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_sem_desconto, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">IVA</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_impostos, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">Subtotal</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_sem_desconto + $documento->total_impostos, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding: 2px;">Desconto</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->desconto_total, 2, ',', '.') }}
                    </td>
                </tr>
                {{-- <tr>
                    <td style="text-align: left; padding: 2px;">Retenção</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->retencao, 2, ',', '.') }}
                </td>
                </tr> --}}
                {{-- <tr>
                    <td style="text-align: left; padding: 2px;">Subtotal</td>
                    <td style="text-align: right; padding: 2px;">
                        {{ number_format($documento->total_sem_desconto, 2, ',', '.') }}
                </td>
                </tr> --}}
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