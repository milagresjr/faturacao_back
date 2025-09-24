<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exemplo PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; }
    </style>
</head>
<body>
    <h1>Teste de Várias Páginas</h1>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descrição</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @for($i=1; $i<=150; $i++)
                <tr>
                    <td>{{ $i }}</td>
                    <td>Produto {{ $i }}</td>
                    <td>{{ rand(10,500) }},00</td>
                </tr>
            @endfor
        </tbody>
    </table>
</body>
</html>
