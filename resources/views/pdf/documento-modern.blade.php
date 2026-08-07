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

        @page {
            margin: 30px;
        }

        body {
            margin: 20 20 20 50px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #2d3748;
        }

        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .left {
            float: left;
            width: 60%;
        }

        .right {
            float: right;
            width: 35%;
            text-align: right;
        }

        .clear {
            clear: both;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
        }

        .sub {
            font-size: 11px;
            color: #555;
        }

        .box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 12px;
        }

        .table-main {
            min-height: 450px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #2563eb;
            color: #fff;
            padding: 6px;
            font-size: 10px;
            text-align: left;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px;
        }

        .right-text {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .small {
            font-size: 9px;
        }

        .totais {
            border: 2px solid #2563eb;
            border-radius: 6px;
            padding: 10px;
        }

        .totais strong {
            font-size: 14px;
            color: #2563eb;
        }

        .footer {
            /* width: 90%; */
            position: fixed;
            margin-top: 10px;
            bottom: 5px;
            left: 50px;
            right: 10px;
            font-size: 9px;
            color: #666;
        }

        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    @foreach ($vias as $via)
        @php
            $documento->via = $via;
        @endphp

        {{-- PAGINAS --}}
        @foreach ($paginas as $i => $pagina)
            <div class="header">

                <div class="left">
                    @if ($src)
                        <img src="{{ $src }}" style="width:120px; margin-bottom:5px;">
                    @endif

                    <div class="title">{{ $documento->empresa_nome }}</div>

                    @if ($dadosPersonalizacaoFatura->endereco)
                        <div class="sub">{{ $documento->empresa_endereco }}</div>
                    @endif

                    @if ($dadosPersonalizacaoFatura->nif)
                        <div class="sub"><b>NIF:</b> {{ $documento->empresa_nif }}</div>
                    @endif

                    @if ($dadosPersonalizacaoFatura->email)
                        <div class="sub">{{ $documento->empresa_email }}</div>
                    @endif

                    @if ($dadosPersonalizacaoFatura->telefone)
                        <div class="sub">{{ $documento->empresa_telefone }}</div>
                    @endif
                </div>

                <div class="right">
                    <div class="title">{{ $documento->tipo_nome }}</div>
                    <div class="sub">{{ $documento->via }}</div>

                    <br>

                    <strong>{{ $documento->cliente_nome ?? 'Cliente' }}</strong>

                    @if ($dadosPersonalizacaoFatura->endereco_cliente)
                        <div class="sub">{{ $documento->cliente_endereco }}</div>
                    @endif
                </div>

                <div class="clear"></div>
            </div>


            @if ($i === 0)
                {{-- DADOS PRINCIPAIS --}}
                <div class="box">
                    <table>
                        <tr>
                            <td><b>Documento:</b> {{ $documento->num_fatura }}</td>
                            <td><b>Emissão:</b> {{ \Carbon\Carbon::parse($documento->data_emissao)->format('Y-m-d') }}
                            </td>
                            <td><b>Vencimento:</b>
                                {{ \Carbon\Carbon::parse($documento->data_vencimento)->format('Y-m-d') }}
                            </td>
                            <td><b>NIF Cliente:</b> {{ $documento->cliente_nif ?? '999999999' }}</td>
                        </tr>
                    </table>
                </div>

                {{-- OBSERVAÇÕES --}}
                <div class="box small">
                    <b>Observações:</b>
                    {{ $documento->observacoes ?? 'Documento emitido para fins de Formação.' }}
                </div>

                {{-- GUIAS --}}
                @if ($documento->tipo_sigla === 'GT' || $documento->tipo_sigla === 'GR')
                    <div class="box">
                        <b>Dados de Transporte</b><br><br>

                        <div style="width:100%;">
                            <div style="float:left; width:48%;">
                                <b>Origem</b><br>
                                {{ $infoGuia->data_origem }}<br>
                                {{ $infoGuia->local_origem }}
                            </div>

                            <div style="float:right; width:48%;">
                                <b>Destino</b><br>
                                {{ $infoGuia->data_destino }}<br>
                                {{ $infoGuia->local_destino }}
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
                @endif
            @endif

            @if ($pagina['valor_transportado'] > 0)
                <div class="small" style="text-align:right; margin-bottom:5px;">
                    Valor Transportado: {{ number_format($pagina['valor_transportado'], 2, ',', '.') }}
                </div>
            @endif

            <div class="table-main">
                <div class="box">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descrição</th>
                                <th>Preço</th>
                                <th>Qtd</th>
                                <th>IVA</th>
                                <th>Desc.</th>
                                <th class="right-text">Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($pagina['itens'] as $item)
                                <tr>
                                    <td>{{ $item->produto_codigo }}</td>
                                    <td>{{ $item->produto_nome }}</td>
                                    <td>{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                    <td>{{ $item->quantidade }}</td>
                                    <td>{{ $item->iva_percent }}%</td>
                                    <td>
                                        @if ($item->desconto_percent != 0)
                                            {{ (int) $item->desconto_percent }}%
                                        @elseif ($item->desconto_fixo != 0)
                                            {{ number_format($item->desconto_fixo, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="right-text">{{ number_format($item->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (!$loop->last)
                <div class="small" style="text-align:right;">
                    Valor a Transportar: {{ number_format($pagina['valor_transportar'], 2, ',', '.') }}
                </div>
            @endif


            <div class="footer">
                <table style="width:100%;">
                    <tr>
                        {{-- ESQUERDA --}}
                        <td style="text-align:left; vertical-align:middle;">
                            FzBf - Processado por programa validado AGT <br>
                            Página {{ $loop->iteration }} / {{ $totalPaginasPorVia }}
                        </td>

                        {{-- DIREITA (QR CODE) --}}
                        <td style="text-align:right; vertical-align:middle;">
                            <img src="data:image/png;base64,{{ $qrCode }}" style="width:60px;">
                        </td>
                    </tr>
                </table>
            </div>

            @if (!$loop->last)
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach

        {{-- BLOCO FINAL EM DUAS COLUNAS --}}
        <div style="width:100%; margin-top: 10px;">

            {{-- ESQUERDA --}}
            <div style="float:left; width:55%;">

                {{-- IMPOSTOS --}}
                <div class="box no-break">
                    <b>Impostos</b>

                    <table>
                        <tr>
                            <th>Cód.</th>
                            <th>Taxa</th>
                            <th>Incidência</th>
                            <th class="right-text">Valor</th>
                        </tr>

                        @foreach ($quadroImpostoAgrupado as $valores)
                            <tr>
                                <td>{{ $valores['codigo'] }}</td>
                                <td>{{ (int) $valores['taxa'] }}%</td>
                                <td>{{ number_format($valores['incidencia'], 2, ',', '.') }}</td>
                                <td class="right-text">{{ number_format($valores['imposto'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>

                {{-- PAGAMENTO --}}
                @if (count($meiosPagamento) > 0)
                    <div class="box no-break">
                        <b>Meios de Pagamento</b>

                        <table>
                            @foreach ($meiosPagamento as $meioPagamento)
                                <tr>
                                    <td>{{ $meioPagamento->descricao }}</td>
                                    <td class="right-text">
                                        {{ number_format($meioPagamento->valor, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                {{-- BANCOS --}}
                @if (!empty($bancos) && count($bancos) > 0)
                    <div class="box no-break">
                        <b>Coordenadas Bancárias</b>

                        <table>
                            <tr>
                                <th>Banco</th>
                                <th>Conta</th>
                                <th class="right-text">IBAN</th>
                            </tr>

                            @foreach ($bancos as $banco)
                                <tr>
                                    <td>{{ $banco->sigla }}</td>
                                    <td>{{ $banco->numero_conta }}</td>
                                    <td class="right-text">{{ $banco->iban }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

            </div>

            {{-- DIREITA (TOTAIS) --}}
            <div style="float:right; width:40%;" class="no-break">

                <div class="totais">
                    <table>
                        <tr>
                            <td>Subtotal</td>
                            <td class="right-text">
                                {{ number_format($documento->total_sem_desconto, 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>Desconto</td>
                            <td class="right-text">
                                {{ number_format($documento->desconto_total, 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>IVA</td>
                            <td class="right-text">
                                {{ number_format($documento->total_impostos, 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>Retenção</td>
                            <td class="right-text">
                                {{ number_format($documento->retencao, 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>Troco</td>
                            <td class="right-text">
                                {{ number_format($documento->troco, 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="right-text">
                                <strong>{{ number_format($documento->total_geral, 2, ',', '.') }} Kz</strong>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>

            <div style="clear: both;"></div>
        </div>

        @if (!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach

</body>

</html>
