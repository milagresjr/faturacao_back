<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Documento;
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

        $idEmpresa = $request->input('id_empresa');

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

        $clientes = Cliente::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

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

        $produtos = Produto::with('tipo')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

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

        $faturas = Documento::with(['itens', 'meiosPagamento', 'impostosDocumento'])
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        $invoices = [];
        $totalDebit = 0;

        foreach ($faturas as $fatura) {

            $linhas = [];

            foreach ($fatura->itens as $index => $linha) {
                $linhas[] = [
                    'LineNumber' => $index + 1,
                    'ProductCode' => $linha->produto_codigo,
                    'ProductDescription' => $linha->descricao,
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
                    'ProductDescription' => $linha->descricao,
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

    public function listFaturas(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $dataInicial = $request->input('data_inicial');
        $dataFinal = $request->input('data_final');

        $query = Documento::query();

        if ($dataInicial && $dataFinal) {
            $query->whereBetween('created_at', [$dataInicial, $dataFinal]);
        }

        $faturas = $query->whereNotIn('estado_documento', ['anulado', 'rascunho'])
            ->whereIn('tipo_sigla', ['FR', 'FT', 'FA', 'ND'])
            ->paginate($perPage);

        return response()->json($faturas);
    }
}
