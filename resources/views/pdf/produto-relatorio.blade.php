<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Relatório de Produtos</title>

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

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1f2937;
    color:white;
    padding:8px;
    font-size:11px;
}

td{
    border:1px solid #ddd;
    padding:6px;
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
    @if(!empty($dadosPersonalizacaoFatura) && $dadosPersonalizacaoFatura->mostrar_logo && $dadosPersonalizacaoFatura->logo && $src)
        <div style="text-align: center; margin-bottom: 10px;">
            <img src="{{ $src }}" alt="Logo" style="width: 120px; height: 80px; object-fit: contain;">
        </div>
    @endif
    <h2>Relatório de Produtos</h2>
    <span>Gerado em: {{ $data }}</span>
</div>

<table>

<thead>
<tr>
<th>ID</th>
<th>Nome</th>
<th>Código</th>
<th>Categoria</th>
<th>Marca</th>
<th>Fornecedor</th>
<th>Preço Custo</th>
<th>Preço Venda</th>
<th>Preço Final</th>
<th>Margem</th>
<th>IVA</th>
<th>Stock</th>
<th>Criado em</th>
</tr>
</thead>

<tbody>

@foreach($produtos as $produto)

<tr>
<td>{{ $produto['id'] }}</td>
<td>{{ $produto['nome'] }}</td>
<td>{{ $produto['codigo'] }}</td>
<td>{{ $produto['categoria'] }}</td>
<td>{{ $produto['marca'] }}</td>
<td>{{ $produto['fornecedor'] }}</td>
<td>{{ number_format($produto['preco_custo'],2,',','.') }}</td>
<td>{{ number_format($produto['preco_venda'],2,',','.') }}</td>
<td>{{ number_format($produto['preco_final'],2,',','.') }}</td>
<td>{{ $produto['margem_lucro'] }}%</td>
<td>{{ number_format($produto['valor_iva'],2,',','.') }}</td>
<td>{{ $produto['stock_total'] }}</td>
<td>{{ $produto['criado_em'] }}</td>
</tr>

@endforeach

</tbody>

</table>

<div class="footer">
Total de Produtos: {{ count($produtos) }}
</div>

</body>
</html>