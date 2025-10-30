<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h2, h3 { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .totais { margin-top: 20px; border-top: 2px solid #000; padding-top: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .empresa { font-size: 13px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $dadosEmpresa['nome'] }}</h2>
        <div class="empresa">
            {{ $dadosEmpresa['endereco'] }} | NIF: {{ $dadosEmpresa['nif'] }}<br>
            Tel: {{ $dadosEmpresa['telefone'] }} | {{ $dadosEmpresa['email'] }}
        </div>
        <h3>Relatório de Faturação por Colaborador</h3>
        <small>Período:
            {{ $dataInicial ?? 'Início' }} até {{ $dataFinal ?? 'Atual' }}
        </small>
    </div>

    @foreach ($documentosPorColaborador as $colaborador => $docs)
        <h4>Colaborador: {{ $colaborador }}</h4>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Nº Documento</th>
                    <th>Data</th>
                    <th>Total S/ Desconto</th>
                    <th>Total Geral</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($docs as $doc)
                    <tr>
                        <td>{{ $doc->tipo_sigla }}</td>
                        <td>{{ $doc->num_fatura }}</td>
                        <td>{{ \Carbon\Carbon::parse($doc->data_emissao)->format('d/m/Y') }}</td>
                        <td>{{ number_format($doc->total_sem_desconto, 2, ',', '.') }}</td>
                        <td>{{ number_format($doc->total_geral, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p><strong>Total do Colaborador:</strong>
            {{ number_format($docs->sum('total_geral'), 2, ',', '.') }} KZ
        </p>
        <hr>
    @endforeach

    <div class="totais">
        <strong>Total Global:</strong><br>
        Documentos: {{ $totalDocs }}<br>
        Valor sem desconto: {{ number_format($totalSemDesconto, 2, ',', '.') }} KZ<br>
        Total faturado: {{ number_format($totalFaturado, 2, ',', '.') }} KZ
    </div>
</body>
</html>
