<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\Produto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;

class SaftController extends Controller
{

    public function gerarSaft(Request $request)
    {
        $startDate = $request->input('data_inicio', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('data_fim', now()->endOfMonth()->format('Y-m-d'));
        $tipo = $request->input('tipo'); // faturacao ou compra
        $idEmpresa = $request->input('empresa_id');

        /*
            |--------------------------------------------------------------------------
            | 1️⃣ HEADER
            |--------------------------------------------------------------------------
        */
        $empresa = Empresa::find($idEmpresa);

        $header = [
            'AuditFileVersion' => '1.01_01',
            'CompanyID' => $empresa->registro_comercial ?? '500000000',
            'TaxRegistrationNumber' => $empresa->nif ?? '500000000',
            'TaxAccountingBasis' => 'F',
            'CompanyName' => $empresa->nome ?? '',
            'CompanyAddress' => [
                'AddressDetail' => $empresa->endereco ?? '',
                'City' => $empresa->cidade ?? '',
                'Country' => 'AO',
            ],
            'FiscalYear' => date('Y'),
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'CurrencyCode' => 'AOA',
            'DateCreated' => now()->format('Y-m-d'),
            'TaxEntity' => 'Global',
            'ProductCompanyTaxID' => '500000000',
            'SoftwareValidationNumber' => 'SVN-123456/AGT/2023',
            'ProductID' => 'Zimboweb/Softseven',
            'ProductVersion' => '1.0',
            'Telephone' => $empresa->telefone ?? '',
            'Email' => $empresa->email ?? '',
            'Website' => $empresa->website ?? ''
        ];

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ MASTER FILES (Clientes/Fornecedores e Produtos)
    |--------------------------------------------------------------------------
    */

        if ($tipo == 'compra') {
            // Buscar documentos de compra
            $documentos = DocumentoCompra::with(['itens'])
                ->where('empresa_id', $idEmpresa)
                ->whereBetween('created_at', [$startDate, $endDate])
                // ->whereNotIn('estado_documento', ['anulado', 'rascunho'])
                ->get();

            // Buscar fornecedores únicos que aparecem nos documentos
            $fornecedoresIds = $documentos->pluck('fornecedor_id')->unique();
            $fornecedores = Fornecedor::whereIn('id', $fornecedoresIds)->get();

            // Master Files para compra
            $customers = []; // Na verdade serão fornecedores
            foreach ($fornecedores as $fornecedor) {
                $customers[] = [
                    'CustomerID' => $fornecedor->id,
                    'AccountID' => 'Desconhecido',
                    'CustomerTaxID' => $fornecedor->nif ?? '999999999',
                    'CompanyName' => $fornecedor->nome,
                    'BillingAddress' => [
                        'AddressDetail' => $fornecedor->endereco ?? 'Desconhecido',
                        'City' => $fornecedor->cidade ?? 'Desconhecido',
                        'Country' => $fornecedor->pais ?? 'AO',
                    ],
                ];
            }

            // Buscar produtos únicos dos documentos de compra
            $produtosIds = $documentos->flatMap(function ($doc) {
                return $doc->itens->pluck('produto_id');
            })->unique();

            $produtos = Produto::with('tipo')->whereIn('id', $produtosIds)->get();
        } else {
            // Buscar documentos de faturação/venda
            $documentos = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento'])
                ->where('empresa_id', $idEmpresa)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('estado_documento', ['anulado', 'rascunho'])
                ->whereIn('tipo_sigla', ['FR', 'FT', 'FA', 'ND'])
                ->get();

            // Buscar clientes únicos que aparecem nos documentos
            $clientesIds = $documentos->pluck('cliente_id')->unique();
            $clientes = Cliente::whereIn('id', $clientesIds)->get();

            // Master Files para faturação
            $customers = [];
            foreach ($clientes as $cliente) {
                $customers[] = [
                    'CustomerID' => $cliente->id,
                    'AccountID' => 'Desconhecido',
                    'CustomerTaxID' => $cliente->nif ?? '999999999',
                    'CompanyName' => $cliente->nome,
                    'BillingAddress' => [
                        'AddressDetail' => $cliente->endereco ?? 'Desconhecido',
                        'City' => $cliente->cidade ?? 'Desconhecido',
                        'Country' => $cliente->pais ?? 'AO',
                    ],
                ];
            }

            // Buscar produtos únicos dos documentos de venda
            $produtosIds = $documentos->flatMap(function ($doc) {
                return $doc->itens->pluck('produto_id');
            })->unique();

            $produtos = Produto::with('tipo')->whereIn('id', $produtosIds)->get();
        }

        $products = [];
        foreach ($produtos as $produto) {
            $products[] = [
                'ProductType' => ($produto->tipo->nome ?? 'Produto') == 'Produto' ? 'P' : 'S',
                'ProductCode' => $produto->id,
                'ProductDescription' => $produto->nome,
                'ProductNumberCode' => $produto->codigo ?? $produto->id,
            ];
        }

        $taxTable = [
            [
                'TaxType' => 'IVA',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NOR',
                'Description' => 'IVA à taxa normal',
                'TaxAmount' => 14,
            ],
            [
                'TaxType' => 'NS',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NS',
                'Description' => 'Não sujeito a IVA',
                'TaxAmount' => 0,
            ]
        ];

        $masterFiles = [
            'Customer' => $customers,
            'Product' => $products,
            'TaxTableEntry' => $taxTable
        ];

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ SOURCE DOCUMENTS (Documentos)
    |--------------------------------------------------------------------------
    */

        $invoices = [];
        $totalDebit = 0;

        if ($tipo == 'compra') {
            // SOURCE DOCUMENTS para compras (PurchaseInvoices)
            foreach ($documentos as $documento) {
                $linhas = [];

                foreach ($documento->itens as $index => $linha) {
                    $linhas[] = [
                        'LineNumber' => $index + 1,
                        'ProductCode' => $linha->produto_codigo,
                        'ProductDescription' => $linha->produto_nome,
                        'Quantity' => $linha->quantidade,
                        'UnitOfMeasure' => 'UN',
                        'UnitPrice' => $linha->preco_unitario,
                        'TaxPointDate' => $documento->data_emissao,
                        'Description' => $linha->descricao,
                        'DebitAmount' => $linha->total,
                        'CreditAmount' => 0,
                        'Tax' => [
                            'TaxType' => $linha->iva > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva > 0 ? 14 : 0,
                            'TaxExemptionReason' => $linha->iva > 0 ? null : 'Isento',
                            'TaxExemptionCode' => $linha->iva > 0 ? null : 'ISENTO'
                        ],
                    ];
                }

                $invoices[] = [
                    'InvoiceNo' => $documento->num_documento ?? $documento->num_fatura,
                    'DocumentStatus' => [
                        'InvoiceStatus' => 'N',
                        'InvoiceStatusDate' => Carbon::parse($documento->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $documento->utilizador_id,
                        'SourceBilling' => $documento->id,
                        'Hash' => $documento->hash ?? '',
                        'HashControl' => '0',
                    ],
                    'InvoiceDate' => $documento->data_emissao,
                    'InvoiceType' => $documento->tipo_sigla ?? 'FT',
                    'SpecialRegimes' => [
                        'SelfBillingIndicator' => '0',
                        'CashVATSchemeIndicator' => '0',
                        'ThirdPartiesBillingIndicator' => '0'
                    ],
                    'SourceID' => $documento->utilizador_id,
                    'SystemEntryDate' => Carbon::parse($documento->data_emissao)->format('Y-m-d\TH:i:s'),
                    'SupplierID' => $documento->fornecedor_id,
                    'Line' => $linhas,
                    'DocumentTotals' => [
                        'TaxPayable' => $documento->total_iva ?? 0,
                        'NetTotal' => $documento->total_sem_iva ?? 0,
                        'GrossTotal' => $documento->total_com_iva ?? 0,
                    ],
                ];

                $totalDebit += $documento->total_sem_iva ?? 0;
            }

            $sourceDocuments = [
                'PurchaseInvoices' => [
                    'NumberOfEntries' => count($invoices),
                    'TotalDebit' => $totalDebit,
                    'TotalCredit' => 0,
                    'Invoice' => $invoices,
                ]
            ];

            $workingDocuments = [];
            $payments = [];
        } else {
            // SOURCE DOCUMENTS para vendas/faturação (SalesInvoices)
          
            foreach ($documentos as $fatura) {
                $linhas = [];

                foreach ($fatura->itens as $index => $linha) {
                    $linhas[] = [
                        'LineNumber' => $index + 1,
                        'ProductCode' => $linha->produto_codigo,
                        'ProductDescription' => $linha->produto_nome,
                        'Quantity' => $linha->quantidade,
                        'UnitOfMeasure' => 'UN',
                        'UnitPrice' => $linha->preco_unitario,
                        'TaxPointDate' => $fatura->data_emissao,
                        'Description' => $linha->descricao,
                        'DebitAmount' => $linha->total,
                        'CreditAmount' => 0,
                        'Tax' => [
                            'TaxType' => $linha->iva > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva > 0 ? 14 : 0,
                            'TaxExemptionReason' => $linha->iva > 0 ? null : 'Isento',
                            'TaxExemptionCode' => $linha->iva > 0 ? null : 'ISENTO'
                        ],
                    ];
                }

                $invoices[] = [
                    'InvoiceNo' => $fatura->num_fatura,
                    'DocumentStatus' => [
                        'InvoiceStatus' => 'N',
                        'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $fatura->utilizador_id,
                        'SourceBilling' => $fatura->id,
                        'Hash' => $fatura->hash,
                        'HashControl' => '0',
                    ],
                    'InvoiceDate' => $fatura->data_emissao,
                    'InvoiceType' => $fatura->sigla_fatura,
                    'SpecialRegimes' => [
                        'SelfBillingIndicator' => '0',
                        'CashVATSchemeIndicator' => '0',
                        'ThirdPartiesBillingIndicator' => '0'
                    ],
                    'SourceID' => $fatura->utilizador_id,
                    'SystemEntryDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'CustomerID' => $fatura->cliente_id,
                    'Line' => $linhas,
                    'DocumentTotals' => [
                        'TaxPayable' => $fatura->total_iva,
                        'NetTotal' => $fatura->total_sem_iva,
                        'GrossTotal' => $fatura->total_com_iva,
                    ],
                ];

                $totalDebit += $fatura->total_sem_iva;
            }

            $sourceDocuments = [
                'SalesInvoices' => [
                    'NumberOfEntries' => count($invoices),
                    'TotalDebit' => $totalDebit,
                    'TotalCredit' => 0,
                    'Invoice' => $invoices,
                ]
            ];

            // Working Documents (apenas para faturação)
            $workDocument = [];
            foreach ($documentos as $fatura) {
                $linhas = [];

                foreach ($fatura->itens as $index => $linha) {
                    $linhas[] = [
                        'LineNumber' => $index + 1,
                        'ProductCode' => $linha->produto_codigo,
                        'ProductDescription' => $linha->produto_nome,
                        'Quantity' => $linha->quantidade,
                        'UnitOfMeasure' => 'UN',
                        'UnitPrice' => $linha->preco_unitario,
                        'TaxPointDate' => $fatura->data_emissao,
                        'Description' => $linha->descricao,
                        'DebitAmount' => $linha->total,
                        'CreditAmount' => $linha->total,
                        'Tax' => [
                            'TaxType' => $linha->iva > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva > 0 ? 14 : 0,
                            'TaxExemptionReason' => $linha->iva > 0 ? null : 'Isento',
                            'TaxExemptionCode' => $linha->iva > 0 ? null : 'ISENTO'
                        ],
                    ];
                }

                $workDocument[] = [
                    'DocumentNumber' => $fatura->num_fatura,
                    'DocumentStatus' => [
                        'WorkStatus' => 'N',
                        'WorkStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $fatura->utilizador_id,
                        'SourceBilling' => $fatura->id,
                    ],
                    'Hash' => $fatura->hash,
                    'HashControl' => '0',
                    'WorkType' => $fatura->tipo_sigla,
                    'SourceID' => $fatura->utilizador_id,
                    'SystemEntryDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'CustomerID' => $fatura->cliente_id,
                    'Line' => $linhas,
                    'DocumentTotals' => [
                        'TaxPayable' => $fatura->total_iva,
                        'NetTotal' => $fatura->total_sem_iva,
                        'GrossTotal' => $fatura->total_com_iva,
                    ],
                ];
            }

            $workingDocuments = [
                'NumberOfEntries' => count($workDocument),
                'TotalDebit' => $totalDebit,
                'TotalCredit' => 0,
                'WorkDocument' => $workDocument
            ];

            // Payments
            $payments = [];
            foreach ($documentos as $fatura) {
                if ($fatura->meiosPagamento && $fatura->meiosPagamento->count()) {
                    foreach ($fatura->meiosPagamento as $pagamento) {
                        $payments[] = [
                            'NumberOfEntries' => $pagamento->id,
                            'TotalDebit' => $pagamento->valor,
                            'TotalCredit' => 0,
                        ];
                    }
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ JUNTAR TUDO
    |--------------------------------------------------------------------------
    */

        $auditFile = [
            '_attributes' => [
                'xmlns' => 'urn:OECD:Tax:AuditFile',
            ],
            'Header' => $header,
            'MasterFiles' => $masterFiles,
            'SourceDocuments' => $sourceDocuments,
        ];

        // Adicionar WorkingDocuments apenas para faturação
        if ($tipo != 'compra' && !empty($workingDocuments)) {
            $auditFile['WorkingDocuments'] = $workingDocuments;
        }

        // Adicionar Payments apenas para faturação
        if ($tipo != 'compra' && !empty($payments)) {
            $auditFile['Payments'] = ['Payment' => $payments];
        }

        $xml = ArrayToXml::convert(
            $auditFile,
            'AuditFile',
            true,
            'UTF-8'
        );

        // Nome do arquivo baseado no tipo
        $fileName = $tipo == 'compra' ? 'SAFT_COMPRAS.xml' : 'SAFT_VENDAS.xml';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        echo $xml;
        exit;
    }

    public function listFaturas(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $dataInicial = $request->input('data_inicial');
        $dataFinal = $request->input('data_final');

        $tipo = $request->input('tipo'); // Compra ou Faturação

        $query = null;

        if ($tipo == 'compra') {
            $query = DocumentoCompra::query();
        } else {
            $query = Documento::query();

            // Aplica os filtros apenas para documentos que NÃO são de compra
            $query->whereNotIn('estado_documento', ['anulado', 'rascunho'])
                ->whereIn('tipo_sigla', ['FR', 'FT', 'FA', 'ND']);
        }

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('created_at', [$dataInicial, $dataFinal]);
        }

        $documentos = $query->paginate($perPage);

        return response()->json($documentos);
    }

    public function gerarSaftOld(Request $request)
    {
        $startDate = $request->input('data_inicio', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('data_fim', now()->endOfMonth()->format('Y-m-d'));

        $tipo = $request->input('tipo'); //faturacao ou compra

        $idEmpresa = $request->input('empresa_id');

        /*
            |--------------------------------------------------------------------------
            | 1️⃣ HEADER
            |--------------------------------------------------------------------------
        */

        $header = [
            'AuditFileVersion' => '1.01_01',
            'CompanyID' => '500000000', //Registo comercial da empresa
            'TaxRegistrationNumber' => '500000000', //NIF da empresa
            'TaxAccountingBasis' => 'F',
            'CompanyName' => 'Zimboweb', //Nome da empresa
            'CompanyAddress' => [
                'AddressDetail' => 'Rua do Sol, 123', //Endereco da empresa
                'City' => 'Luanda',
                'Country' => 'AO',
            ],
            'FiscalYear' => date('Y'),
            'StartDate' => now()->startOfMonth()->format('Y-m-d'),
            'EndDate' => now()->endOfMonth()->format('Y-m-d'),
            'CurrencyCode' => 'AOA',
            'DateCreated' => now()->format('Y-m-d'),
            'TaxEntity' => 'Global',
            'ProductCompanyTaxID' => '500000000', //NIF da Softseven
            'SoftwareValidationNumber' => 'SVN-123456/AGT/2023', //Número de validação do software
            'ProductID' => 'Zimboweb/Softseven',
            'ProductVersion' => '1.0',
            'Telephone' => '',
            'Email' => '',
            'Website' => ''
        ];


        /*
        |--------------------------------------------------------------------------
        | 2️⃣ MASTER FILES (Clientes)
        |--------------------------------------------------------------------------
        */

        $query = Cliente::query();

        if ($startDate && $endDate) {
            $query->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        } elseif ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $clientes = $query->get();

        $customers = [];

        foreach ($clientes as $cliente) {
            $customers[] = [
                'CustomerID' => $cliente->id,
                'AccountID' => 'Desconhecido',
                'CustomerTaxID' => $cliente->nif ?? '999999999',
                'CompanyName' => $cliente->nome,
                'BillingAddress' => [
                    'AddressDetail' => $cliente->endereco ?? 'Desconhecido',
                    'City' => 'Desconhecido',
                    'Country' => $cliente->pais ?? 'Desconhecido',
                ],
            ];
        }

        $queryProduct = Produto::with('tipo');

        if ($startDate && $endDate) {
            $queryProduct->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        } elseif ($startDate) {
            $queryProduct->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $queryProduct->whereDate('created_at', '<=', $endDate);
        }

        $produtos = $queryProduct->get();

        $products = [];

        foreach ($produtos as $produto) {
            $products[] = [
                'ProductType' => $produto->tipo->nome == 'Produto' ? 'P' : 'S' ?? 'Desconhecido',
                'ProductCode' => $produto->id,
                'ProductDescription' => $produto->nome,
                'ProductNumberCode' => $produto->id ?? 'Desconhecido',
            ];
        }

        $taxTable = [
            [
                'TaxType' => 'IVA',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NOR',
                'Description' => 'Iva à taxa normal',
                'TaxAmount' => 14,
            ],
            [
                'TaxType' => 'NS',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NS',
                'Description' => '',
                'TaxAmount' => 0,
            ]
        ];

        $masterFiles = [
            'Customer' => $customers,
            'Product' => $products,
            'TaxTableEntry' => $taxTable
        ];


        /*
    |--------------------------------------------------------------------------
    | 3️⃣ SOURCE DOCUMENTS (Faturas)
    |--------------------------------------------------------------------------
    */

        $queryFat = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento']);

        if ($startDate && $endDate) {
            $queryFat->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        } elseif ($startDate) {
            $queryFat->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $queryFat->whereDate('created_at', '<=', $endDate);
        }

        $faturas = $queryFat->get();

        $invoices = [];
        $totalDebit = 0;

        foreach ($faturas as $fatura) {

            $linhas = [];

            foreach ($fatura->itens as $index => $linha) {
                $linhas[] = [
                    'LineNumber' => $index + 1,
                    'ProductCode' => $linha->produto_codigo,
                    'ProductDescription' => $linha->produto_nome,
                    'Quantity' => $linha->quantidade,
                    'UnitOfMeasure' => 'UN',
                    'UnitPrice' => $linha->preco_unitario,
                    'TaxPointDate' => $fatura->data_emissao, //Duvida
                    'Description' => $linha->descricao,
                    'DebitAmount' => $linha->total,
                    'CreditAmount' => $linha->total,
                    'Tax' => [
                        'TaxType' => $linha->iva > 0 ? 'IVA' : 'NS',
                        'TaxCountryRegion' => 'AO',
                        'TaxCode' => $linha->iva > 0 ? 'NOR' : 'NS',
                        'TaxPercentage' => $linha->iva > 0 ? 14 : 0,
                        'TaxExemptionReason' => $linha->iva > 0 ? null : 'Isento', //Motivo isencao
                        'TaxExemptionCode' => $linha->iva > 0 ? null : 'ISENTO' //Codigo do imposto
                    ],
                ];
            }

            $invoices[] = [
                'InvoiceNo' => $fatura->num_fatura,
                'DocumentStatus' => [
                    'InvoiceStatus' => 'N',
                    'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'SourceID' => $fatura->utilizador_id,
                    'SourceBilling' => $fatura->id,
                    'Hash' => $fatura->hash,
                    'HashControl' => '0',
                    'InvoiceDate' => $fatura->data_emissao,
                    'InvoiceType' => $fatura->sigla_fatura,
                    'SpecialRegimes' => [
                        'SelfBillingIndicator' => '0',
                        'CashVATSchemeIndicator' => '0',
                        'ThirdPartiesBillingIndicator' => '0'
                    ],
                    'SourceID' => $fatura->utilizador_id,
                    'SystemEntryDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'CustomerID' => $fatura->cliente_id,
                ],
                'Line' => $linhas,
                'DocumentTotals' => [
                    'TaxPayable' => $fatura->total_iva,
                    'NetTotal' => $fatura->total_sem_iva,
                    'GrossTotal' => $fatura->total_com_iva,
                ],
            ];

            $totalDebit += $fatura->total_sem_iva;
        }

        $sourceDocuments = [
            'SalesInvoices' => [
                'NumberOfEntries' => count($invoices),
                'TotalDebit' => $totalDebit,
                'TotalCredit' => 0,
                'Invoice' => $invoices,
            ]
        ];

        $workDocument = [];

        foreach ($faturas as $fatura) {

            $linhas = [];

            foreach ($fatura->itens as $index => $linha) {
                $linhas[] = [
                    'LineNumber' => $index + 1,
                    'ProductCode' => $linha->produto_codigo,
                    'ProductDescription' => $linha->produto_nome,
                    'Quantity' => $linha->quantidade,
                    'UnitOfMeasure' => 'UN',
                    'UnitPrice' => $linha->preco_unitario,
                    'TaxPointDate' => $fatura->data_emissao, //Duvida
                    'Description' => $linha->descricao,
                    'DebitAmount' => $linha->total,
                    'CreditAmount' => $linha->total,
                    'Tax' => [
                        'TaxType' => $linha->iva > 0 ? 'IVA' : 'NS',
                        'TaxCountryRegion' => 'AO',
                        'TaxCode' => $linha->iva > 0 ? 'NOR' : 'NS',
                        'TaxPercentage' => $linha->iva > 0 ? 14 : 0,
                        'TaxExemptionReason' => $linha->iva > 0 ? null : 'Isento', //Motivo isencao
                        'TaxExemptionCode' => $linha->iva > 0 ? null : 'ISENTO' //Codigo do imposto
                    ],
                ];
            }

            $workDocument[] = [
                'DocumentNumber' => $fatura->num_fatura,
                'DocumentStatus' => [
                    'WorkStatus' => 'N',
                    'WorkStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'SourceID' => $fatura->utilizador_id,
                    'SourceBilling' => $fatura->id,
                ],
                'Hash' => $fatura->hash,
                'HashControl' => '0',
                'WorkType' => $fatura->tipo_sigla,
                'SourceID' => $fatura->utilizador_id,
                'SystemEntryDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                'CustomerID' => $fatura->cliente_id,
                'Line' => $linhas,
                'DocumentTotals' => [
                    'TaxPayable' => $fatura->total_iva,
                    'NetTotal' => $fatura->total_sem_iva,
                    'GrossTotal' => $fatura->total_com_iva,
                ],
            ];
        }

        $workingDocuments = [
            'NumberOfEntries' => '2', //Total de outros tipo de documento
            'TotalDebit' => $totalDebit,
            'TotalCredit' => 0,
            'WorkDocument' => $workDocument
        ];


        $payments = [];

        // return $faturas;

        foreach ($faturas as $fatura) {
            foreach ($fatura->meiosPagamento as $pagamento) {
                $payments[] = [
                    'NumberOfEntries' => $pagamento->id, //Numero total de recibos emitidos
                    'TotalDebit' => $pagamento->valor,
                    'TotalCredit' => 0,
                ];
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ JUNTAR TUDO
    |--------------------------------------------------------------------------
    */

        $auditFile = [
            '_attributes' => [
                'xmlns' => 'urn:OECD:Tax:AuditFile',
            ],
            'Header' => $header,
            'MasterFiles' => $masterFiles,
            'SourceDocuments' => $sourceDocuments,
            'WorkingDocuments' => $workingDocuments,
            'Payments' => [
                'Payment' => $payments
            ]
        ];


        $xml = ArrayToXml::convert(
            $auditFile,
            'AuditFile',
            true,
            'UTF-8'
        );

        // send XML directly to the browser and stop further processing
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: inline; filename="SAFT.xml"');
        echo $xml;
        exit;

        // return response($xml, 200)
        //     ->header('Content-Type', 'application/xml')
        //     ->header('Content-Disposition', 'attachment; filename="SAFT.xml"');
    }
}
