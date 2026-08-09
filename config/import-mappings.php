<?php

/*
|--------------------------------------------------------------------------
| Mapeamentos de importação de produtos
|--------------------------------------------------------------------------
|
| "presets"  — mapeamentos por sistema de origem:
|   chave  => [ titulo, mapeamento: [coluna_origem => campo_interno] ]
|
| "campos"  — campos internos que podem ser mapeados e aliases aceites na
|   deteção automática por cabeçalho.
|
*/

return [

    'campos' => [
        'nome' => [
            'label' => 'Nome',
            'obrigatorio' => true,
            'aliases' => ['nome', 'designacao', 'designação', 'descricao', 'artigo'],
        ],
        'descricao' => [
            'label' => 'Descrição',
            'aliases' => ['descricao', 'descrição', 'detalhes', 'observacoes'],
        ],
        'codigo_produto' => [
            'label' => 'Código / Referência',
            'aliases' => ['codigo', 'codigo_produto', 'referencia', 'referência', 'ref', 'codigo_artigo'],
        ],
        'codigo_barra' => [
            'label' => 'Código de Barras',
            'aliases' => ['codigo_barra', 'codigo_de_barras', 'barcode', 'ean', 'gtin'],
        ],
        'preco_custo' => [
            'label' => 'Preço de Custo',
            'aliases' => ['preco_custo', 'custo', 'custo_unitario', 'cost', 'preco_compra'],
        ],
        'preco_venda' => [
            'label' => 'Preço de Venda',
            'aliases' => ['preco_venda', 'pvp', 'venda', 'preco', 'price', 'preco_sem_iva'],
        ],
        'preco_final' => [
            'label' => 'Preço Final (c/ IVA)',
            'aliases' => ['preco_final', 'preco_total', 'preco_com_iva', 'final'],
        ],
        'margem_lucro' => [
            'label' => 'Margem de Lucro (%)',
            'aliases' => ['margem_lucro', 'margem', 'markup', 'lucro'],
        ],
        'valor_iva' => [
            'label' => 'Valor IVA',
            'aliases' => ['valor_iva', 'iva_valor', 'imposto_valor'],
        ],
        'stock_minimo' => [
            'label' => 'Stock Mínimo',
            'aliases' => ['stock_minimo', 'stock_min', 'minimo', 'min', 'stock_minimo'],
        ],
        'stock_maximo' => [
            'label' => 'Stock Máximo',
            'aliases' => ['stock_maximo', 'stock_max', 'maximo', 'max'],
        ],
        'stock_ideial' => [
            'label' => 'Stock Ideal',
            'aliases' => ['stock_ideial', 'stock_ideal', 'ideal'],
        ],
        'unidade' => [
            'label' => 'Unidade',
            'aliases' => ['unidade', 'un', 'medida', 'unit'],
        ],
        'imposto' => [
            'label' => 'Tipo de IVA',
            'aliases' => ['imposto', 'iva', 'taxa_iva', 'vat', 'vat_rate'],
        ],
        'tipo' => [
            'label' => 'Tipo (Produto/Serviço)',
            'aliases' => ['tipo', 'tipo_produto', 'tipo_produto', 'natureza'],
        ],
        'marca' => [
            'label' => 'Marca',
            'aliases' => ['marca', 'brand'],
        ],
        'categoria' => [
            'label' => 'Categoria',
            'aliases' => ['categoria', 'grupo', 'categoria_produto'],
        ],
        'sub_categoria' => [
            'label' => 'Sub-Categoria',
            'aliases' => ['sub_categoria', 'subcategoria', 'subgrupo'],
        ],
        'fornecedor' => [
            'label' => 'Fornecedor',
            'aliases' => ['fornecedor', 'supplier', 'marca_fornecedor'],
        ],
        'armazem' => [
            'label' => 'Armazém',
            'aliases' => ['armazem', 'warehouse', 'loja', 'deposito'],
        ],
        'tipo_stock' => [
            'label' => 'Tipo de Stock',
            'aliases' => ['tipo_stock', 'tipo de stock', 'natureza_stock'],
        ],
        'controla_validade' => [
            'label' => 'Controla Validade (sim/não)',
            'aliases' => ['controla_validade', 'validade', 'controlo_validade'],
        ],
        'movimenta_stock' => [
            'label' => 'Movimenta Stock (sim/não)',
            'aliases' => ['movimenta_stock', 'movimenta', 'move_stock'],
        ],
        'estado' => [
            'label' => 'Estado (ativo/inativo)',
            'aliases' => ['estado', 'ativo', 'status', 'situacao'],
        ],
    ],

    'presets' => [
        'generico' => [
            'titulo' => 'Genérico (modelo SoftSeven)',
            'mapeamento' => [
                'nome' => 'nome',
                'descricao' => 'descricao',
                'codigo_produto' => 'codigo_produto',
                'codigo_barra' => 'codigo_barra',
                'preco_custo' => 'preco_custo',
                'preco_venda' => 'preco_venda',
                'preco_final' => 'preco_final',
                'margem_lucro' => 'margem_lucro',
                'valor_iva' => 'valor_iva',
                'stock_minimo' => 'stock_minimo',
                'stock_maximo' => 'stock_maximo',
                'stock_ideial' => 'stock_ideial',
                'unidade' => 'unidade',
                'imposto' => 'imposto',
                'tipo' => 'tipo',
                'marca' => 'marca',
                'categoria' => 'categoria',
                'sub_categoria' => 'sub_categoria',
                'fornecedor' => 'fornecedor',
                'armazem' => 'armazem',
                'tipo_stock' => 'tipo_stock',
                'controla_validade' => 'controla_validade',
                'movimenta_stock' => 'movimenta_stock',
                'estado' => 'estado',
            ],
        ],

        'sage' => [
            'titulo' => 'Sage',
            'mapeamento' => [
                'Referencia' => 'codigo_produto',
                'Codigo' => 'codigo_produto',
                'Designacao' => 'nome',
                'Descricao' => 'descricao',
                'PVP' => 'preco_venda',
                'Custo' => 'preco_custo',
                'CodigoBarras' => 'codigo_barra',
                'IVA' => 'imposto',
                'Marca' => 'marca',
                'Categoria' => 'categoria',
                'Fornecedor' => 'fornecedor',
                'StkMin' => 'stock_minimo',
                'StkMax' => 'stock_maximo',
                'Unidade' => 'unidade',
                'Grupo IVA' => 'imposto',
            ],
        ],

        'phc' => [
            'titulo' => 'PHC',
            'mapeamento' => [
                'Artigo' => 'nome',
                'Código' => 'codigo_produto',
                'Descrição' => 'descricao',
                'Preço Custo' => 'preco_custo',
                'Preço Venda' => 'preco_venda',
                'IVA' => 'imposto',
                'Categoria' => 'categoria',
                'Subcategoria' => 'sub_categoria',
                'Fornecedor' => 'fornecedor',
                'Exist. Mínima' => 'stock_minimo',
                'Exist. Máxima' => 'stock_maximo',
                'Unidade' => 'unidade',
                'Armazém' => 'armazem',
                'Marca' => 'marca',
            ],
        ],

        'odoo' => [
            'titulo' => 'Odoo',
            'mapeamento' => [
                'Name' => 'nome',
                'Internal Reference' => 'codigo_produto',
                'Barcode' => 'codigo_barra',
                'Default Cost' => 'preco_custo',
                'List Price' => 'preco_venda',
                'Cost' => 'preco_custo',
                'Product Category' => 'categoria',
                'Brand' => 'marca',
                'Manufacturer' => 'fornecedor',
                'Vendor Name' => 'fornecedor',
                'Unit of Measure' => 'unidade',
                'Minimum Quantity' => 'stock_minimo',
                'Maximum Quantity' => 'stock_maximo',
                'Route' => 'tipo_stock',
            ],
        ],

        'sap' => [
            'titulo' => 'SAP',
            'mapeamento' => [
                'Material' => 'nome',
                'Material Code' => 'codigo_produto',
                'Description' => 'descricao',
                'Standard Price' => 'preco_custo',
                'Selling Price' => 'preco_venda',
                'EAN/UPC' => 'codigo_barra',
                'Tax' => 'imposto',
                'Base Unit' => 'unidade',
                'Plant' => 'armazem',
                'Min Stock' => 'stock_minimo',
                'Max Stock' => 'stock_maximo',
                'Vendor' => 'fornecedor',
            ],
        ],
    ],
];