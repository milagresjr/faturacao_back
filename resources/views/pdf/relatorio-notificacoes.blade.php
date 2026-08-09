<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title>Relatório de Notificações</title>

<style>
body{
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
}

.header{
    text-align:center;
    margin-bottom:20px;
}

.header h2{
    margin:0;
}

.header span{
    font-size:11px;
    color:#555;
}

.empresa{
    margin-top:4px;
    font-size:12px;
    font-weight:bold;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1f2937;
    color:white;
    padding:8px;
    font-size:11px;
    text-align:left;
}

td{
    border:1px solid #ddd;
    padding:6px;
    font-size:11px;
}

tr:nth-child(even){
    background:#f3f4f6;
}

.footer{
    margin-top:20px;
    text-align:right;
    font-size:11px;
}
</style>
</head>
<body>

<div class="header">
    <h2>Relatório de Notificações</h2>
    <div class="empresa">{{ $empresa->nome ?? '' }}</div>
    <span>{{ $fonte === 'stock' ? 'Stock Baixo' : 'Validade de Produtos' }} - Gerado em: {{ $data }}</span>
</div>

<table>
<thead>
@if($fonte === 'stock')
<tr>
    <th>#</th>
    <th>Produto</th>
    <th>Código</th>
    <th>Armazém</th>
    <th>Stock Atual</th>
    <th>Stock Mínimo</th>
    <th>Data</th>
    <th>Estado</th>
</tr>
@else
<tr>
    <th>#</th>
    <th>Tipo</th>
    <th>Nível</th>
    <th>Produto</th>
    <th>Lote</th>
    <th>Dias Restantes</th>
    <th>Quantidade</th>
    <th>Data</th>
</tr>
@endif
</thead>
<tbody>

@foreach($items as $item)

@if($fonte === 'stock')
<tr>
    <td>{{ $item['id'] }}</td>
    <td>{{ $item['produto']['nome'] ?? '-' }}</td>
    <td>{{ $item['produto']['codigo'] ?? '-' }}</td>
    <td>{{ $item['stock']['armazem'] ?? '-' }}</td>
    <td>{{ $item['stock']['stock_atual'] }}</td>
    <td>{{ $item['stock']['stock_min'] ?? '-' }}</td>
    <td>{{ $item['data_envio'] }}</td>
    <td>{{ $item['lida'] ? 'Lida' : 'Não lida' }}</td>
</tr>
@else
<tr>
    <td>{{ $item['id'] }}</td>
    <td>{{ ucfirst($item['tipo'] ?? '-') }}</td>
    <td>{{ $item['nivel'] ?? '-' }}</td>
    <td>{{ $item['produto']['nome'] ?? '-' }}</td>
    <td>{{ $item['lote']['codigo_lote'] ?? '-' }}</td>
    <td>{{ $item['dias_restantes'] ?? '-' }}</td>
    <td>{{ $item['quantidade_afetada'] ?? '-' }}</td>
    <td>{{ $item['data_envio'] }}</td>
</tr>
@endif

@endforeach

</tbody>
</table>

<div class="footer">
    Total: {{ count($items) }}
</div>

</body>
</html>