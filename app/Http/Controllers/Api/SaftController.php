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
            'CompanyID' => $empresa->nif ?? '500000000',
            'TaxRegistrationNumber' => $empresa->nif ?? '500000000',
            'TaxAccountingBasis' => 'F',
            'CompanyName' => $empresa->nome ?? '',
            'CompanyAddress' => [
                'AddressDetail' => $empresa->endereco ?? 'Sem endereco',
                'City' => $empresa->cidade ?? 'Sem cidade',
                'Country' => 'AO',
            ],
            'FiscalYear' => date('Y'),
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'CurrencyCode' => 'AOA',
            'DateCreated' => now()->format('Y-m-d'),
            'TaxEntity' => 'Global',
            'ProductCompanyTaxID' => '500000000',
            'SoftwareValidationNumber' => '123456/AGT/2023',
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
                    'SelfBillingIndicator' => '0',
                ];
            }

            // Buscar produtos únicos dos documentos de compra
            $produtoCodigos = $documentos->flatMap(function ($doc) {
                return $doc->itens->pluck('produto_codigo');
            })->unique();

            $produtos = Produto::with('tipo')->whereIn('id', $documentos->flatMap(fn($d) => $d->itens->pluck('produto_id'))->unique())->get();
        } else {
            // Buscar documentos de faturação/venda
            $documentos = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento'])
                ->where('empresa_id', $idEmpresa)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('estado_documento', ['anulado', 'rascunho'])
                ->whereIn('tipo_sigla', ['FR', 'FT', 'FA', 'ND'])
                ->where(function ($q) {
                    $q->where('cliente_id', '!=', '0')->whereNotNull('cliente_id');
                })
                ->get();

            // Buscar clientes únicos que aparecem nos documentos
            $clientesIds = $documentos->pluck('cliente_id')->unique();

            // Buscar documentos de guias/transporte (GR/GT)
            $workDocuments = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento'])
                ->where('empresa_id', $idEmpresa)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('estado_documento', ['anulado', 'rascunho'])
                ->whereIn('tipo_sigla', ['GR', 'GT'])
                ->where(function ($q) {
                    $q->where('cliente_id', '!=', '0')->whereNotNull('cliente_id');
                })
                ->get();

            // Incluir clientes das guias (GR/GT) que podem não estar nas faturas
            $workClientesIds = $workDocuments->pluck('cliente_id')->unique();
            $clientesIds = $clientesIds->merge($workClientesIds)->unique();

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
                    'SelfBillingIndicator' => '0',
                ];
            }

            // Buscar produtos únicos dos documentos de venda (incluindo guias)
            $produtoCodigos = $documentos->flatMap(function ($doc) {
                return $doc->itens->pluck('produto_codigo');
            })->merge(
                $workDocuments->flatMap(function ($doc) {
                    return $doc->itens->pluck('produto_codigo');
                })
            )->unique();

            $produtos = Produto::with('tipo')->whereIn('id', $documentos->flatMap(fn($d) => $d->itens->pluck('produto_id'))->merge(
                $workDocuments->flatMap(fn($d) => $d->itens->pluck('produto_id'))
            )->unique())->get();
        }

        $products = [];
        $existingCodes = [];
        foreach ($produtos as $produto) {
            $code = $produto->codigo_produto ?? (string) $produto->id;
            $existingCodes[] = $code;
            $products[] = [
                'ProductType' => ($produto->tipo->nome ?? 'Produto') == 'Produto' ? 'P' : 'S',
                'ProductCode' => $code,
                'ProductDescription' => $produto->nome,
                'ProductNumberCode' => $produto->codigo_produto ?? $produto->id,
            ];
        }

        foreach ($produtoCodigos as $code) {
            if (!in_array($code, $existingCodes)) {
                $products[] = [
                    'ProductType' => 'P',
                    'ProductCode' => $code,
                    'ProductDescription' => 'Produto (removido)',
                    'ProductNumberCode' => $code,
                ];
            }
        }

        $taxTable = [
            [
                'TaxType' => 'NS',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NS',
                'Description' => 'Não sujeito a IVA',
                'TaxPercentage' => 0,
            ]
        ];

        $masterFiles = [
            'Customer' => $customers,
            'Product' => $products,
            'TaxTable' => [
                'TaxTableEntry' => $taxTable
            ]
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
                        'TaxPointDate' => Carbon::parse($documento->data_emissao)->format('Y-m-d'),
                        'Description' => $linha->descricao ?: $linha->produto_nome,
                        'DebitAmount' => $linha->total,
                        'CreditAmount' => 0,
                        'Tax' => [
                            'TaxType' => $linha->iva_percent > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva_percent > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva_percent > 0 ? 14 : 0,
                        ],
                        'TaxExemptionReason' => $linha->iva_percent > 0 ? null : 'Transmissão de bens e serviços não sujeita',
                        'TaxExemptionCode' => $linha->iva_percent > 0 ? null : 'M02',
                    ];
                }

                $invoices[] = [
                    'InvoiceNo' => $this->normalizarNumeroFatura($documento->num_documento ?? $documento->num_fatura),
                    'DocumentStatus' => [
                        'InvoiceStatus' => 'N',
                        'InvoiceStatusDate' => Carbon::parse($documento->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $documento->utilizador_id,
                        'SourceBilling' => 'P',
                    ],
                    'Hash' => $documento->hash ?? '',
                    'HashControl' => '0',
                    'InvoiceDate' => Carbon::parse($documento->data_emissao)->format('Y-m-d'),
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
                        'TaxPayable' => $documento->total_impostos ?? 0,
                        'NetTotal' => ($documento->total_geral - $documento->total_impostos) ?? 0,
                        'GrossTotal' => $documento->total_geral ?? 0,
                    ],
                ];

                $totalDebit += ($documento->total_geral - $documento->total_impostos) ?? 0;
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
                        'TaxPointDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                        'Description' => $linha->descricao ?: $linha->produto_nome,
                        'DebitAmount' => $linha->total,
                        'Tax' => [
                            'TaxType' => $linha->iva_percent > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva_percent > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva_percent > 0 ? 14 : 0,
                        ],
                        'TaxExemptionReason' => $linha->iva_percent > 0 ? null : 'Transmissão de bens e serviços não sujeita',
                        'TaxExemptionCode' => $linha->iva_percent > 0 ? null : 'M02',
                    ];
                }

                $invoices[] = [
                    'InvoiceNo' => $this->normalizarNumeroFatura($fatura->num_fatura),
                    'DocumentStatus' => [
                        'InvoiceStatus' => 'N',
                        'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $fatura->utilizador_id,
                        'SourceBilling' => 'P',
                    ],
                    'Hash' => $fatura->hash,
                    'HashControl' => '0',
                    'InvoiceDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                    'InvoiceType' => $fatura->tipo_sigla,
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
                        'TaxPayable' => $fatura->total_impostos,
                        'NetTotal' => $fatura->total_geral - $fatura->total_impostos,
                        'GrossTotal' => $fatura->total_geral,
                    ],
                ];

                $totalDebit += $fatura->total_geral - $fatura->total_impostos;
            }

            $sourceDocuments = [
                'SalesInvoices' => [
                    'NumberOfEntries' => count($invoices),
                    'TotalDebit' => $totalDebit,
                    'TotalCredit' => 0,
                    'Invoice' => $invoices,
                ],
            ];

            // Working Documents (apenas guias/transportes)
            $workDocument = [];
            $totalWorkDebit = 0;
            foreach ($workDocuments as $fatura) {
                $linhas = [];

                foreach ($fatura->itens as $index => $linha) {
                    $linhas[] = [
                        'LineNumber' => $index + 1,
                        'ProductCode' => $linha->produto_codigo,
                        'ProductDescription' => $linha->produto_nome,
                        'Quantity' => $linha->quantidade,
                        'UnitOfMeasure' => 'UN',
                        'UnitPrice' => $linha->preco_unitario,
                        'TaxPointDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                        'Description' => $linha->descricao ?: $linha->produto_nome,
                        'DebitAmount' => $linha->quantidade * $linha->preco_unitario,
                        'Tax' => [
                            'TaxType' => $linha->iva_percent > 0 ? 'IVA' : 'NS',
                            'TaxCountryRegion' => 'AO',
                            'TaxCode' => $linha->iva_percent > 0 ? 'NOR' : 'NS',
                            'TaxPercentage' => $linha->iva_percent > 0 ? 14 : 0,
                        ],
                        'TaxExemptionReason' => $linha->iva_percent > 0 ? null : 'Transmissão de bens e serviços não sujeita',
                        'TaxExemptionCode' => $linha->iva_percent > 0 ? null : 'M02',
                    ];
                }

                $totalWorkDebit += $fatura->total_geral - $fatura->total_impostos;

                $workDocument[] = [
                    'DocumentNumber' => $this->normalizarNumeroFatura($fatura->num_fatura),
                    'DocumentStatus' => [
                        'WorkStatus' => 'N',
                        'WorkStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                        'SourceID' => $fatura->utilizador_id,
                        'SourceBilling' => 'P',
                    ],
                    'Hash' => $fatura->hash,
                    'HashControl' => '0',
                    'WorkDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                    'WorkType' => $fatura->tipo_sigla === 'GT' ? 'GR' : $fatura->tipo_sigla,
                    'SourceID' => $fatura->utilizador_id,
                    'SystemEntryDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'CustomerID' => $fatura->cliente_id,
                    'Line' => $linhas,
                    'DocumentTotals' => [
                        'TaxPayable' => $fatura->total_impostos,
                        'NetTotal' => $fatura->total_geral - $fatura->total_impostos,
                        'GrossTotal' => $fatura->total_geral,
                    ],
                ];
            }

            if (!empty($workDocument)) {
                $sourceDocuments['WorkingDocuments'] = [
                    'NumberOfEntries' => count($workDocument),
                    'TotalDebit' => $totalWorkDebit,
                    'TotalCredit' => 0,
                    'WorkDocument' => $workDocument,
                ];
            }

            // Payments
            $paymentLines = [];
            $paymentCounters = [];
            foreach ($documentos as $fatura) {
                if ($fatura->meiosPagamento && $fatura->meiosPagamento->count()) {
                    $paymentCounters[$fatura->id] = 0;
                    foreach ($fatura->meiosPagamento as $pagamento) {
                        $paymentCounters[$fatura->id]++;
                        $pRef = $this->normalizarNumeroFatura($fatura->num_fatura);
                        if ($paymentCounters[$fatura->id] > 1) {
                            $pParts = explode(' ', $pRef, 2);
                            $nParts = explode('/', $pParts[1] ?? '0');
                            $pRef = $pParts[0] . ' ' . ($nParts[0] ?? '0') . '/' . $paymentCounters[$fatura->id];
                        }
                        $paymentLines[] = [
                            'PaymentRefNo' => $pRef,
                            'TransactionDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                            'PaymentType' => match ($fatura->forma_pagamento) {
                                'Cash', 'Numerário', 'Dinheiro' => 'RC',
                                'Credit', 'Crédito', 'Transferência' => 'RC',
                                'Debit', 'Débito' => 'RC',
                                'Refund', 'Reembolso' => 'RG',
                                'Rent', 'Aluguer', 'Arrendamento' => 'AR',
                                default => 'RC',
                            },
                            'DocumentStatus' => [
                                'InvoiceStatus' => 'N',
                                'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                            ],
                            'PaymentAmount' => $pagamento->valor,
                            'PaymentMechanism' => 'CC',
                            'CustomerID' => $fatura->cliente_id,
                        ];
                    }
                }
            }
            if (!empty($paymentLines)) {
                $sourceDocuments['Payments'] = [
                    'NumberOfEntries' => count($paymentLines),
                    'TotalDebit' => collect($paymentLines)->sum('PaymentAmount'),
                    'TotalCredit' => 0,
                    'Payment' => $paymentLines,
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
                'xmlns' => 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01',
            ],
            'Header' => $header,
            'MasterFiles' => $masterFiles,
            'SourceDocuments' => $sourceDocuments,
        ];

        $xml = ArrayToXml::convert(
            $auditFile,
            'AuditFile',
            true,
            'UTF-8'
        );

        // Nome do arquivo baseado no tipo
        $fileName = $tipo == 'compra' ? 'SAFT_COMPRAS.xml' : 'SAFT_VENDAS.xml';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
    }

    public function listFaturas(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $dataInicial = $request->input('data_inicial');
        $dataFinal = $request->input('data_final');

        $tipo = $request->input('tipo'); // Compra ou Faturação

        $idEmpresa = $request->input('empresa_id');

        $query = null;

        if ($tipo == 'compra') {
            $query = DocumentoCompra::query()->where('empresa_id', $idEmpresa);
        } else {
            $query = Documento::query()->where('empresa_id', $idEmpresa);

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
            'SoftwareValidationNumber' => '123456/AGT/2023', //Número de validação do software
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
                'SelfBillingIndicator' => '0',
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
                'ProductCode' => $produto->codigo_produto ?? $produto->id,
                'ProductDescription' => $produto->nome,
                'ProductNumberCode' => $produto->id ?? 'Desconhecido',
            ];
        }

        $taxTable = [
            [
                'TaxType' => 'NS',
                'TaxCountryRegion' => 'AO',
                'TaxCode' => 'NS',
                'Description' => 'Nao sujeito a IVA',
                'TaxPercentage' => 0,
            ]
        ];

        $masterFiles = [
            'Customer' => $customers,
            'Product' => $products,
            'TaxTable' => [
                'TaxTableEntry' => $taxTable
            ]
        ];


        /*
    |--------------------------------------------------------------------------
    | 3️⃣ SOURCE DOCUMENTS (Faturas)
    |--------------------------------------------------------------------------
    */

        $queryFat = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento'])
            ->where(function ($q) {
                $q->where('cliente_id', '!=', '0')->whereNotNull('cliente_id');
            });

        if ($startDate && $endDate) {
            $queryFat->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        } elseif ($startDate) {
            $queryFat->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $queryFat->whereDate('created_at', '<=', $endDate);
        }

        $faturas = $queryFat->get();

        // Add virtual entries for products referenced in invoices but not in DB
        $existingCodes = collect($products)->pluck('ProductCode');
        $allLineCodes = $faturas->flatMap(fn($f) => $f->itens->pluck('produto_codigo'))->unique();
        foreach ($allLineCodes as $code) {
            if (!$existingCodes->contains($code)) {
                $products[] = [
                    'ProductType' => 'P',
                    'ProductCode' => $code,
                    'ProductDescription' => 'Produto (removido)',
                    'ProductNumberCode' => $code,
                ];
            }
        }

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
                    'TaxPointDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'), //Duvida
                    'Description' => $linha->descricao ?: $linha->produto_nome,
                    'DebitAmount' => $linha->total,
                    'Tax' => [
                        'TaxType' => $linha->iva_percent > 0 ? 'IVA' : 'NS',
                        'TaxCountryRegion' => 'AO',
                        'TaxCode' => $linha->iva_percent > 0 ? 'NOR' : 'NS',
                        'TaxPercentage' => $linha->iva_percent > 0 ? 14 : 0,
                    ],
                    'TaxExemptionReason' => $linha->iva_percent > 0 ? null : 'Transmissão de bens e serviços não sujeita',
                    'TaxExemptionCode' => $linha->iva_percent > 0 ? null : 'M02',
                ];
            }

            $invoices[] = [
                'InvoiceNo' => $this->normalizarNumeroFatura($fatura->num_fatura),
                'DocumentStatus' => [
                    'InvoiceStatus' => 'N',
                    'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'SourceID' => $fatura->utilizador_id,
                    'SourceBilling' => 'P',
                ],
                'Hash' => $fatura->hash,
                'HashControl' => '0',
                'InvoiceDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                'InvoiceType' => $fatura->tipo_sigla,
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
                    'TaxPayable' => $fatura->total_impostos,
                    'NetTotal' => $fatura->total_geral - $fatura->total_impostos,
                    'GrossTotal' => $fatura->total_geral,
                ],
            ];

            $totalDebit += $fatura->total_geral - $fatura->total_impostos;
        }

        $sourceDocuments = [
            'SalesInvoices' => [
                'NumberOfEntries' => count($invoices),
                'TotalDebit' => $totalDebit,
                'TotalCredit' => 0,
                'Invoice' => $invoices,
            ],
        ];

        // Working Documents (guias/transportes)
        $workDocument = [];
        $totalWorkDebit = 0;

        foreach ($faturas->whereIn('tipo_sigla', ['GR', 'GT']) as $fatura) {

            $linhas = [];

            foreach ($fatura->itens as $index => $linha) {
                $linhas[] = [
                    'LineNumber' => $index + 1,
                    'ProductCode' => $linha->produto_codigo,
                    'ProductDescription' => $linha->produto_nome,
                    'Quantity' => $linha->quantidade,
                    'UnitOfMeasure' => 'UN',
                    'UnitPrice' => $linha->preco_unitario,
                    'TaxPointDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                    'Description' => $linha->descricao ?: $linha->produto_nome,
                    'DebitAmount' => $linha->quantidade * $linha->preco_unitario,
                    'Tax' => [
                        'TaxType' => $linha->iva_percent > 0 ? 'IVA' : 'NS',
                        'TaxCountryRegion' => 'AO',
                        'TaxCode' => $linha->iva_percent > 0 ? 'NOR' : 'NS',
                        'TaxPercentage' => $linha->iva_percent > 0 ? 14 : 0,
                    ],
                    'TaxExemptionReason' => $linha->iva_percent > 0 ? null : 'Transmissão de bens e serviços não sujeita',
                    'TaxExemptionCode' => $linha->iva_percent > 0 ? null : 'M02',
                ];
            }

            $totalWorkDebit += $fatura->total_geral - $fatura->total_impostos;

            $workDocument[] = [
                'DocumentNumber' => $this->normalizarNumeroFatura($fatura->num_fatura),
                'DocumentStatus' => [
                    'WorkStatus' => 'N',
                    'WorkStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                    'SourceID' => $fatura->utilizador_id,
                    'SourceBilling' => 'P',
                ],
                'Hash' => $fatura->hash,
                'HashControl' => '0',
                'WorkDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                'WorkType' => $fatura->tipo_sigla === 'GT' ? 'GR' : $fatura->tipo_sigla,
                'SourceID' => $fatura->utilizador_id,
                'CustomerID' => $fatura->cliente_id,
                'Line' => $linhas,
                'DocumentTotals' => [
                    'TaxPayable' => $fatura->total_impostos,
                    'NetTotal' => $fatura->total_geral - $fatura->total_impostos,
                    'GrossTotal' => $fatura->total_geral,
                ],
            ];
        }

        if (!empty($workDocument)) {
            $sourceDocuments['WorkingDocuments'] = [
                'NumberOfEntries' => count($workDocument),
                'TotalDebit' => $totalWorkDebit,
                'TotalCredit' => 0,
                'WorkDocument' => $workDocument,
            ];
        }

        // Payments
        $paymentLines = [];
        $paymentCounters = [];

        foreach ($faturas as $fatura) {
            if ($fatura->meiosPagamento && $fatura->meiosPagamento->count()) {
                $paymentCounters[$fatura->id] = 0;
                foreach ($fatura->meiosPagamento as $pagamento) {
                    $paymentCounters[$fatura->id]++;
                    $pRef = $this->normalizarNumeroFatura($fatura->num_fatura);
                    if ($paymentCounters[$fatura->id] > 1) {
                        $pParts = explode(' ', $pRef, 2);
                        $nParts = explode('/', $pParts[1] ?? '0');
                        $pRef = $pParts[0] . ' ' . ($nParts[0] ?? '0') . '/' . $paymentCounters[$fatura->id];
                    }
                    $paymentLines[] = [
                        'PaymentRefNo' => $pRef,
                        'TransactionDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d'),
                        'PaymentType' => match ($fatura->forma_pagamento) {
                            'Cash', 'Numerário', 'Dinheiro' => 'RC',
                            'Credit', 'Crédito', 'Transferência' => 'RC',
                            'Debit', 'Débito' => 'RC',
                            'Refund', 'Reembolso' => 'RG',
                            'Rent', 'Aluguer', 'Arrendamento' => 'AR',
                            default => 'RC',
                        },
                        'DocumentStatus' => [
                            'InvoiceStatus' => 'N',
                            'InvoiceStatusDate' => Carbon::parse($fatura->data_emissao)->format('Y-m-d\TH:i:s'),
                        ],
                        'PaymentAmount' => $pagamento->valor,
                        'PaymentMechanism' => 'CC',
                        'CustomerID' => $fatura->cliente_id,
                    ];
                }
            }
        }

        if (!empty($paymentLines)) {
            $sourceDocuments['Payments'] = [
                'NumberOfEntries' => count($paymentLines),
                'TotalDebit' => collect($paymentLines)->sum('PaymentAmount'),
                'TotalCredit' => 0,
                'Payment' => $paymentLines,
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ JUNTAR TUDO
    |--------------------------------------------------------------------------
    */

        $auditFile = [
            '_attributes' => [
                'xmlns' => 'urn:OECD:StandardAuditFile-Tax:AO_1.01_01',
            ],
            'Header' => $header,
            'MasterFiles' => $masterFiles,
            'SourceDocuments' => $sourceDocuments,
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

    private function normalizarNumeroFatura($num)
    {
        $num = preg_replace('/\s+/', ' ', trim($num));
        $parts = explode(' ', $num, 2);
        if (count($parts) > 1) {
            return $parts[0] . ' ' . str_replace(' ', '', $parts[1]);
        }
        return str_replace(' ', '', $num);
    }
}
