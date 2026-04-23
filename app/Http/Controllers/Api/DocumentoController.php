<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPagamento;
use App\Enums\EstadoVencimento;
use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\BancoDocumento;
use App\Models\Cliente;
use App\Models\ConfiguracaoFatura;
use App\Models\Conta;
use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\Empresa;
use App\Models\ImpostoDocumento;
use App\Models\ImpostoDocumentoCompra;
use App\Models\InfoGuia;
use App\Models\MeioPagamentoDocumento;
use App\Models\PagamentoDocumentoCompra;
use App\Models\TipoTaxaIva;
use App\Services\DocumentoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->input("per_page", 10);

        $search = $request->query("search");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $status = $request->query("status"); // pago, por_pagar, vencido
        $entidadeId = $request->query("entidade_id"); // cliente
        $valorMin = $request->query("valor_min");
        $valorMax = $request->query("valor_max");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery
                ->where("num_fatura", "like", "%" . $search . "%")
                ->orWhere("cliente_nome", "like", "%" . $search . "%")
                ->orWhere("utilizador", "like", "%" . $search . "%")
                ->orWhere("total_geral", "like", "%" . $search . "%");
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where("entidade_id", $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween("total_geral", [
                $valorMin,
                $valorMax,
            ]);
        } elseif ($valorMin) {
            $documentoQuery->where("total_geral", ">=", $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where("total_geral", "<=", $valorMax);
        }

        // Filtrar por status
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        //Nao trazer NQ, EI e SI
        $documentoQuery->whereNotIn("tipo_sigla", ["NQ", "EI", "SI"]);

        $documentos = $documentoQuery
            ->with([
                "itens",
                "meiosPagamento",
                "impostosDocumento",
                "documentosRelacionados", // documentos que este documento referencia
                "relacionadoEm", // documentos que referenciam este documento
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function listFaturas(Request $request)
    {
        $per_page = $request->input("per_page", 10);

        $search = $request->query("search");
        $clienteId = $request->query("cliente_id");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $status = $request->query("status"); // pago, por_pagar, vencido
        $entidadeId = $request->query("entidade_id"); // cliente
        $valorMin = $request->query("valor_min");
        $valorMax = $request->query("valor_max");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery
                ->where("num_fatura", "like", "%" . $search . "%")
                ->orWhere("cliente_nome", "like", "%" . $search . "%")
                ->orWhere("utilizador", "like", "%" . $search . "%")
                ->orWhere("total_geral", "like", "%" . $search . "%");
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        }

        // 👤 Filtrar por cliente
        if ($clienteId) {
            $documentoQuery->where("cliente_id", $clienteId);
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where("entidade_id", $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween("total_geral", [
                $valorMin,
                $valorMax,
            ]);
        } elseif ($valorMin) {
            $documentoQuery->where("total_geral", ">=", $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where("total_geral", "<=", $valorMax);
        }

        // Filtrar por status
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        $documentoQuery->whereIn("tipo_sigla", ["FT", "FA", "FR", "FG"]);

        $documentos = $documentoQuery
            ->with([
                "itens",
                "meiosPagamento",
                "impostosDocumento",
                "documentosRelacionados", // documentos que este documento referencia
                "relacionadoEm", // documentos que referenciam este documento
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function listFaturaProforma(Request $request)
    {
        $per_page = $request->input("per_page", 10);

        $search = $request->query("search");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $status = $request->query("status"); // pago, por_pagar, vencido
        $entidadeId = $request->query("entidade_id"); // cliente
        $valorMin = $request->query("valor_min");
        $valorMax = $request->query("valor_max");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery
                ->where("num_fatura", "like", "%" . $search . "%")
                ->orWhere("cliente_nome", "like", "%" . $search . "%")
                ->orWhere("utilizador", "like", "%" . $search . "%")
                ->orWhere("total_geral", "like", "%" . $search . "%");
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where("entidade_id", $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween("total_geral", [
                $valorMin,
                $valorMax,
            ]);
        } elseif ($valorMin) {
            $documentoQuery->where("total_geral", ">=", $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where("total_geral", "<=", $valorMax);
        }

        // Filtrar por status
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        $documentoQuery->whereIn("tipo_sigla", ["PP", "EC", "OR", "OT"]);

        $documentos = $documentoQuery
            ->with([
                "itens",
                "meiosPagamento",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function listGuias(Request $request)
    {
        $per_page = $request->input("per_page", 10);

        $search = $request->query("search");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $status = $request->query("status"); // pago, por_pagar, vencido
        $entidadeId = $request->query("entidade_id"); // cliente
        $valorMin = $request->query("valor_min");
        $valorMax = $request->query("valor_max");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery
                ->where("num_fatura", "like", "%" . $search . "%")
                ->orWhere("cliente_nome", "like", "%" . $search . "%")
                ->orWhere("utilizador", "like", "%" . $search . "%")
                ->orWhere("total_geral", "like", "%" . $search . "%");
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where("entidade_id", $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween("total_geral", [
                $valorMin,
                $valorMax,
            ]);
        } elseif ($valorMin) {
            $documentoQuery->where("total_geral", ">=", $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where("total_geral", "<=", $valorMax);
        }

        // Filtrar por status
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        $documentoQuery->whereIn("tipo_sigla", ["GR", "GT"]);

        $documentos = $documentoQuery
            ->with([
                "itens",
                "meiosPagamento",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->paginate($per_page);

        return response()->json($documentos);
    }

    public function listNotaCredito(Request $request)
    {
        $per_page = $request->input("per_page", 10);

        $search = $request->query("search");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $status = $request->query("status"); // pago, por_pagar, vencido
        $entidadeId = $request->query("entidade_id"); // cliente
        $valorMin = $request->query("valor_min");
        $valorMax = $request->query("valor_max");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 🔍 Pesquisa por número da fatura
        if ($search) {
            $documentoQuery
                ->where("num_fatura", "like", "%" . $search . "%")
                ->orWhere("cliente_nome", "like", "%" . $search . "%")
                ->orWhere("utilizador", "like", "%" . $search . "%")
                ->orWhere("total_geral", "like", "%" . $search . "%");
        }

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        // 👤 Filtrar por cliente/entidade
        if ($entidadeId) {
            $documentoQuery->where("entidade_id", $entidadeId);
        }

        // 💰 Filtro por valor
        if ($valorMin && $valorMax) {
            $documentoQuery->whereBetween("total_geral", [
                $valorMin,
                $valorMax,
            ]);
        } elseif ($valorMin) {
            $documentoQuery->where("total_geral", ">=", $valorMin);
        } elseif ($valorMax) {
            $documentoQuery->where("total_geral", "<=", $valorMax);
        }

        // Filtrar por status
        /*  if ($status) {
            $documentoQuery->where(function ($query) use ($status) {
                if ($status === 'pago') {
                    $query->whereColumn('total_pago', '>=', 'total_geral');
                } elseif ($status === 'por_pagar') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '>=', now());
                } elseif ($status === 'vencido') {
                    $query->whereColumn('total_pago', '<', 'total_geral')
                        ->whereDate('data_vencimento', '<', now());
                }
            });
        } */

        $documentoQuery->where("tipo_sigla", "NC");

        $documentos = $documentoQuery
            ->with([
                "itens",
                "meiosPagamento",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->paginate($per_page);

        return response()->json($documentos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, DocumentoService $documentoService)
    {
        $numFatura = $this->gerarNumeroDocumento(
            $request->input("sigla_fatura"),
            $request->input("empresa_id"),
        );

        // Validação dos dados recebidos
        $validated = Validator::make(
            $request->all(),
            [
                // Dados do documento
                "tipo_fatura" => "required|string",
                "sigla_fatura" => "required|string",
                "tipo_cor" => "nullable|string",

                "estado_documento" => "nullable|string",
                "estado_pagamento" => "nullable|string",
                "estado_vencimento" => "nullable|string",

                "empresa_id" => "nullable|integer",
                "empresa_nome" => "required|string",
                "empresa_nif" => "required|integer",
                "empresa_telefone" => "nullable|integer",
                "empresa_email" => "nullable|email",
                "empresa_endereco" => "nullable|string",

                "cliente_id" => "nullable|integer",
                "cliente_nome" => "required|string",
                "cliente_nif" => "required|string",
                "cliente_telefone" => "nullable|string",
                "cliente_email" => "nullable|email",
                "cliente_endereco" => "nullable|string",

                "caixa" => "required|string",
                "data_emissao" => "required|date",
                "data_vencimento" => "required|date",
                "is_apronto" => "nullable|boolean",
                "movimenta_stock" => "required|boolean",

                "taxa_iva" => "nullable|numeric",
                "valor_iva" => "nullable|numeric",

                "desconto_total" => "nullable|numeric",
                "valor_transporte" => "nullable|numeric",
                "total_sem_desconto" => "nullable|numeric",
                "total_impostos" => "nullable|numeric",
                "total_geral" => "nullable|numeric",

                "meiosPagamento" => "nullable|array",
                "meiosPagamento.*.descricao" => "nullable|string",
                "meiosPagamento.*.valor" => "nullable|numeric",

                "marca" => "nullable|string",
                "matricula" => "nullable|string",
                "local_origem" => "nullable|string",
                "local_destino" => "nullable|string",
                "data_origem" => "nullable|date",
                "data_destino" => "nullable|date",

                // Itens do documento
                "itens" => "required|array|min:1",
                "itens.*.produto_nome" => "required|string",
                "itens.*.codigo_produto" => "required|string",
                "itens.*.preco_venda" => "required|numeric",
                "itens.*.descricao" => "nullable|string",
                "itens.*.quantidade" => "required|integer",
                "itens.*.desconto_percent" => "required|numeric",
                "itens.*.desconto_fixo" => "required|numeric",
                "itens.*.iva_percent" => "nullable|numeric",
            ],
            [
                // Mensagens personalizadas de validação
                "required" => "O campo :attribute é obrigatório.",
                "string" => "O campo :attribute deve ser uma string.",
                "integer" => "O campo :attribute deve ser um número inteiro.",
                "numeric" => "O campo :attribute deve ser um número.",
                "email" => "O campo :attribute deve ser um email válido.",
                "date" => "O campo :attribute deve ser uma data válida.",
                "array" => "O campo :attribute deve ser uma lista.",
                "min" => [
                    "array" =>
                    "O campo :attribute deve ter pelo menos :min item(ns).",
                ],
            ],
        );

        if ($validated->fails()) {
            return response()->json(
                [
                    "message" => "Erro de validação.",
                    "errors" => $validated->errors(),
                ],
                422,
            );
        }

        // VALIDAÇÃO: Verificar data da última fatura
        $validacaoData = $this->validateInvoiceDate(
            $request->cliente_id,
            $request->data_emissao,
            $request->empresa_id
        );

        if (!$validacaoData['allowed']) {
            return response()->json([
                'message' => $validacaoData['message'],
                'error' => 'INVALID_INVOICE_DATE',
                'details' => [
                    'ultima_fatura_emitida' => $validacaoData['ultima_fatura'] ?? null,
                    'data_ultima_fatura' => $validacaoData['ultima_data'] ?? null,
                    'data_solicitada' => $validacaoData['data_solicitada'] ?? null
                ]
            ], 422);
        }


        // Construção do quadro por taxas (mantendo também o 'liquido' por grupo)
        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item["iva_percent"]);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item["motivo_isencao_id"] ?? "";
            $motivo = "";

            if ($codigo === "ISENTO" && $motivoIsencaoId) {
                $motivo = DB::table("motivo_isencao")
                    ->where("id", $motivoIsencaoId)
                    ->value("motivo");
            }

            $subtotalBruto = $item["preco_venda"] * $item["quantidade"];

            $desconto = 0;
            if (
                isset($item["desconto_percent"]) &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $subtotalBruto * ($item["desconto_percent"] / 100);
            } elseif (
                isset($item["desconto_fixo"]) &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + $taxaIva / 100), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . "|" . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    "taxa" => $taxaIva,
                    "codigo" => $codigo,
                    "motivo_isencao" => $motivo,
                    "incidencia" => 0.0, // base
                    "imposto" => 0.0,
                    "liquido" => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]["incidencia"] += $base;
            $quadroImposto[$chave]["imposto"] += $imposto;
            $quadroImposto[$chave]["liquido"] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;
        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {
            $precoBruto = $item["preco_venda"] * $item["quantidade"];

            // Desconto do item
            $desconto = 0;
            if (
                $item["desconto_percent"] !== null &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $precoBruto * ($item["desconto_percent"] / 100);
            } elseif (
                $item["desconto_fixo"] !== null &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"] * $item["quantidade"];
            }

            $subtotalComIva = $precoBruto - $desconto;

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request["desconto_tipo"] === "percentual") {
            $descontoGeral = $totalSemDesconto * ($request["desconto_total"] / 100);
        } elseif ($request["desconto_tipo"] === "fixo") {
            $descontoGeral = $request["desconto_total"]; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha["liquido"] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha["liquido"] = round(
                    $linha["liquido"] - $descontoLinha,
                    2,
                );

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha["incidencia"] = round(
                    $linha["liquido"] / (1 + $linha["taxa"] / 100),
                    2,
                );
                $linha["imposto"] = round(
                    $linha["liquido"] - $linha["incidencia"],
                    2,
                );

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, "liquido"));
            $totalBase = array_sum(array_column($quadroImposto, "incidencia"));
            $totalImposto = array_sum(array_column($quadroImposto, "imposto"));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal =
            $totalSemDesconto - $descontoItensTotal - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, "imposto"));

        $retencao = 0;
        //Calcular retencao na fonte nos servicos
        foreach ($request["itens"] as $item) {
            if (isset($item["tipo_produto"]) && $item["tipo_produto"] === "S") {
                if ($item["preco_venda"] > 20000) {
                    $retencao += $item["preco_venda"] * 0.06; // 5% de retenção
                }
            }
        }

        $totalEntregue = 0;

        foreach ($request->input("meiosPagamento") as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento["valor"];
        }

        $troco = $totalEntregue - $totalFinal;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }

        DB::beginTransaction();

        try {

            $infoGuiaId = null;

            if (
                $request["sigla_fatura"] === "GT" ||
                $request["sigla_fatura"] === "GR"
            ) {
                $infoGuiaId = DB::table("info_guias")->insertGetId([
                    "marca" => $request->input("marca"),
                    "matricula" => $request->input("matricula"),
                    "local_origem" => $request->input("local_origem"),
                    "local_destino" => $request->input("local_destino"),
                    "data_origem" => $request->input("data_origem"),
                    "data_destino" => $request->input("data_destino"),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }

            //verifica o estado de pagamento
            if ($totalFinal - $totalEntregue <= 0) {
                $request["estado_pagamento"] = EstadoPagamento::PAGO->value;
            } elseif ($totalEntregue > 0 && $totalFinal - $totalEntregue > 0) {
                $request["estado_pagamento"] =
                    EstadoPagamento::PARCIALMENTE_PAGO->value;
            } else {
                $request["estado_pagamento"] = EstadoPagamento::NAO_PAGO->value;
            }

            // Criação do documento
            $documento = Documento::create([
                "tipo_nome" => $request["tipo_fatura"],
                "tipo_sigla" => $request["sigla_fatura"],
                //'tipo_cor' => $request['tipo_cor'],

                "armazem_id" => $request['armazem_id'],

                "estado_documento" => $request["estado_documento"] ?? "emitido",
                "estado_pagamento" =>
                $request["estado_pagamento"] ?? "por_pagar",
                "estado_vencimento" =>
                $request["estado_vencimento"] ?? "no_prazo",

                "num_fatura" =>
                $request["estado_documento"] === "rascunho"
                    ? ""
                    : $numFatura,

                "via" => $request["via"] ?? "original",

                "empresa_id" => $request["empresa_id"],
                "empresa_nome" => $request["empresa_nome"],
                "empresa_nif" => $request["empresa_nif"],
                "empresa_telefone" => $request["empresa_telefone"],
                "empresa_email" => $request["empresa_email"],
                "empresa_endereco" => $request["empresa_endereco"],

                "cliente_id" => $request["cliente_id"] ?? null,
                "cliente_nome" => $request["cliente_nome"],
                "cliente_nif" => $request["cliente_nif"],
                "cliente_telefone" => $request["cliente_telefone"],
                "cliente_email" => $request["cliente_email"],
                "cliente_endereco" => $request["cliente_endereco"],

                "caixa" => $request["caixa"],
                "data_emissao" => $request["data_emissao"],
                "data_vencimento" => $request["data_vencimento"],
                "forma_pagamento" => $request["forma_pagamento"],
                "movimenta_stock" => $request["movimenta_stock"],

                "taxa_iva" => "0",
                "valor_iva" => "0",
                "retencao" => $retencao,

                "estado" => "emitido",

                "hash" => "aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd",

                "desconto_tipo" => $request["desconto_tipo"] ?? "",
                "desconto_total" => $descontoItensTotal + $descontoGeral,
                "valor_transporte" => $request["valor_transporte"],
                "total_sem_desconto" => $totalSemDesconto,
                "total_impostos" => $totalImpostos,
                "total_geral" => $totalFinal,
                "troco" => $troco,

                "utilizador_id" => $request["utilizador_id"],
                "utilizador" => $request["utilizador"],

                "info_guia_id" => $infoGuiaId,
            ]);

            $bancos = Conta::with("banco")
                ->where("empresa_id", $request->input("empresa_id"))
                ->where("estado", true)
                ->get();

            foreach ($bancos as $banco) {
                DB::table("bancos_documento")->insert([
                    "documento_id" => $documento->id,
                    "sigla" => $banco["banco"]->sigla,
                    "descricao" => $banco["banco"]->descricao,
                    "numero_conta" => $banco->numero_conta,
                    "iban" => $banco->iban,
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }

            foreach ($request->input("meiosPagamento") as $meioPagamento) {
                MeioPagamentoDocumento::create([
                    "documento_id" => $documento->id,
                    "descricao" => $meioPagamento["descricao"],
                    "valor" => $meioPagamento["valor"],
                ]);
            }

            foreach ($quadroImposto as $value) {
                $value["incidencia"] = round($value["incidencia"], 2);
                $value["imposto"] = round($value["imposto"], 2);
                $value["liquido"] = round($value["liquido"], 2);

                ImpostoDocumento::create([
                    "documento_id" => $documento->id,
                    "taxa" => $value["taxa"],
                    "codigo" => $value["codigo"],
                    "isento" => $value["codigo"] === "ISENTO" ? 1 : 0,
                    "motivo_isencao" => $value["motivo_isencao"],
                    "incidencia" => $value["incidencia"],
                    "imposto" => $value["imposto"],
                    //'liquido' => $value['liquido'],
                    "total" => $value["incidencia"] + $value["imposto"],
                ]);
            }

            // Criação dos itens
            $itens = [];
            foreach ($request["itens"] as $item) {
                $idImpostoTaxa = $item["iva_percent"];
                $taxaIva = TipoTaxaIva::find($idImpostoTaxa)->taxa;
                $codigoIva = TipoTaxaIva::find($idImpostoTaxa)->codigo;
                //$motivoIsencaoCodigo = null;
                $motivoIsencaoDescricao = null;

                if ($codigoIva === "ISENTO") {
                    $motivo = DB::table("motivo_isencao")
                        ->where("id", $item["motivo_isencao_id"]) // <-- aqui
                        ->first();
                    if ($motivo) {
                        $codigoIva = $motivo->codigo;
                        $motivoIsencaoDescricao = $motivo->motivo;
                    }
                }

                $desconto = 0;
                if (
                    isset($item["desconto_percent"]) &&
                    $item["desconto_percent"] > 0
                ) {
                    $desconto =
                        $item["preco_venda"] *
                        ($item["desconto_percent"] / 100);
                } elseif (
                    isset($item["desconto_fixo"]) &&
                    $item["desconto_fixo"] > 0
                ) {
                    $desconto = $item["desconto_fixo"];
                }

                // Calcula o total do item (sem IVA)
                $totalSemDesconto = $item["preco_venda"] * $item["quantidade"];
                $totalItem = $totalSemDesconto - $desconto;

                $itens[] = [
                    "documento_id" => $documento->id,
                    "produto_id" => $item["produto_id"],
                    "produto_nome" => $item["produto_nome"],
                    "produto_codigo" => $item["codigo_produto"],
                    "preco_unitario" => $item["preco_venda"],
                    "descricao" => $item["descricao"],
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => $item["desconto_percent"],
                    "desconto_fixo" => $item["desconto_fixo"],
                    "iva_percent" => $taxaIva ?? 0,
                    "imposto_taxa_id" => $idImpostoTaxa,
                    "codigo_iva" => $codigoIva ?? "",
                    "tipo_id" => $item["tipo_id"],
                    "motivo_isencao" => $motivoIsencaoDescricao,
                    "total_sem_desconto" => $totalSemDesconto,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $documento->itens()->createMany($itens);

            // Criar Recibo caso o tipo de vencimento for a prazo
            if (
                $request->has("is_apronto") &&
                $request->input("is_apronto") === "1"
            ) {
                // Se for um APRONTO, relaciona com o documento relacionado
                $request->merge([
                    "tipo_nome" => "Recibo",
                    "tipo_sigla" => "RC",
                    "total_geral" => $totalFinal,
                    "documento_relacionado_id" => $documento->id,
                ]);

                $data = [
                    "tipo_fatura" => "Recibo", // "RECIBO"
                    "sigla_fatura" => "RC",
                    "total_geral" => $totalFinal,
                    "documento_relacionado_id" => $documento->id,
                    "empresa_id" => $documento->empresa_id,
                    "empresa_nome" => $documento->empresa_nome,
                    "cliente_id" => $documento->cliente_id,
                    "cliente_nome" => $documento->cliente_nome,
                    "meiosPagamento" => $request->input("meiosPagamento"),
                    "utilizador_id" => $request->input("utilizador_id"),
                    "utilizador" => $request->input("utilizador"),
                    "caixa" => $documento->caixa,
                    "data_emissao" => $documento->data_emissao,
                    "data_vencimento" => $documento->data_vencimento,
                ];

                $recibo = $this->storeRecibo(new Request($data));

                return response()->json(
                    [
                        "message" => "Factura e Recibo criados com sucesso.",
                        "documento" => $documento->load("itens"),
                        "documento_recibo" => $recibo->documento ?? "",
                    ],
                    201,
                );
            }

            //Atualizar hash
            $hash = $this->calcularHash($documento->id);
            $documento->update(['hash' => $hash]);

            //Se movimentar stock for true, atualiza o stock
            if ($request->boolean("movimenta_stock")) {
                $documentoService->updateStock($documento->load("itens"));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Erro ao criar o documento.",
                    "file" => $th->getFile(),
                    "line" => $th->getLine(),
                    "error" => $th->getMessage(),
                ],
                500,
            );
        }


        return response()->json(
            [
                "message" => "Documento criado com sucesso.",
                "documento" => $documento->load("itens"),
            ],
            201,
        );
    }

    public function calcularHash($id)
    {
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(["message" => "Documento não encontrado."], 404);
        }

        //Formatar campos obrigatórios
        $invoiceDate = Carbon::parse($documento->data_emissao)
            ->format('Y-m-d');

        $systemEntryDate = Carbon::parse($documento->created_at)
            ->format('Y-m-d\TH:i:s');

        $grossTotal = number_format($documento->total_geral, 2, '.', '');

        //Buscar hash anterior (respeitando cadeia correta)

        $hashAnterior = Documento::where('empresa_id', $documento->empresa_id)
            ->where('tipo_sigla', $documento->tipo_sigla)
            // ->where('serie', $documento->serie)
            ->whereYear('data_emissao', $documento->data_emissao->year)
            ->where('id', '<', $documento->id)
            ->orderBy('id', 'desc')
            ->value('hash') ?? '';

        //Montar string exatamente no padrão AGT

        $mensagem = $invoiceDate . ';' .
            $systemEntryDate . ';' .
            $documento->num_fatura . ';' .
            $grossTotal . ';' .
            $hashAnterior;

        //Carregar chave privada

        $privateKey = openssl_pkey_get_private(
            file_get_contents(storage_path('app/keys/ChavePrivada.pem'))
        );

        //Assinar (RSA 1024 + SHA1 + PKCS1 v1.5)

        openssl_sign($mensagem, $assinatura, $privateKey, OPENSSL_ALGO_SHA1);

        //Converter para Base64 (172 caracteres)

        $hash = base64_encode($assinatura);

        return $hash;
    }

    private function validateInvoiceDate($clienteId, $novaDataEmissao, $empresaId = null)
    {
        // Buscar a última fatura emitida para este cliente
        $query = Documento::where('cliente_id', $clienteId)
            ->whereIn('tipo_sigla', ['FT', 'FR', 'FG']) // Ajuste conforme suas siglas
            ->where('estado', 'emitido'); // Apenas faturas emitidas, não rascunhos

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $ultimaFatura = $query->orderBy('data_emissao', 'desc')
            ->orderBy('id', 'desc') // Desempate por ID se mesma data
            ->first();

        if ($ultimaFatura) {
            $ultimaData = Carbon::parse($ultimaFatura->data_emissao);
            $novaData = Carbon::parse($novaDataEmissao);

            // Verificar se a nova data é anterior à última fatura
            if ($novaData->lt($ultimaData)) {
                return [
                    'allowed' => false,
                    'message' => "Não é permitido emitir fatura com data anterior à última fatura emitida.",
                    'ultima_data' => $ultimaData->format('d/m/Y'),
                    'ultima_fatura' => $ultimaFatura->num_fatura,
                    'data_solicitada' => $novaData->format('d/m/Y')
                ];
            }
        }

        return ['allowed' => true];
    }

    public function destroyDocRascunho(string $id)
    {
        $documento = Documento::where("estado_documento", "rascunho")->find($id);
        if (!$documento) {
            return response()->json(["message" => "Document not found!"], 404);
        }

        $delete = $documento->delete();

        if (!$delete) {
            response()->json([
                'message' => 'Erro ao deletar'
            ]);
        }

        return response()->json(
            [
                "message" => "Documento excluido com sucesso!",
            ]
        );
    }

    public function transformarDocumento(Request $request, $id)
    {
        $validated = Validator::make(
            $request->all(),
            [
                "tipo_destino" => "required|string",
                "tipo_nome_destino" => "required|string",

                "caixa" => "required|string",
                "data_emissao" => "nullable|date",
                "data_vencimento" => "nullable|date",
                "is_apronto" => "nullable|boolean",
                "movimenta_stock" => "required|boolean",

                "desconto_total" => "nullable|numeric",
                "valor_transporte" => "nullable|numeric",
                "total_sem_desconto" => "nullable|numeric",
                "total_impostos" => "nullable|numeric",
                "total_geral" => "nullable|numeric",

                "meiosPagamento" => "nullable|array",
                "meiosPagamento.*.descricao" => "nullable|string",
                "meiosPagamento.*.valor" => "nullable|numeric",

                "marca" => "nullable|string",
                "matricula" => "nullable|string",
                "local_origem" => "nullable|string",
                "local_destino" => "nullable|string",
                "data_origem" => "nullable|date",
                "data_destino" => "nullable|date",

                "itens" => "nullable|array",
                "itens.*.produto_nome" => "required|string",
                "itens.*.codigo_produto" => "required|string",
                "itens.*.preco_venda" => "required|numeric",
                "itens.*.descricao" => "nullable|string",
                "itens.*.quantidade" => "required|integer",
                "itens.*.desconto_percent" => "required|numeric",
                "itens.*.desconto_fixo" => "required|numeric",
                "itens.*.iva_percent" => "nullable|numeric",
            ],
            [
                // Mensagens personalizadas de validação
                "required" => "O campo :attribute é obrigatório.",
                "string" => "O campo :attribute deve ser uma string.",
                "integer" => "O campo :attribute deve ser um número inteiro.",
                "numeric" => "O campo :attribute deve ser um número.",
                "email" => "O campo :attribute deve ser um email válido.",
                "date" => "O campo :attribute deve ser uma data válida.",
                "array" => "O campo :attribute deve ser uma lista.",
                "min" => [
                    "array" =>
                    "O campo :attribute deve ter pelo menos :min item(ns).",
                ],
            ],
        );

        if ($validated->fails()) {
            return response()->json(
                [
                    "message" => "Erro de validação.",
                    "errors" => $validated->errors(),
                ],
                422,
            );
        }

        // Construção do quadro por taxas (mantendo também o 'liquido' por grupo)
        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item["iva_percent"]);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item["motivo_isencao_id"] ?? "";
            $motivo = "";

            if ($codigo === "ISENTO" && $motivoIsencaoId) {
                $motivo = DB::table("motivo_isencao")
                    ->where("id", $motivoIsencaoId)
                    ->value("motivo");
            }

            $subtotalBruto = $item["preco_venda"] * $item["quantidade"];

            $desconto = 0;
            if (
                isset($item["desconto_percent"]) &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $subtotalBruto * ($item["desconto_percent"] / 100);
            } elseif (
                isset($item["desconto_fixo"]) &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + $taxaIva / 100), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . "|" . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    "taxa" => $taxaIva,
                    "codigo" => $codigo,
                    "motivo_isencao" => $motivo,
                    "incidencia" => 0.0, // base
                    "imposto" => 0.0,
                    "liquido" => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]["incidencia"] += $base;
            $quadroImposto[$chave]["imposto"] += $imposto;
            $quadroImposto[$chave]["liquido"] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;
        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {
            $precoBruto = $item["preco_venda"] * $item["quantidade"];

            // Desconto do item
            $desconto = 0;
            if (
                $item["desconto_percent"] !== null &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $precoBruto * ($item["desconto_percent"] / 100);
            } elseif (
                $item["desconto_fixo"] !== null &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"] * $item["quantidade"];
            }

            $subtotalComIva = $precoBruto - $desconto;

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request["desconto_tipo"] === "percentual") {
            $descontoGeral =
                $totalSemDesconto * ($request["desconto_total"] / 100);
        } elseif ($request["desconto_tipo"] === "fixo") {
            $descontoGeral = $request["desconto_total"]; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha["liquido"] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha["liquido"] = round(
                    $linha["liquido"] - $descontoLinha,
                    2,
                );

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha["incidencia"] = round(
                    $linha["liquido"] / (1 + $linha["taxa"] / 100),
                    2,
                );
                $linha["imposto"] = round(
                    $linha["liquido"] - $linha["incidencia"],
                    2,
                );

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, "liquido"));
            $totalBase = array_sum(array_column($quadroImposto, "incidencia"));
            $totalImposto = array_sum(array_column($quadroImposto, "imposto"));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal =
            $totalSemDesconto - $descontoItensTotal - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, "imposto"));

        $retencao = 0;
        //Calcular retencao na fonte nos servicos
        foreach ($request["itens"] as $item) {
            if (isset($item["tipo_produto"]) && $item["tipo_produto"] === "S") {
                if ($item["preco_venda"] > 20000) {
                    $retencao += $item["preco_venda"] * 0.06; // 5% de retenção
                }
            }
        }

        $totalEntregue = 0;

        foreach ($request->input("meiosPagamento") as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento["valor"];
        }

        $troco = $totalEntregue - $totalFinal;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }


        try {

            DB::beginTransaction();

            // 1️⃣ Buscar o documento original
            $documentoOrigem = Documento::with([
                "itens",
                "impostosDocumento",
                "meiosPagamento",
            ])->findOrFail($id);

            // Verifica se já foi transformado
            if ($documentoOrigem->estado === "transformado") {
                return response()->json(
                    [
                        "message" => "Este documento já foi transformado.",
                    ],
                    400,
                );
            }

            $infoGuiaId = null;

            if (
                $request["sigla_fatura"] === "GT" ||
                $request["sigla_fatura"] === "GR"
            ) {
                $infoGuiaId = DB::table("info_guias")->insertGetId([
                    "marca" => $request->input("marca"),
                    "matricula" => $request->input("matricula"),
                    "local_origem" => $request->input("local_origem"),
                    "local_destino" => $request->input("local_destino"),
                    "data_origem" => $request->input("data_origem"),
                    "data_destino" => $request->input("data_destino"),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }

            //verifica o estado de pagamento
            if ($totalFinal - $totalEntregue <= 0) {
                $request["estado_pagamento"] = EstadoPagamento::PAGO->value;
            } elseif ($totalEntregue > 0 && $totalFinal - $totalEntregue > 0) {
                $request["estado_pagamento"] =
                    EstadoPagamento::PARCIALMENTE_PAGO->value;
            } else {
                $request["estado_pagamento"] = EstadoPagamento::NAO_PAGO->value;
            }



            // 2️⃣ Determinar tipo de destino (ex: FT)
            $tipoDestino = $request->input("tipo_destino");
            $tipoNomeDestino = $request->input("tipo_nome_destino");

            // 3️⃣ Gerar número sequencial da nova faturas
            $numFatura = $this->gerarNumeroDocumento(
                $tipoDestino,
                $documentoOrigem->empresa_id,
            );

            // 4️⃣ Criar novo documento
            $novoDocumento = Documento::create([
                "tipo_nome" => $tipoNomeDestino,
                "tipo_sigla" => $tipoDestino,

                "estado_documento" => $request["estado_documento"] ?? "emitido",
                "estado_pagamento" =>
                $request["estado_pagamento"] ?? "por_pagar",
                "estado_vencimento" =>
                $request["estado_vencimento"] ?? "no_prazo",

                "num_fatura" => $numFatura,
                "via" => "original",

                "empresa_id" => $documentoOrigem->empresa_id,
                "empresa_nome" => $documentoOrigem->empresa_nome,
                "empresa_nif" => $documentoOrigem->empresa_nif,
                "empresa_telefone" => $documentoOrigem->empresa_telefone,
                "empresa_email" => $documentoOrigem->empresa_email,
                "empresa_endereco" => $documentoOrigem->empresa_endereco,

                "cliente_id" => $documentoOrigem->cliente_id,
                "cliente_nome" => $documentoOrigem->cliente_nome,
                "cliente_nif" => $documentoOrigem->cliente_nif,
                "cliente_telefone" => $documentoOrigem->cliente_telefone,
                "cliente_email" => $documentoOrigem->cliente_email,
                "cliente_endereco" => $documentoOrigem->cliente_endereco,

                "caixa" => $request["caixa"] ?? $documentoOrigem->caixa,
                "data_emissao" => $request->input("data_emissao") ?? now(),
                "data_vencimento" =>
                $request->input("data_vencimento") ??
                    $documentoOrigem->data_vencimento,
                "forma_pagamento" => $request["forma_pagamento"] ?? $documentoOrigem->forma_pagamento,
                "movimenta_stock" => $request["movimenta_stock"] ?? $documentoOrigem->movimenta_stock,

                "taxa_iva" => $documentoOrigem->taxa_iva,
                "valor_iva" => $documentoOrigem->valor_iva,
                "retencao" => $retencao,

                "estado" => "emitido",
                "hash" => "TRANSFORM-" . uniqid(),

                "desconto_tipo" => $request["desconto_tipo"] ?? $documentoOrigem->desconto_tipo,
                "desconto_total" => $documentoOrigem->desconto_total,
                "valor_transporte" => $documentoOrigem->valor_transporte,
                "total_sem_desconto" => $documentoOrigem->total_sem_desconto,
                "total_impostos" => $documentoOrigem->total_impostos,
                "total_geral" => $documentoOrigem->total_geral,
                "troco" => $documentoOrigem->troco,

                "utilizador_id" => $request["utilizador_id"] ?? $documentoOrigem->utilizador_id,
                "utilizador" => $request["utilizador"] ?? $documentoOrigem->utilizador,

                "info_guia_id" => $infoGuiaId,
                "documento_origem_id" => $documentoOrigem->id, // 🔗 referência
            ]);

            foreach ($request->input("meiosPagamento") as $meioPagamento) {
                MeioPagamentoDocumento::create([
                    "documento_id" => $novoDocumento->id,
                    "descricao" => $meioPagamento["descricao"],
                    "valor" => $meioPagamento["valor"],
                ]);
            }

            // 5️⃣ Copiar impostos (quadro)
            // foreach ($documentoOrigem->impostosDocumento as $imp) {
            //     ImpostoDocumento::create([
            //         "documento_id" => $novoDocumento->id,
            //         "taxa" => $imp->taxa,
            //         "codigo" => $imp->codigo,
            //         "isento" => $imp->isento,
            //         "motivo_isencao" => $imp->motivo_isencao,
            //         "incidencia" => $imp->incidencia,
            //         "imposto" => $imp->imposto,
            //         "total" => $imp->total,
            //     ]);
            // }

            foreach ($quadroImposto as $value) {
                $value["incidencia"] = round($value["incidencia"], 2);
                $value["imposto"] = round($value["imposto"], 2);
                $value["liquido"] = round($value["liquido"], 2);

                ImpostoDocumento::create([
                    "documento_id" => $novoDocumento->id,
                    "taxa" => $value["taxa"],
                    "codigo" => $value["codigo"],
                    "isento" => $value["codigo"] === "ISENTO" ? 1 : 0,
                    "motivo_isencao" => $value["motivo_isencao"],
                    "incidencia" => $value["incidencia"],
                    "imposto" => $value["imposto"],
                    //'liquido' => $value['liquido'],
                    "total" => $value["incidencia"] + $value["imposto"],
                ]);
            }

            // 6️⃣ Copiar itens
            $itens = [];
            foreach ($request["itens"] as $item) {
                $idImpostoTaxa = $item["iva_percent"];
                $taxaIva = TipoTaxaIva::find($idImpostoTaxa)->taxa;
                $codigoIva = TipoTaxaIva::find($idImpostoTaxa)->codigo;
                //$motivoIsencaoCodigo = null;
                $motivoIsencaoDescricao = null;

                if ($codigoIva === "ISENTO") {
                    $motivo = DB::table("motivo_isencao")
                        ->where("id", $item["motivo_isencao_id"]) // <-- aqui
                        ->first();
                    if ($motivo) {
                        $codigoIva = $motivo->codigo;
                        $motivoIsencaoDescricao = $motivo->motivo;
                    }
                }

                $desconto = 0;
                if (
                    isset($item["desconto_percent"]) &&
                    $item["desconto_percent"] > 0
                ) {
                    $desconto =
                        $item["preco_venda"] *
                        ($item["desconto_percent"] / 100);
                } elseif (
                    isset($item["desconto_fixo"]) &&
                    $item["desconto_fixo"] > 0
                ) {
                    $desconto = $item["desconto_fixo"];
                }

                // Calcula o total do item (sem IVA)
                $totalSemDesconto = $item["preco_venda"] * $item["quantidade"];
                $totalItem = $totalSemDesconto - $desconto;

                $itens[] = [
                    "documento_id" => $novoDocumento->id,
                    "produto_nome" => $item["produto_nome"],
                    "produto_codigo" => $item["codigo_produto"],
                    "preco_unitario" => $item["preco_venda"],
                    "descricao" => $item["descricao"],
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => $item["desconto_percent"],
                    "desconto_fixo" => $item["desconto_fixo"],
                    "iva_percent" => $taxaIva ?? 0,
                    "imposto_taxa_id" => $idImpostoTaxa,
                    "codigo_iva" => $codigoIva ?? "",
                    "tipo_id" => $item["tipo_id"],
                    "motivo_isencao" => $motivoIsencaoDescricao,
                    "total_sem_desconto" => $totalSemDesconto,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $novoDocumento->itens()->createMany($itens);

            // 7️⃣ Copiar meios de pagamento (se quiser manter)
            // if ($documentoOrigem->meiosPagamento) {
            //     foreach ($documentoOrigem->meiosPagamento as $mp) {
            //         MeioPagamentoDocumento::create([
            //             "documento_id" => $novoDocumento->id,
            //             "descricao" => $mp->descricao,
            //             "valor" => $mp->valor,
            //         ]);
            //     }
            // }

            // 8️⃣ Marcar documento de origem como transformado
            $documentoOrigem->update(["estado_documento" => "transformado"]);

            //Atualizar hash
            $hash = $this->calcularHash($novoDocumento->id);
            $novoDocumento->update(['hash' => $hash]);

            DB::commit();

            return response()->json([
                "message" => "Documento {$documentoOrigem->tipo_sigla} transformado em {$tipoDestino} com sucesso.",
                "documento_origem" => $documentoOrigem,
                "documento_novo" => $novoDocumento->load([
                    "itens",
                    "impostosDocumento",
                ]),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Erro ao transformar documento.",
                    "error" => $th->getMessage(),
                ],
                500,
            );
        }
    }

    public function storeNotaCredito(Request $request)
    {
        // VALIDAÇÃO: Verificar se a fatura já possui nota de crédito
        $faturaId = $request->input("documento_id");

        // Buscar a fatura original
        $faturaOriginal = Documento::find($faturaId);

        if (!$faturaOriginal) {
            return response()->json([
                'message' => 'Fatura não encontrada.',
                'error' => 'INVOICE_NOT_FOUND'
            ], 404);
        }

        // Verificar se a fatura já tem uma nota de crédito associada
        $notaCreditoExistente = DB::table("documento_relacoes")
            ->where("documento_relacionado_id", $faturaId)
            ->where("tipo_relacao", "NOTA_DE_CREDITO_FATURA")
            ->exists();

        if ($notaCreditoExistente) {
            return response()->json([
                'message' => 'Não é permitido criar múltiplas notas de crédito para a mesma fatura.',
                'error' => 'DUPLICATE_CREDIT_NOTE',
                'fatura_id' => $faturaId,
                'fatura_numero' => $faturaOriginal->num_fatura
            ], 422);
        }

        // Verificar se a fatura está elegível para nota de crédito (opcional)
        if ($faturaOriginal->estado === 'cancelada') {
            return response()->json([
                'message' => 'Não é possível criar nota de crédito para uma fatura cancelada.',
                'error' => 'CANCELLED_INVOICE'
            ], 422);
        }

        // Se a fatura já tiver sido totalmente creditada (opcional - baseado no valor)
        $totalCreditado = DB::table("documento_relacoes")
            ->join("documentos", "documento_relacoes.documento_id", "=", "documentos.id")
            ->where("documento_relacoes.documento_relacionado_id", $faturaId)
            ->where("documento_relacoes.tipo_relacao", "NOTA_DE_CREDITO_FATURA")
            ->where("documentos.estado", "emitido")
            ->sum("documentos.total_geral");

        if ($totalCreditado >= $faturaOriginal->total_geral) {
            return response()->json([
                'message' => 'Esta fatura já foi totalmente creditada. Não é possível criar nova nota de crédito.',
                'error' => 'FULLY_CREDITED_INVOICE',
                'valor_fatura' => $faturaOriginal->total_geral,
                'valor_creditado' => $totalCreditado
            ], 422);
        }

        // Gerar número do documento
        $numFatura = $this->gerarNumeroDocumento(
            "NC",
            $request->input("empresa_id"),
        );

        // Validação dos dados recebidos
        $validated = Validator::make(
            $request->all(),
            [
                // Dados do documento
                "tipo_fatura" => "nullable|string",
                "sigla_fatura" => "nullable|string",
                "tipo_cor" => "nullable|string",

                "documento_id" => "required|integer",

                "data_emissao" => "required|date",

                "desconto_total" => "nullable|numeric",
                "total_sem_desconto" => "nullable|numeric",
                "total_impostos" => "nullable|numeric",
                "total_geral" => "nullable|numeric",

                "motivo_emissao" => "required|string", // Corrigido: required!string para required|string

                "meiosPagamento" => "required|array",
                "meiosPagamento.*.descricao" => "required|string",
                "meiosPagamento.*.valor" => "required|numeric",

                // Itens do documento
                "itens" => "required|array|min:1",
                "itens.*.produto_nome" => "required|string",
                "itens.*.codigo_produto" => "required|string",
                "itens.*.preco_venda" => "required|numeric",
                "itens.*.descricao" => "nullable|string",
                "itens.*.quantidade" => "required|integer",
                "itens.*.desconto_percent" => "required|numeric",
                "itens.*.desconto_fixo" => "required|numeric",
                "itens.*.imposto_taxa_id" => "required|integer",
                "itens.*.iva_percent" => "nullable|numeric",

                "utilizador_id" => "required|integer",
                "utilizador" => "required|string",
            ],
            [
                // Mensagens personalizadas de validação
                "required" => "O campo :attribute é obrigatório.",
                "string" => "O campo :attribute deve ser uma string.",
                "integer" => "O campo :attribute deve ser um número inteiro.",
                "numeric" => "O campo :attribute deve ser um número.",
                "email" => "O campo :attribute deve ser um email válido.",
                "date" => "O campo :attribute deve ser uma data válida.",
                "array" => "O campo :attribute deve ser uma lista.",
                "min" => [
                    "array" => "O campo :attribute deve ter pelo menos :min item(ns).",
                ],
            ],
        );

        if ($validated->fails()) {
            return response()->json(
                [
                    "message" => "Erro de validação.",
                    "errors" => $validated->errors(),
                ],
                422,
            );
        }

        // Restante do seu código permanece igual...
        // (Construção do quadro por taxas, cálculo de descontos, etc.)

        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item["imposto_taxa_id"]);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item["motivo_isencao_id"] ?? "";
            $motivo = "";

            if ($codigo === "ISENTO" && $motivoIsencaoId) {
                $motivo = DB::table("motivo_isencao")
                    ->where("id", $motivoIsencaoId)
                    ->value("motivo");
            }

            $subtotalBruto = $item["preco_venda"] * $item["quantidade"];

            $desconto = 0;
            if (
                isset($item["desconto_percent"]) &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $subtotalBruto * ($item["desconto_percent"] / 100);
            } elseif (
                isset($item["desconto_fixo"]) &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + $taxaIva / 100), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . "|" . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    "taxa" => $taxaIva,
                    "codigo" => $codigo,
                    "motivo_isencao" => $motivo,
                    "incidencia" => 0.0,
                    "imposto" => 0.0,
                    "liquido" => 0.0,
                ];
            }

            $quadroImposto[$chave]["incidencia"] += $base;
            $quadroImposto[$chave]["imposto"] += $imposto;
            $quadroImposto[$chave]["liquido"] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;

        foreach ($request->itens as $item) {
            $precoBruto = $item["preco_venda"] * $item["quantidade"];

            $desconto = 0;
            if (
                $item["desconto_percent"] !== null &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $precoBruto * ($item["desconto_percent"] / 100);
            } elseif (
                $item["desconto_fixo"] !== null &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"] * $item["quantidade"];
            }

            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        $descontoGeral = 0;

        if ($request["desconto_tipo"] === "percentual") {
            $descontoGeral =
                $totalSemDesconto * ($request["desconto_total"] / 100);
        } elseif ($request["desconto_tipo"] === "fixo") {
            $descontoGeral = $request["desconto_total"];
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                $proporcao = $linha["liquido"] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                $linha["liquido"] = round(
                    $linha["liquido"] - $descontoLinha,
                    2,
                );

                $linha["incidencia"] = round(
                    $linha["liquido"] / (1 + $linha["taxa"] / 100),
                    2,
                );
                $linha["imposto"] = round(
                    $linha["liquido"] - $linha["incidencia"],
                    2,
                );

                unset($linha);
            }

            $totalLiquido = array_sum(array_column($quadroImposto, "liquido"));
            $totalBase = array_sum(array_column($quadroImposto, "incidencia"));
            $totalImposto = array_sum(array_column($quadroImposto, "imposto"));
        }

        $totalComIvaFinal =
            $totalSemDesconto - $descontoItensTotal - $descontoGeral;

        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, "imposto"));

        $idDocumentoPai = $request["documento_id"];

        $dadosDocPai = Documento::where("id", $idDocumentoPai)->first();

        // Criação do documento
        $documento = Documento::create([
            "tipo_nome" => "Nota de Crédito",
            "tipo_sigla" => "NC",

            "num_fatura" => $numFatura,
            "via" => "original",

            "empresa_id" => $dadosDocPai->empresa_id,
            "empresa_nome" => $dadosDocPai->empresa_nome,
            "empresa_nif" => $dadosDocPai->empresa_nif,
            "empresa_telefone" => $dadosDocPai->empresa_telefone,
            "empresa_email" => $dadosDocPai->empresa_email,
            "empresa_endereco" => $dadosDocPai->empresa_endereco,

            "cliente_id" => $dadosDocPai->cliente_id,
            "cliente_nome" => $dadosDocPai->cliente_nome,
            "cliente_nif" => $dadosDocPai->cliente_nif,
            "cliente_telefone" => $dadosDocPai->cliente_telefone,
            "cliente_email" => $dadosDocPai->cliente_email,
            "cliente_endereco" => $dadosDocPai->cliente_endereco,

            "caixa" => $dadosDocPai->caixa,
            "data_emissao" => $dadosDocPai->data_emissao,
            "data_vencimento" => $dadosDocPai->data_vencimento,
            "forma_pagamento" => $dadosDocPai->forma_pagamento,
            "movimenta_stock" => $dadosDocPai->movimenta_stock,

            "taxa_iva" => "0",
            "valor_iva" => "0",

            "estado" => "emitido",

            "hash" => "aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd",

            "desconto_total" => $descontoItensTotal + $descontoGeral,
            "valor_transporte" => $dadosDocPai->valor_transporte,
            "total_sem_desconto" => $totalSemDesconto,
            "total_impostos" => $totalImpostos,
            "total_geral" => $totalFinal,

            "utilizador_id" => $request["utilizador_id"],
            "utilizador" => $request["utilizador"],
        ]);

        $bancos = Conta::with("banco")
            ->where("empresa_id", $request->input("empresa_id"))
            ->where("estado", true)
            ->get();

        foreach ($bancos as $banco) {
            DB::table("bancos_documento")->insert([
                "documento_id" => $documento->id,
                "sigla" => $banco["banco"]->sigla,
                "descricao" => $banco["banco"]->descricao,
                "numero_conta" => $banco->numero_conta,
                "iban" => $banco->iban,
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }

        foreach ($request->input("meiosPagamento") as $meioPagamento) {
            MeioPagamentoDocumento::create([
                "documento_id" => $documento->id,
                "descricao" => $meioPagamento["descricao"],
                "valor" => $meioPagamento["valor"],
            ]);
        }

        foreach ($quadroImposto as $value) {
            $value["incidencia"] = round($value["incidencia"], 2);
            $value["imposto"] = round($value["imposto"], 2);
            $value["liquido"] = round($value["liquido"], 2);

            ImpostoDocumento::create([
                "documento_id" => $documento->id,
                "taxa" => $value["taxa"],
                "codigo" => $value["codigo"],
                "isento" => $value["codigo"] === "ISENTO" ? 1 : 0,
                "motivo_isencao" => $value["motivo_isencao"],
                "incidencia" => $value["incidencia"],
                "imposto" => $value["imposto"],
                "total" => $value["incidencia"] + $value["imposto"],
            ]);
        }

        // Criação dos itens
        $itens = [];
        foreach ($request["itens"] as $item) {
            $taxaIva = TipoTaxaIva::find($item["imposto_taxa_id"])->taxa;
            $codigoIva = TipoTaxaIva::find($item["imposto_taxa_id"])->codigo;
            $motivoIsencaoDescricao = null;

            if ($codigoIva === "ISENTO") {
                $motivo = DB::table("motivo_isencao")
                    ->where("id", $item["motivo_isencao_id"])
                    ->first();
                if ($motivo) {
                    $codigoIva = $motivo->codigo;
                    $motivoIsencaoDescricao = $motivo->motivo;
                }
            }

            $desconto = 0;
            if (
                isset($item["desconto_percent"]) &&
                $item["desconto_percent"] > 0
            ) {
                $desconto =
                    $item["preco_venda"] * ($item["desconto_percent"] / 100);
            } elseif (
                isset($item["desconto_fixo"]) &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"];
            }

            $totalSemDesconto = $item["preco_venda"] * $item["quantidade"];
            $totalItem = $totalSemDesconto - $desconto;

            $itens[] = [
                "documento_id" => $documento->id,
                "produto_nome" => $item["produto_nome"],
                "produto_codigo" => $item["codigo_produto"],
                "preco_unitario" => $item["preco_venda"],
                "descricao" => $item["descricao"] ?? "",
                "quantidade" => $item["quantidade"],
                "desconto_percent" => $item["desconto_percent"],
                "desconto_fixo" => $item["desconto_fixo"],
                "imposto_taxa_id" => $item["imposto_taxa_id"],
                "iva_percent" => $taxaIva ?? 0,
                "codigo_iva" => $codigoIva ?? "",
                "motivo_isencao" => $motivoIsencaoDescricao,
                "motivo_isencao_id" => $item["motivo_isencao_id"] ?? null,
                "total_sem_desconto" => $totalSemDesconto,
                "total" => $totalItem,
            ];
        }

        $documento->itens()->createMany($itens);

        // Criar relação Nota de credito -> fatura
        DB::table("documento_relacoes")->insert([
            "documento_id" => $documento->id,
            "documento_relacionado_id" => $request["documento_id"],
            "tipo_relacao" => "NOTA_DE_CREDITO_FATURA",
            "created_at" => now(),
            "updated_at" => now(),
        ]);

        // Criar Recibo
        $request->merge([
            "tipo_nome" => "Recibo",
            "tipo_sigla" => "RC",
            "total_geral" => $totalFinal,
            "documento_relacionado_id" => $documento->id,
        ]);

        $data = [
            "tipo_fatura" => "Recibo",
            "sigla_fatura" => "RC",
            "total_geral" => $totalFinal,
            "documento_relacionado_id" => $documento->id,
            "empresa_id" => $documento->empresa_id,
            "empresa_nome" => $documento->empresa_nome,
            "empresa_nif" => $documento->empresa_nif,
            "cliente_id" => $documento->cliente_id,
            "cliente_nome" => $documento->cliente_nome,
            "cliente_nif" => $documento->cliente_nif,
            "meiosPagamento" => $request->input("meiosPagamento"),
            "utilizador_id" => $request->input("utilizador_id"),
            "utilizador" => $request->input("utilizador"),
            "caixa" => $documento->caixa,
            "data_emissao" => $documento->data_emissao,
            "data_vencimento" => $documento->data_vencimento,
        ];

        $recibo = $this->storeRecibo(
            new Request($data),
            "RECIBO_NOTA_DE_CREDITO",
        );

        return response()->json(
            [
                "message" => "Nota de Crédito e Recibo criados com sucesso.",
                "documento" => $documento->load("itens"),
                "documento_recibo" => $recibo->documento ?? "",
            ],
            201,
        );
    }

    public function storeRecibo(
        Request $request,
        $tipoRelacao = "RECIBO_FATURA",
    ) {
        //dd($request->all());
        $validated = Validator::make($request->all(), [
            "tipo_fatura" => "required|string", // "RECIBO"
            "sigla_fatura" => "required|string", // "RC"
            "data_emissao" => "required|date",
            "total_geral" => "required|numeric",
            "meiosPagamento" => "required|array|min:1",
            "meiosPagamento.*.descricao" => "required|string",
            "meiosPagamento.*.valor" => "required|numeric",
            "documento_relacionado_id" =>
            "required|integer|exists:documentos,id", // fatura associada
            "utilizador_id" => "required|integer",
            "utilizador" => "required|string",
        ]);

        if ($validated->fails()) {
            return response()->json(
                [
                    "message" => "Erro de validação.",
                    "errors" => $validated->errors(),
                ],
                422,
            );
        }

        // Gerar número do recibo
        $numRecibo = $this->gerarNumeroDocumento(
            $request->sigla_fatura,
            $request->empresa_id,
        );

        $totalEntregue = 0;

        foreach ($request->input("meiosPagamento") as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento["valor"];
        }

        $troco = $totalEntregue - $request->total_geral;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }

        // Criar recibo
        $documento = Documento::create([
            "tipo_nome" => $request->tipo_fatura,
            "tipo_sigla" => $request->sigla_fatura,
            "num_fatura" => $numRecibo,
            "via" => "Original",
            "empresa_id" => $request->empresa_id,
            "empresa_nome" => $request->empresa_nome,
            "empresa_nif" => $request->empresa_nif,
            "cliente_id" => $request->cliente_id,
            "cliente_nome" => $request->cliente_nome,
            "cliente_nif" => $request->cliente_nif,
            "caixa" => $request->caixa ?? "CAIXA PRINCIPAL",
            "data_emissao" => $request->data_emissao,
            "movimenta_stock" => false,
            "total_geral" => $request->total_geral,
            "troco" => $troco,
            "estado" => "emitido",
            "hash" => "rfsuhihuhuycgygyfyukgeyggfavdyvd",
            "utilizador_id" => $request->utilizador_id,
            "utilizador" => $request->utilizador,
        ]);

        // Criar relação recibo
        DB::table("documento_relacoes")->insert([
            "documento_id" => $documento->id,
            "documento_relacionado_id" => $request->documento_relacionado_id,
            "tipo_relacao" => $tipoRelacao,
            "created_at" => now(),
            "updated_at" => now(),
        ]);

        // Meios de pagamento
        foreach ($request->meiosPagamento as $meio) {
            MeioPagamentoDocumento::create([
                "documento_id" => $documento->id,
                "descricao" => $meio["descricao"],
                "valor" => $meio["valor"],
            ]);
        }

        return response()->json(
            [
                "message" => "Recibo criado com sucesso.",
                "documento" => $documento,
            ],
            201,
        );
    }

    public function gerarNumeroDocumento(
        string $tipoSigla,
        string $empresId,
    ): string {
        $ano = Carbon::now()->year;

        $empresa = DB::table("empresas")->find($empresId);

        // Conta quantos documentos desse tipo e ano já existem
        $contador = DB::table("documentos")
            ->where("tipo_sigla", $tipoSigla) // campo tipo como 'FR', por exemplo
            ->where("empresa_id", $empresId) // campo empresa_id
            ->whereYear("created_at", $ano)
            ->count();

        $sequencial = $contador + 1;

        // Formato final: FR T11P2025/2
        return "{$tipoSigla} {$empresa->indicativo_fatura}{$ano}/{$sequencial}";
    }

    public function gerarPdf(string $id, Request $request)
    {
        $documento = Documento::with([
            "documentosRelacionados",
            "relacionadoEm",
            "itens",
        ])->find($id);

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(
                ["message" => "Documento não encontrado."],
                404,
            );
        }

        $empresaId = $documento->empresa_id;

        $bancos = BancoDocumento::where("documento_id", $id)->get();

        $infoGuia = InfoGuia::where("id", $documento->info_guia_id)->first();

        if ($infoGuia) {
            $infoGuia->data_origem = Carbon::parse(
                $infoGuia->data_origem,
            )->format("Y-m-d - H:i");
            $infoGuia->data_destino = Carbon::parse(
                $infoGuia->data_destino,
            )->format("Y-m-d - H:i");
        }

        $meiosPagamento = MeioPagamentoDocumento::where(
            "documento_id",
            $id,
        )->get();

        $quadroImposto = ImpostoDocumento::where("documento_id", $id)->get();

        $quadroImpostoAgrupado = [];

        foreach ($quadroImposto as $linha) {
            $taxa = $linha["taxa"];
            if (!isset($quadroImpostoAgrupado[$taxa])) {
                $quadroImpostoAgrupado[$taxa] = [
                    "taxa" => $taxa,
                    "codigo" => $linha["codigo"],
                    "incidencia" => 0,
                    "imposto" => 0,
                    "motivos" => [],
                ];
            }

            $quadroImpostoAgrupado[$taxa]["incidencia"] += $linha["incidencia"];
            $quadroImpostoAgrupado[$taxa]["imposto"] += $linha["imposto"];

            if (
                !empty($linha["motivo_isencao"]) &&
                !in_array(
                    $linha["motivo_isencao"],
                    $quadroImpostoAgrupado[$taxa]["motivos"],
                )
            ) {
                $quadroImpostoAgrupado[$taxa]["motivos"][] =
                    $linha["motivo_isencao"];
            }
        }

        // Depois pode juntar motivos numa string
        foreach ($quadroImpostoAgrupado as &$linha) {
            $linha["motivos"] = implode("; ", $linha["motivos"]);
        }
        unset($linha);

        usort($quadroImpostoAgrupado, function ($a, $b) {
            //return (float)$a['taxa'] <=> (float)$b['taxa']; // crescente
            return (float) $b["taxa"] <=> (float) $a["taxa"]; // decrescente
        });

        $itens = collect($documento->itens);

        $dadosPersonalizacaoFatura = ConfiguracaoFatura::where("empresa_id", $documento->empresa_id)->first();

        $maxLinhas = 25;
        if ($dadosPersonalizacaoFatura->mostrar_logo && $dadosPersonalizacaoFatura->logo) {
            $maxLinhas = 22;
        }
        // número de linhas por página
        $paginas = [];
        $subtotalTransportar = 0;

        foreach ($itens->chunk($maxLinhas) as $chunk) {
            $pagina = [];
            $pagina["itens"] = $chunk;
            $pagina["valor_transportado"] = $subtotalTransportar;

            $subtotalPagina = $chunk->sum("total");
            $subtotalTransportar += $subtotalPagina;

            $pagina["valor_transportar"] = $subtotalTransportar;

            $paginas[] = $pagina;
        }

        $imagePath = storage_path('app/public/logos-fatura/' . $dadosPersonalizacaoFatura->logo);
        $imageData = base64_encode(file_get_contents($imagePath));
        $src = 'data:image/png;base64,' . $imageData;

        $configFat = ConfiguracaoFatura::where('empresa_id', $empresaId)->first();
        $numVias = $configFat->num_via;

        $viasBase = ['Original', 'Duplicado', 'Triplicado', 'Quadruplicado', 'Quintuplicado'];
        $vias = [];

        if ($documento->vezes_impresso == 0) {
            $vias = array_slice($viasBase, 0, $numVias);
        } else {
            $vias = ['Duplicado'];
        }
        // Incrementa sempre
        $documento->vezes_impresso += 1;
        $documento->save();

        $totalPaginasPorVia = count($paginas);

        $qrString = "A:" . $documento->empresa_nif .
            "*B:" . $documento->cliente_nif .
            "*C:AO" .
            "*D:" . $documento->tipo_sigla .
            "*F:" . $documento->num_fatura .
            "*G:" . $documento->data_emissao .
            "*H:" . $documento->total_geral;

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrString)
            ->size(100)
            ->margin(2)
            ->build();

        $qrCode = base64_encode($result->getString());

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $template = ConfiguracaoFatura::where("empresa_id", $documento->empresa_id)->value("template");

        $templateView = $template === "classic" ? "pdf.documento-classic" : "pdf.documento-modern";

        $html = view(
            $templateView,
            compact([
                "documento",
                "paginas",
                "quadroImpostoAgrupado",
                "bancos",
                "meiosPagamento",
                "infoGuia",
                "src",
                "dadosPersonalizacaoFatura",
                "vias",
                "totalPaginasPorVia",
                "qrCode"
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        $filename = str_replace([" ", "/"], "_", $documento["num_fatura"]);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function gerarPdfFaturaCompra(string $id)
    {
        $documento = DocumentoCompra::with([
            "itens",
            "otherItens",
            "impostosDocumento",
            "pagamentos"
        ])->find($id);

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(
                ["message" => "Documento não encontrado."],
                404,
            );
        }

        $meiosPagamento = PagamentoDocumentoCompra::where(
            "documento_compra_id",
            $id,
        )->get();

        $quadroImposto = ImpostoDocumentoCompra::where("documento_compra_id", $id)->get();

        $quadroImpostoAgrupado = [];

        foreach ($quadroImposto as $linha) {
            $taxa = $linha["taxa"];
            if (!isset($quadroImpostoAgrupado[$taxa])) {
                $quadroImpostoAgrupado[$taxa] = [
                    "taxa" => $taxa,
                    "codigo" => $linha["codigo"],
                    "incidencia" => 0,
                    "imposto" => 0,
                    "motivos" => [],
                ];
            }

            $quadroImpostoAgrupado[$taxa]["incidencia"] += $linha["incidencia"];
            $quadroImpostoAgrupado[$taxa]["imposto"] += $linha["imposto"];

            if (
                !empty($linha["motivo_isencao"]) &&
                !in_array(
                    $linha["motivo_isencao"],
                    $quadroImpostoAgrupado[$taxa]["motivos"],
                )
            ) {
                $quadroImpostoAgrupado[$taxa]["motivos"][] =
                    $linha["motivo_isencao"];
            }
        }

        // Depois pode juntar motivos numa string
        foreach ($quadroImpostoAgrupado as &$linha) {
            $linha["motivos"] = implode("; ", $linha["motivos"]);
        }
        unset($linha);

        usort($quadroImpostoAgrupado, function ($a, $b) {
            //return (float)$a['taxa'] <=> (float)$b['taxa']; // crescente
            return (float) $b["taxa"] <=> (float) $a["taxa"]; // decrescente
        });

        //  return $quadroImpostoAgrupado;

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.documento-compra",
            compact([
                "documento",
                "quadroImpostoAgrupado",
                "meiosPagamento",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            // $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            // $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = str_replace([" ", "/"], "_", $documento["num_fatura"]);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function gerarPdfRecibo(string $id)
    {
        $documento = Documento::with([
            "documentosRelacionados",
            "relacionadoEm",
        ])->find($id);

        $docRelacionado = $documento->relacionadoEm->first();

        $pagamentos = MeioPagamentoDocumento::where(
            "documento_id",
            $id,
        )->first();

        $valorPago = $pagamentos->valor;

        // Verifica se o documento foi encontrado
        if (!$documento) {
            return response()->json(
                ["message" => "Documento de recibo não encontrado."],
                404,
            );
        }

        $bancos = BancoDocumento::where("documento_id", $id)->get();

        $meiosPagamento = MeioPagamentoDocumento::where(
            "documento_id",
            $id,
        )->get();

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.recibo",
            compact([
                "documento",
                "docRelacionado",
                "bancos",
                "meiosPagamento",
                "valorPago",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = str_replace([" ", "/"], "_", $documento["num_fatura"]);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function pdfRelatorioDocumento(Request $request)
    {
        $clienteId = $request->query("cliente_id");
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $documentoQuery = Documento::query();

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        } else {
            $documentoQuery->whereIn("tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 👤 Filtrar por cliente
        if ($clienteId) {
            $documentoQuery->where("cliente_id", $clienteId);
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        $documentos = $documentoQuery
            ->with([
                "itens",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->get();

        //return response()->json($documentos);

        $totalGeral = $documentos->sum("total_geral");

        $dadosEmpresa = [
            "nome" => "Softseven",
            "endereco" => "Luanda, Camama",
            "nif" => "999999999",
            "telefone" => "941608052",
            "email" => " geral@sofyseven.ao",
        ];

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.relatorio-documento",
            compact([
                "documentos",
                "dataInicial",
                "dataFinal",
                "totalGeral",
                "dadosEmpresa",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = "relatorio"; //str_replace([' ', '/'], '_', $documento['num_fatura']);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function listFaturacaoPorItem2(Request $request)
    {
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        } else {
            $documentoQuery->whereIn("tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        $documentos = $documentoQuery
            ->with([
                "itens",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where('empresa_id', $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->get();

        // após obter $documentos...
        $itensAgrupadosArr = []; // array simples para agregar

        foreach ($documentos as $doc) {
            foreach ($doc->itens as $item) {
                // Ajusta os nomes dos campos conforme o teu modelo:
                $codigo =
                    $item->codigo ??
                    ($item->produto_codigo ?? $item->produto_id);
                $descricao = $item->descricao ?? ($item->produto_nome ?? "");

                // Valores por linha (adapta se teu modelo usa nomes diferentes)
                $valorLinha = (float) ($item->total_sem_desconto ?? 0); // total sem imposto da linha
                $impostoLinha =
                    (float) ($item->total_impostos ?? ($item->imposto ?? 0));
                $quantidadeLinha =
                    (float) ($item->quantidade ?? ($item->qty ?? 0));
                $totalLinha = (float) ($item->total ?? 0);

                if (!isset($itensAgrupadosArr[$codigo])) {
                    $itensAgrupadosArr[$codigo] = [
                        "codigo" => $codigo,
                        "descricao" => $descricao,
                        "quantidade" => 0.0,
                        "valor" => 0.0, // soma dos valores sem imposto
                        "imposto" => 0.0, // soma dos impostos
                        "total" => 0.0,
                    ];
                }

                // Agora sim, modificamos o array (sem problema)
                $itensAgrupadosArr[$codigo]["quantidade"] += $quantidadeLinha;
                $itensAgrupadosArr[$codigo]["valor"] += $valorLinha;
                $itensAgrupadosArr[$codigo]["imposto"] += $impostoLinha;
                $itensAgrupadosArr[$codigo]["total"] += $totalLinha;
            }
        }

        $itensAgrupados = DB::table("itens_documento")
            ->select(
                "produto_codigo as codigo",
                DB::raw("SUM(quantidade) as quantidade"),
                DB::raw("SUM(total_sem_desconto) as valor"),
                DB::raw("SUM(total_impostos) as imposto"),
                DB::raw("SUM(total) as total"),
            )
            ->join(
                "documentos",
                "documentos.id",
                "=",
                "itens_documento.documento_id",
            )
            ->whereBetween("documentos.data_emissao", [
                $dataInicial,
                $dataFinal,
            ])
            ->groupBy("produto_codigo")
            ->paginate(10);

        // Converter para Collection (opcional) e recalcular totais
        $itensAgrupados = collect(array_values($itensAgrupadosArr)); // reindexa numericamente

        $totalQuantidade = $itensAgrupados->sum("quantidade");
        $totalValor = $itensAgrupados->sum("valor");
        $totalImposto = $itensAgrupados->sum("imposto");
        $totalGeral = $totalValor + $totalImposto;

        return response()->json([
            "itens" => $itensAgrupados,
            "totalQtd" => $totalQuantidade,
            "totalVavlor" => $totalValor,
            "totalImposto" => $totalImposto,
            "totalGeral" => $totalGeral,
        ]);
    }

    public function listContaCorrenteCliente(Request $request, $clienteId)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $perPage = $request->query("per_page", 10);

        $idEmpresa = $request->input('empresa_id');

        // ⚙️ Base da query
        $query = DB::table("documentos")
            ->where('empresa_id', $idEmpresa)
            ->where("cliente_id", $clienteId)
            ->whereIn("tipo_sigla", [
                "FT",
                "FA",
                "FG",
                "FR",
                "NC",
                "ND",
                "RC",
                "RG",
            ]) // Adiciona os tipos relevantes
            ->select([
                "id",
                "tipo_nome",
                "tipo_sigla",
                "num_fatura",
                "data_emissao",
                "total_geral",
                "estado",
                // Campos calculados
                DB::raw("
                CASE
                    WHEN tipo_sigla IN ('FR','FT','FA','FG','ND') THEN total_geral
                    ELSE 0
                END AS debito
            "),
                DB::raw("
                CASE
                    WHEN tipo_sigla IN ('NC','RC','RG') THEN total_geral
                    ELSE 0
                END AS credito
            "),
            ]);

        // 📅 Filtro por datas
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("data_emissao", "<=", $dataFinal);
        }

        $query->orderBy("data_emissao", "asc"); // ordem cronológica

        // 🧾 Busca os documentos (paginação)
        $documentos = $query->paginate($perPage);

        // 💰 Calcular saldo acumulado
        $saldo = 0;
        $movimentos = [];

        // return $documentos;

        foreach ($documentos->items() as $doc) {
            $saldo += $doc->debito - $doc->credito;

            $movimentos[] = [
                "data" => date("d M Y", strtotime($doc->data_emissao)),
                "documento" => $doc->num_fatura,
                "debito" => (float) $doc->debito,
                "credito" => (float) $doc->credito,
                "saldo" => (float) $saldo,
            ];
        }

        // 🔢 Totais gerais (sem paginação)
        $totaisQuery = DB::table("documentos")
            ->where('empresa_id', $idEmpresa)
            ->where("cliente_id", $clienteId)
            ->whereIn("tipo_sigla", ["FT", "FA", "FG", "FR", "NC", "ND", "RC"])
            ->select([
                DB::raw("
                SUM(CASE WHEN tipo_sigla IN ('FR','FT','FA','FG','ND') THEN total_geral ELSE 0 END) AS total_debito
            "),
                DB::raw("
                SUM(CASE WHEN tipo_sigla IN ('NC','RC') THEN total_geral ELSE 0 END) AS total_credito
            "),
            ]);

        if ($dataInicial && $dataFinal) {
            $totaisQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $totaisQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $totaisQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        $totais = $totaisQuery->first();

        $saldoFinal =
            ($totais->total_debito ?? 0) - ($totais->total_credito ?? 0);

        // 📤 Retorno JSON
        return response()->json([
            "data" => $movimentos,
            "current_page" => $documentos->currentPage(),
            "last_page" => $documentos->lastPage(),
            "per_page" => $documentos->perPage(),
            "total" => $documentos->total(),
            "from" => $documentos->firstItem(),
            "to" => $documentos->lastItem(),
            "links" => $documentos->links(),
            "totais" => [
                "total_debito" => (float) ($totais->total_debito ?? 0),
                "total_credito" => (float) ($totais->total_credito ?? 0),
                "saldo_final" => (float) $saldoFinal,
            ],
        ]);
    }

    public function pdfContaCorrenteCliente(Request $request, $clienteId)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        // ⚙️ Base da query
        $query = DB::table("documentos")
            ->where("empresa_id", $idEmpresa)
            ->where("cliente_id", $clienteId)
            ->whereIn("tipo_sigla", [
                "FT",
                "FA",
                "FG",
                "FR",
                "NC",
                "ND",
                "RC",
                "RG",
            ]) // Adiciona os tipos relevantes
            ->select([
                "id",
                "tipo_nome",
                "tipo_sigla",
                "num_fatura",
                "data_emissao",
                "total_geral",
                "estado",
                // Campos calculados
                DB::raw("
                CASE
                    WHEN tipo_sigla IN ('FR','FT','FA','FG','ND') THEN total_geral
                    ELSE 0
                END AS debito
            "),
                DB::raw("
                CASE
                    WHEN tipo_sigla IN ('NC','RC','RG') THEN total_geral
                    ELSE 0
                END AS credito
            "),
            ]);

        // 📅 Filtro por datas
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("data_emissao", "<=", $dataFinal);
        }

        $query->orderBy("data_emissao", "asc"); // ordem cronológica

        // 🧾 Busca os documentos (sem paginação para o PDF)
        $documentos = $query->get();

        // 💰 Calcular saldo acumulado
        $saldo = 0;
        $movimentos = [];

        foreach ($documentos as $doc) {
            $saldo += $doc->debito - $doc->credito;

            $movimentos[] = [
                "data" => date("d M Y", strtotime($doc->data_emissao)),
                "documento" => $doc->num_fatura,
                "debito" => (float) $doc->debito,
                "credito" => (float) $doc->credito,
                "saldo" => (float) $saldo,
            ];
        }
        // 🔢 Totais gerais
        $totaisQuery = DB::table("documentos")
            ->where("empresa_id", $idEmpresa)
            ->where("cliente_id", $clienteId)
            ->whereIn("tipo_sigla", ["FT", "FA", "FG", "FR", "NC", "ND", "RC"])
            ->select([
                DB::raw("
                SUM(CASE WHEN tipo_sigla IN ('FR','FT','FA','FG','ND') THEN total_geral ELSE 0 END) AS total_debito
            "),
                DB::raw("
                SUM(CASE WHEN tipo_sigla IN ('NC','RC') THEN total_geral ELSE 0 END) AS total_credito
            "),
            ]);
        if ($dataInicial && $dataFinal) {
            $totaisQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $totaisQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $totaisQuery->whereDate("data_emissao", "<=", $dataFinal);
        }
        $totais = $totaisQuery->first();
        $saldoFinal =
            ($totais->total_debito ?? 0) - ($totais->total_credito ?? 0);
        $cliente = Cliente::find($clienteId);

        $dadosEmpresa = Empresa::find($idEmpresa);

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);
        $dompdf = new Dompdf($options);
        $html = view(
            "pdf.relatorio-conta-corrente-cliente",
            compact([
                "movimentos",
                "dataInicial",
                "dataFinal",
                "totais",
                "saldoFinal",
                "cliente",
                "dadosEmpresa",
            ]),
        )->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();
        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;
            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;
            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );
            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });
        $filename = "conta_corrente_" . ($cliente->nome ?? "cliente"); //str_replace([' ', '/'], '_', $documento['num_fatura']);
        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function listPagamentosEmFalta(Request $request)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $perPage = $request->query("per_page", 10);
        $hoje = now()->toDateString();

        $idEmpresa = $request->input('empresa_id');

        // Base da query
        $baseQuery = DB::table("documentos as d")
            // 👇 Liga faturas aos recibos que as pagaram
            ->leftJoin(
                "documento_relacoes as dr",
                "dr.documento_relacionado_id",
                "=",
                "d.id",
            )
            // 👇 Join dos meios de pagamento dos recibos
            ->leftJoin(
                "meios_pagamento_documento as mp",
                "mp.documento_id",
                "=",
                "dr.documento_id",
            )
            ->select([
                "d.id",
                "d.num_fatura",
                "d.tipo_sigla",
                "d.cliente_id",
                "d.cliente_nome",
                "d.data_emissao",
                "d.data_vencimento",
                "d.total_geral",
                DB::raw("COALESCE(SUM(mp.valor), 0) as total_pago"),
                DB::raw(
                    "(d.total_geral - COALESCE(SUM(mp.valor), 0)) as valor_em_falta",
                ),
            ])
            ->where("d.empresa_id", $idEmpresa)
            // Apenas documentos que geram dívida
            ->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "ND"])
            ->groupBy(
                "d.id",
                "d.num_fatura",
                "d.tipo_sigla",
                "d.cliente_id",
                "d.cliente_nome",
                "d.data_emissao",
                "d.data_vencimento",
                "d.total_geral",
            )
            // Somente documentos com saldo em aberto
            ->havingRaw("(d.total_geral - COALESCE(SUM(mp.valor), 0)) > 0");

        // 📅 Filtro por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $baseQuery->whereBetween("d.data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $baseQuery->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $baseQuery->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        // 🔍 Filtro por cliente (opcional)
        if ($request->filled("cliente_id")) {
            $baseQuery->where("d.cliente_id", $request->cliente_id);
        }

        // 📊 Totais de valores vencidos e não vencidos
        $totais = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery)
            ->selectRaw(
                "
            SUM(CASE WHEN data_vencimento >= ? THEN valor_em_falta ELSE 0 END) as total_nao_vencido,
            SUM(CASE WHEN data_vencimento < ? THEN valor_em_falta ELSE 0 END) as total_vencido,
            SUM(valor_em_falta) as total_geral
        ",
                [$hoje, $hoje],
            )
            ->first();

        // 🔹 Resultados paginados
        $resultados = (clone $baseQuery)
            ->orderBy("d.data_emissao", "asc")
            ->paginate($perPage);

        // Retornar tudo junto
        return response()->json([
            "data" => $resultados->items(),
            "current_page" => $resultados->currentPage(),
            "last_page" => $resultados->lastPage(),
            "per_page" => $resultados->perPage(),
            "total" => $resultados->total(),
            "from" => $resultados->firstItem(),
            "to" => $resultados->lastItem(),
            "links" => $resultados->links(),
            "totais" => $totais,
        ]);
    }

    public function pdfPagamentosEmFalta(Request $request)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $hoje = now()->toDateString();

        $idEmpresa = $request->input('empresa_id');

        // 🔹 Query base (idêntica à da listagem)
        $query = DB::table("documentos as d")
            ->leftJoin(
                "documento_relacoes as dr",
                "dr.documento_relacionado_id",
                "=",
                "d.id",
            )
            ->leftJoin(
                "meios_pagamento_documento as mp",
                "mp.documento_id",
                "=",
                "dr.documento_id",
            )
            ->select([
                "d.id",
                "d.num_fatura",
                "d.tipo_sigla",
                "d.cliente_id",
                "d.cliente_nome",
                "d.data_emissao",
                "d.data_vencimento",
                "d.total_geral",
                DB::raw("COALESCE(SUM(mp.valor), 0) as total_pago"),
                DB::raw(
                    "(d.total_geral - COALESCE(SUM(mp.valor), 0)) as valor_em_falta",
                ),
            ])
            ->where("d.empresa_id", $idEmpresa)
            ->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "ND"])
            ->groupBy(
                "d.id",
                "d.num_fatura",
                "d.tipo_sigla",
                "d.cliente_id",
                "d.cliente_nome",
                "d.data_emissao",
                "d.data_vencimento",
                "d.total_geral",
            )
            ->havingRaw("(d.total_geral - COALESCE(SUM(mp.valor), 0)) > 0")
            ->orderBy("d.data_emissao", "asc");

        // 📅 Filtro de datas
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("d.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        // 🔍 Filtro por cliente (opcional)
        if ($request->filled("cliente_id")) {
            $query->where("d.cliente_id", $request->cliente_id);
        }

        // 🔸 Executa a query
        $resultados = $query->get();

        // 📊 Totais (vencido / não vencido / geral)
        $totais = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->selectRaw(
                "
            SUM(CASE WHEN data_vencimento >= ? THEN valor_em_falta ELSE 0 END) as total_nao_vencido,
            SUM(CASE WHEN data_vencimento < ? THEN valor_em_falta ELSE 0 END) as total_vencido,
            SUM(valor_em_falta) as total_geral
        ",
                [$hoje, $hoje],
            )
            ->first();

        // 🏢 Dados da empresa (podes buscar da tabela empresa se quiser)
        $dadosEmpresa = Empresa::find($idEmpresa);

        // ⚙️ Configuração DomPDF
        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        // 📄 Renderiza o HTML da view
        $html = view(
            "pdf.relatorio-pagamentos-em-falta",
            compact([
                "resultados",
                "dataInicial",
                "dataFinal",
                "dadosEmpresa",
                "totais",
            ]),
        )->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // 🖋️ Rodapé com número de página e assinatura
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf - Processado por programa validado n.º /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;
            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;
            $canvas->line(
                $x,
                $y1 - 5,
                $canvas->get_width() - $x,
                $y1 - 5,
                [0, 0, 0],
                1,
            );
            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        // 📁 Nome dinâmico
        $filename =
            "pagamentos_em_falta_" . now()->format("Y-m-d_His") . ".pdf";

        // 📤 Retorna resposta com PDF inline
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
            ],
        );
    }

    public function listPagamentosEfetuados(Request $request)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $perPage = $request->query("per_page", 10);

        $idEmpresa = $request->input('empresa_id');

        $hoje = now()->toDateString();

        // 🧾 Query principal: Recibos (RG, RC) e os documentos pagos
        $query = DB::table("documentos as rg")
            ->join("documento_relacoes as dr", "dr.documento_id", "=", "rg.id")
            ->join(
                "documentos as ft",
                "ft.id",
                "=",
                "dr.documento_relacionado_id",
            )
            ->leftJoin(
                "meios_pagamento_documento as mp",
                "mp.documento_id",
                "=",
                "rg.id",
            )
            ->select([
                "rg.id",
                "rg.num_fatura as num_recibo",
                "ft.num_fatura as num_fatura",
                "rg.cliente_nome",
                "ft.data_vencimento",
                "rg.data_emissao",
                DB::raw("COALESCE(SUM(mp.valor), 0) as valor_pago"),
            ])
            ->where("rg.empresa_id", $idEmpresa)
            ->whereIn("rg.tipo_sigla", ["RG", "RC"]) // apenas Recibos
            ->where("ft.tipo_sigla", "!=", "NC") // exclui Notas de Crédito
            ->groupBy(
                "rg.id",
                "rg.num_fatura",
                "ft.num_fatura",
                "rg.cliente_nome",
                "ft.data_vencimento",
                "rg.data_emissao",
            )
            ->orderBy("rg.data_emissao", "asc");

        // 📅 Filtros de data (data do recibo)
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("rg.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("rg.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("rg.data_emissao", "<=", $dataFinal);
        }

        // 🔍 Filtro por cliente (opcional)
        if ($request->filled("cliente_id")) {
            $query->where("ft.cliente_id", $request->cliente_id);
        }

        $resultados = $query->paginate($perPage);

        // 🧮 Totais
        $colecao = collect($resultados->items());

        $totalVencidos = $colecao
            ->where("data_vencimento", "<", $hoje)
            ->sum("valor_pago");
        $totalNaoVencidos = $colecao
            ->where("data_vencimento", ">=", $hoje)
            ->sum("valor_pago");
        $totalGeral = $colecao->sum("valor_pago");

        return response()->json(
            [
                "data" => $resultados->items(),
                "current_page" => $resultados->currentPage(),
                "last_page" => $resultados->lastPage(),
                "per_page" => $resultados->perPage(),
                "total" => $resultados->total(),
                "from" => $resultados->firstItem(),
                "to" => $resultados->lastItem(),
                "links" => $resultados->links(),
                "totais" => [
                    "total_vencido" => $totalVencidos,
                    "total_nao_vencido" => $totalNaoVencidos,
                    "total_geral" => $totalGeral,
                ],
            ],
            200,
        );
    }

    public function pdfPagamentosEfetuados(Request $request)
    {
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        // 🧾 Query principal: Recibos (RG) e os documentos pagos
        $query = DB::table("documentos as rg")
            ->join("documento_relacoes as dr", "dr.documento_id", "=", "rg.id")
            ->join(
                "documentos as ft",
                "ft.id",
                "=",
                "dr.documento_relacionado_id",
            )
            ->leftJoin(
                "meios_pagamento_documento as mp",
                "mp.documento_id",
                "=",
                "rg.id",
            )
            ->select([
                "rg.id",
                "rg.num_fatura as num_recibo",
                "ft.num_fatura as num_fatura",
                "rg.cliente_nome",
                "ft.data_vencimento",
                DB::raw("COALESCE(SUM(mp.valor), 0) as valor_pago"),
            ])
            ->where("rg.empresa_id", $idEmpresa)
            ->whereIn("rg.tipo_sigla", ["RG", "RC"]) // apenas Recibos
            ->where("ft.tipo_sigla", "!=", "NC") // exclui Notas de Crédito
            ->groupBy(
                "rg.id",
                "rg.num_fatura",
                "ft.num_fatura",
                "rg.cliente_nome",
                "ft.data_vencimento",
            )
            ->orderBy("rg.data_emissao", "asc");

        // 📅 Filtro de intervalo de datas (data do Recibo)
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("rg.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("rg.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("rg.data_emissao", "<=", $dataFinal);
        }

        // 🔍 Filtro por cliente (opcional)
        if ($request->filled("cliente_id")) {
            $query->where("ft.cliente_id", $request->cliente_id);
        }

        $resultados = $query->get();

        // 🧮 Totais
        $totalGeral = $resultados->sum("valor_pago");

        $dadosEmpresa = Empresa::find($idEmpresa);

        // ⚙️ Configuração do DOMPDF
        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);
        $dompdf = new Dompdf($options);

        $dataAtual = now()->format('d \d\e F \d\e Y');

        $html = view(
            "pdf.relatorio-pagamentos-efetuados",
            compact(
                "resultados",
                "dataInicial",
                "dataFinal",
                "dataAtual",
                "dadosEmpresa",
                "totalGeral",
            ),
        )->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // 🧾 Rodapé (numeração e texto fixo)
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf - Processado por programa validado n.º /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 9;
            $x = 40;
            $y1 = $canvas->get_height() - 45;
            $y2 = $y1 + 10;
            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );
            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = "pagamentos_efetuados";

        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" => "*",
            ],
        );
    }

    public function listFaturacaoPorItem(Request $request)
    {
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $perPage = $request->query("per_page", 10);

        $idEmpresa = $request->input('empresa_id');

        $query = DB::table("itens_documento as di")
            ->join("documentos as d", "di.documento_id", "=", "d.id")
            ->where("d.empresa_id", $idEmpresa)
            ->select([
                "di.produto_codigo as codigo",
                "di.produto_nome as nome",
                DB::raw("SUM(COALESCE(di.quantidade, 0)) as quantidade"),
                DB::raw("SUM(COALESCE(di.total_sem_desconto, 0)) as valor"),
                DB::raw("SUM(COALESCE(di.total, 0)) as total"),
            ]);

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $query->where(function ($q) use ($tipo) {
                    $q->whereIn("d.tipo_sigla", $tipo)->orWhereIn(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $query->where(function ($q) use ($tipo) {
                    $q->where("d.tipo_sigla", $tipo)->orWhere(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            }
        } else {
            $query->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 📅 Filtro por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("d.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        // 🔢 Agrupar por produto
        $query
            ->groupBy("di.produto_codigo", "di.produto_nome")
            ->orderBy("di.produto_nome", "asc");

        // Paginação nativa do SQL
        $itensAgrupados = $query->paginate($perPage);

        // Totais globais (sem paginação)
        $totais = DB::table("itens_documento as di")
            ->join("documentos as d", "di.documento_id", "=", "d.id")
            ->where("d.empresa_id", $idEmpresa)
            ->select([
                DB::raw("SUM(COALESCE(di.quantidade, 0)) as totalQtd"),
                DB::raw(
                    "SUM(COALESCE(di.total_sem_desconto, 0)) as totalValor",
                ),
                // DB::raw('SUM(COALESCE(di.total_impostos, di.imposto, 0)) as totalImposto')
            ]);

        // Reaplicar os mesmos filtros aos totais
        if ($tipo) {
            if (is_array($tipo)) {
                $totais->where(function ($q) use ($tipo) {
                    $q->whereIn("d.tipo_sigla", $tipo)->orWhereIn(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $totais->where(function ($q) use ($tipo) {
                    $q->where("d.tipo_sigla", $tipo)->orWhere(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            }
        } else {
            $totais->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        if ($dataInicial && $dataFinal) {
            $totais->whereBetween("d.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $totais->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $totais->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        $totais = $totais->first();

        $totalGeral = ($totais->totalValor ?? 0) + ($totais->totalImposto ?? 0);

        return response()->json([
            "data" => $itensAgrupados->items(),
            "current_page" => $itensAgrupados->currentPage(),
            "last_page" => $itensAgrupados->lastPage(),
            "per_page" => $itensAgrupados->perPage(),
            "total" => $itensAgrupados->total(),
            "from" => $itensAgrupados->firstItem(),
            "to" => $itensAgrupados->lastItem(),
            "totais" => [
                "totalQtd" => (float) ($totais->totalQtd ?? 0),
                "totalValor" => (float) ($totais->totalValor ?? 0),
                "totalImposto" => (float) ($totais->totalImposto ?? 0),
                "totalGeral" => (float) $totalGeral,
            ],
        ]);
    }

    public function pdfRelatorioFaturacaoPorItem(Request $request)
    {
        $tipo = $request->query("tipo"); // Tipo de documento
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        $documentoQuery = Documento::query();

        // 📄 Filtrar por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("tipo_sigla", $tipo)->orWhereIn(
                        "tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $documentoQuery->where(function ($q) use ($tipo) {
                    $q->where("tipo_sigla", $tipo)->orWhere("tipo_nome", $tipo);
                });
            }
        } else {
            $documentoQuery->whereIn("tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 📅 Filtrar por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $documentoQuery->whereBetween("data_emissao", [
                $dataInicial,
                $dataFinal,
            ]);
        } elseif ($dataInicial) {
            $documentoQuery->whereDate("data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $documentoQuery->whereDate("data_emissao", "<=", $dataFinal);
        }

        $documentos = $documentoQuery
            ->with([
                "itens",
                "impostosDocumento",
                "documentosRelacionados",
                "relacionadoEm",
            ])
            ->where("empresa_id", $idEmpresa)
            ->where('estado_documento', '!=', ['cancelado', 'anulado', 'pendente', 'rascunho'])
            ->orderByDesc("id")
            ->get();

        // após obter $documentos...
        $itensAgrupadosArr = []; // array simples para agregar

        foreach ($documentos as $doc) {
            foreach ($doc->itens as $item) {
                // Ajusta os nomes dos campos conforme o teu modelo:
                $codigo =
                    $item->codigo ??
                    ($item->produto_codigo ?? $item->produto_id);
                $nome = $item->produto_nome ?? "";
                $descricao = $item->descricao ?? "";

                // Valores por linha (adapta se teu modelo usa nomes diferentes)
                $valorLinha = (float) ($item->total_sem_desconto ?? 0); // total sem imposto da linha
                $impostoLinha = (float) ($item->total_impostos ?? 0);
                $quantidadeLinha = (float) ($item->quantidade ?? 0);
                $totalLinha = (float) ($item->total ?? 0);

                if (!isset($itensAgrupadosArr[$codigo])) {
                    $itensAgrupadosArr[$codigo] = [
                        "codigo" => $codigo,
                        "nome" => $nome,
                        "descricao" => $descricao,
                        "quantidade" => 0.0,
                        "valor" => 0.0, // soma dos valores sem imposto
                        "imposto" => 0.0, // soma dos impostos
                        "total" => 0.0,
                    ];
                }

                // Agora sim, modificamos o array (sem problema)
                $itensAgrupadosArr[$codigo]["quantidade"] += $quantidadeLinha;
                $itensAgrupadosArr[$codigo]["valor"] += $valorLinha;
                $itensAgrupadosArr[$codigo]["imposto"] += $impostoLinha;
                $itensAgrupadosArr[$codigo]["total"] += $totalLinha;
            }
        }

        // Converter para Collection (opcional) e recalcular totais
        $itensAgrupados = collect(array_values($itensAgrupadosArr)); // reindexa numericamente

        $totalQuantidade = $itensAgrupados->sum("quantidade");
        $totalValor = $itensAgrupados->sum("valor");
        $totalImposto = $itensAgrupados->sum("imposto");
        $totalGeral = $totalValor + $totalImposto;

        // return response()->json([
        //     'itens' => $itensAgrupados,
        //     'totalQtd' => $totalQuantidade,
        //     'totalValor' => $totalValor,
        //     'totalImposto' => $totalImposto,
        //     'totalGeral' => $totalGeral,
        // ]);

        $dadosEmpresa = Empresa::find($idEmpresa);

        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.relatorio-faturacao-item",
            compact([
                "itensAgrupados",
                "dataInicial",
                "dataFinal",
                "totalGeral",
                "totalQuantidade",
                "totalValor",
                "totalImposto",
                "totalGeral",
                "dadosEmpresa",
            ]),
        )->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Pegamos o canvas atualizado
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        // Aqui aplicamos o script para todas as páginas
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf-Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = "relatorio"; //str_replace([' ', '/'], '_', $documento['num_fatura']);

        //Usa StreamedResponse com o dompdf direto
        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    public function listFaturacaoPorColaborador(Request $request, string $utilizadorId)
    {

        $tipo = $request->query("tipo");
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");
        $perPage = $request->query("per_page", 10);

        $idEmpresa = $request->input('empresa_id');

        $baseQuery = DB::table("documentos as d")
            ->join("utilizadores as u", "d.utilizador_id", "=", "u.id");

        // 🔍 Filtro por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $baseQuery->where(function ($q) use ($tipo) {
                    $q->whereIn("d.tipo_sigla", $tipo)
                        ->orWhereIn("d.tipo_nome", $tipo);
                });
            } else {
                $baseQuery->where(function ($q) use ($tipo) {
                    $q->where("d.tipo_sigla", $tipo)
                        ->orWhere("d.tipo_nome", $tipo);
                });
            }
        } else {
            $baseQuery->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 👨‍💼 Filtro por colaborador
        if ($utilizadorId) {
            $baseQuery->where("u.id", $utilizadorId);
        }

        // 📅 Filtro por datas
        if ($dataInicial && $dataFinal) {
            $baseQuery->whereBetween("d.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $baseQuery->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $baseQuery->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        /*
    |--------------------------------------------------------------------------
    | LISTAGEM (COM SELECT NORMAL)
    |--------------------------------------------------------------------------
    */
        $documentos = (clone $baseQuery)
            ->select([
                "d.id",
                "d.empresa_id",
                "d.tipo_sigla",
                "d.tipo_nome",
                "d.num_fatura",
                "d.data_emissao",
                "d.total_sem_desconto",
                "d.total_geral",
                "d.cliente_nome",
                "u.id as colaborador_id",
                "u.nome_pessoal as colaborador_nome",
            ])
            ->where("d.empresa_id", $idEmpresa)
            ->orderBy("u.nome_pessoal", "asc")
            ->orderBy("d.data_emissao", "desc")
            ->paginate($perPage);

        /*
    |--------------------------------------------------------------------------
    | TOTAIS (APENAS AGREGAÇÕES)
    |--------------------------------------------------------------------------
    */
        $totais = (clone $baseQuery)
            ->selectRaw("
            COUNT(d.id) as totalDocs,
            SUM(COALESCE(d.total_sem_desconto, 0)) as totalSemDesconto,
            SUM(COALESCE(d.total_geral, 0)) as totalFaturado
        ")
            ->where("d.empresa_id", $idEmpresa)
            ->first();

        return response()->json([
            "data" => $documentos->items(),
            "current_page" => $documentos->currentPage(),
            "last_page" => $documentos->lastPage(),
            "per_page" => $documentos->perPage(),
            "total" => $documentos->total(),
            "from" => $documentos->firstItem(),
            "to" => $documentos->lastItem(),
            "totais" => [
                "totalDocs" => (int) ($totais->totalDocs ?? 0),
                "totalSemDesconto" => (float) ($totais->totalSemDesconto ?? 0),
                "totalFaturado" => (float) ($totais->totalFaturado ?? 0),
            ],
        ]);
    }


    public function pdfRelatorioFaturacaoPorColaborador(Request $request)
    {
        $tipo = $request->query("tipo");
        $utilizadorId = $request->query("colaborador_id");
        $dataInicial = $request->query("data_inicial");
        $dataFinal = $request->query("data_final");

        $idEmpresa = $request->input('empresa_id');

        $query = DB::table("documentos as d")
            ->where("d.empresa_id", $idEmpresa)
            ->join("utilizadores as u", "d.utilizador_id", "=", "u.id")
            ->select([
                "d.id",
                "d.tipo_sigla",
                "d.tipo_nome",
                "d.num_fatura",
                "d.data_emissao",
                "d.total_sem_desconto",
                "d.total_geral",
                "u.id as colaborador_id",
                "u.nome_pessoal as colaborador_nome",
            ]);

        // 📄 Filtro por tipo de documento
        if ($tipo) {
            if (is_array($tipo)) {
                $query->where(function ($q) use ($tipo) {
                    $q->whereIn("d.tipo_sigla", $tipo)->orWhereIn(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            } else {
                $query->where(function ($q) use ($tipo) {
                    $q->where("d.tipo_sigla", $tipo)->orWhere(
                        "d.tipo_nome",
                        $tipo,
                    );
                });
            }
        } else {
            $query->whereIn("d.tipo_sigla", ["FT", "FA", "FG", "FR"]);
        }

        // 👨‍💼 Filtro por colaborador
        if ($utilizadorId) {
            $query->where("u.id", $utilizadorId);
        }

        // 📅 Filtro por intervalo de datas
        if ($dataInicial && $dataFinal) {
            $query->whereBetween("d.data_emissao", [$dataInicial, $dataFinal]);
        } elseif ($dataInicial) {
            $query->whereDate("d.data_emissao", ">=", $dataInicial);
        } elseif ($dataFinal) {
            $query->whereDate("d.data_emissao", "<=", $dataFinal);
        }

        // 🔢 Ordenação
        $query
            ->orderBy("u.nome_pessoal", "asc")
            ->orderBy("d.data_emissao", "desc");

        $documentos = $query->get();

        // 📊 Totais gerais
        $totalDocs = $documentos->count();
        $totalSemDesconto = $documentos->sum("total_sem_desconto");
        $totalFaturado = $documentos->sum("total_geral");

        // 📋 Dados da empresa
        $dadosEmpresa = Empresa::find($idEmpresa);

        // 🧾 Agrupar documentos por colaborador
        $documentosPorColaborador = $documentos->groupBy("colaborador_nome");

        // 🖨️ Gerar o PDF
        $options = new Options();
        $options->set("isHtml5ParserEnabled", true);
        $options->set("isRemoteEnabled", true);

        $dompdf = new Dompdf($options);

        $html = view(
            "pdf.relatorio-faturacao-colaborador",
            compact([
                "documentosPorColaborador",
                "dataInicial",
                "dataFinal",
                "totalDocs",
                "totalSemDesconto",
                "totalFaturado",
                "dadosEmpresa",
            ]),
        )->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper("A4", "portrait");
        $dompdf->render();

        // Rodapé com paginação e mensagem legal
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $canvas->page_script(function (
            $pageNumber,
            $pageCount,
            $canvas,
            $fontMetrics,
        ) {
            $text1 = "FzBf - Processado por programa validado n. /AGT/2019";
            $text2 = "Página $pageNumber / $pageCount";
            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $x = 40;
            $y1 = $canvas->get_height() - 50;
            $y2 = $y1 + 12;

            $lineY = $y1 - 5;
            $canvas->line(
                $x,
                $lineY,
                $canvas->get_width() - $x,
                $lineY,
                [0, 0, 0],
                1,
            );

            $canvas->text($x, $y1, $text1, $font, $size);
            $canvas->text($x, $y2, $text2, $font, $size);
        });

        $filename = "relatorio_faturacao_colaborador.pdf";

        return new StreamedResponse(
            function () use ($dompdf, $filename) {
                echo $dompdf->stream($filename, ["Attachment" => false]);
            },
            200,
            [
                "Content-Type" => "application/pdf",
                "Content-Disposition" => 'inline; filename="' . $filename . '"',
                "Access-Control-Allow-Origin" =>
                "https://softseven-faturacao-front.vercel.app",
            ],
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Verifica se o documento existe
        $documento = Documento::find($id);
        if (!$documento) {
            return response()->json(
                ["message" => "Documento não encontrado."],
                404,
            );
        }

        // Return a list of all Caixa records
        $doc = Documento::with([
            "itens.tipoIva",
            "meiosPagamento",
            "impostosDocumento",
            "documentoOrigem",
            "documentosRelacionados",
            "relacionadoEm",
        ])
            ->where("id", $id)
            ->first();

        return response()->json($doc);
    }

    public function anularDocumento(string $id)
    {
        $doc = Documento::findOrFail($id);

        // regra: só pode anular se ainda não tiver nota de crédito associada
        if ($doc->estado !== "emitido") {
            return response()->json(
                ["erro" => "Não pode anular este documento."],
                400,
            );
        }

        $doc->estado = "anulado";
        $doc->save();

        return response()->json([
            "sucesso" => "Documento anulado com sucesso.",
        ]);
    }

    public function finalizarDocRascunho(Request $request, string $id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            return response()->json(
                [
                    "message" => "Document not found!",
                ],
                404,
            );
        }

        $numFatura = $this->gerarNumeroDocumento(
            $request->input("sigla_fatura"),
            $request->input("empresa_id"),
        );

        // Validação dos dados recebidos
        $validated = Validator::make(
            $request->all(),
            [
                // Dados do documento
                "tipo_fatura" => "required|string",
                "sigla_fatura" => "required|string",
                "tipo_cor" => "nullable|string",

                "estado_documento" => "nullable|string",
                "estado_pagamento" => "nullable|string",
                "estado_vencimento" => "nullable|string",

                "empresa_id" => "nullable|integer",
                "empresa_nome" => "required|string",
                "empresa_nif" => "required|integer",
                "empresa_telefone" => "nullable|integer",
                "empresa_email" => "nullable|email",
                "empresa_endereco" => "nullable|string",

                "cliente_id" => "nullable|integer",
                "cliente_nome" => "required|string",
                "cliente_nif" => "required|string",
                "cliente_telefone" => "nullable|string",
                "cliente_email" => "nullable|email",
                "cliente_endereco" => "nullable|string",

                "caixa" => "required|string",
                "data_emissao" => "required|date",
                "data_vencimento" => "required|date",
                "is_apronto" => "nullable|boolean",
                "movimenta_stock" => "required|boolean",

                "taxa_iva" => "nullable|numeric",
                "valor_iva" => "nullable|numeric",

                "desconto_total" => "nullable|numeric",
                "valor_transporte" => "nullable|numeric",
                "total_sem_desconto" => "nullable|numeric",
                "total_impostos" => "nullable|numeric",
                "total_geral" => "nullable|numeric",

                "meiosPagamento" => "nullable|array",
                "meiosPagamento.*.descricao" => "nullable|string",
                "meiosPagamento.*.valor" => "nullable|numeric",

                "marca" => "nullable|string",
                "matricula" => "nullable|string",
                "local_origem" => "nullable|string",
                "local_destino" => "nullable|string",
                "data_origem" => "nullable|date",
                "data_destino" => "nullable|date",

                // Itens do documento
                "itens" => "required|array|min:1",
                "itens.*.produto_nome" => "required|string",
                "itens.*.codigo_produto" => "required|string",
                "itens.*.preco_venda" => "required|numeric",
                "itens.*.descricao" => "nullable|string",
                "itens.*.quantidade" => "required|integer",
                "itens.*.desconto_percent" => "required|numeric",
                "itens.*.desconto_fixo" => "required|numeric",
                "itens.*.iva_percent" => "nullable|numeric",
            ],
            [
                // Mensagens personalizadas de validação
                "required" => "O campo :attribute é obrigatório.",
                "string" => "O campo :attribute deve ser uma string.",
                "integer" => "O campo :attribute deve ser um número inteiro.",
                "numeric" => "O campo :attribute deve ser um número.",
                "email" => "O campo :attribute deve ser um email válido.",
                "date" => "O campo :attribute deve ser uma data válida.",
                "array" => "O campo :attribute deve ser uma lista.",
                "min" => [
                    "array" =>
                    "O campo :attribute deve ter pelo menos :min item(ns).",
                ],
            ],
        );

        if ($validated->fails()) {
            return response()->json(
                [
                    "message" => "Erro de validação.",
                    "errors" => $validated->errors(),
                ],
                422,
            );
        }

        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($request->itens as $item) {
            $tipo = TipoTaxaIva::find($item["iva_percent"]);
            $taxaIva = $tipo->taxa;
            $codigo = $tipo->codigo;
            $motivoIsencaoId = $item["motivo_isencao_id"] ?? "";
            $motivo = "";

            if ($codigo === "ISENTO" && $motivoIsencaoId) {
                $motivo = DB::table("motivo_isencao")
                    ->where("id", $motivoIsencaoId)
                    ->value("motivo");
            }

            $subtotalBruto = $item["preco_venda"] * $item["quantidade"];

            $desconto = 0;
            if (
                isset($item["desconto_percent"]) &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $subtotalBruto * ($item["desconto_percent"] / 100);
            } elseif (
                isset($item["desconto_fixo"]) &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"];
            }

            $subtotalLiquido = $subtotalBruto - $desconto;

            // base e imposto atuais (por item)
            $base = round($subtotalLiquido / (1 + $taxaIva / 100), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . "|" . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    "taxa" => $taxaIva,
                    "codigo" => $codigo,
                    "motivo_isencao" => $motivo,
                    "incidencia" => 0.0, // base
                    "imposto" => 0.0,
                    "liquido" => 0.0, // subtotal (com IVA) do grupo
                ];
            }

            $quadroImposto[$chave]["incidencia"] += $base;
            $quadroImposto[$chave]["imposto"] += $imposto;
            $quadroImposto[$chave]["liquido"] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBruto;
        }

        $totalSemDesconto = 0;
        $descontoItensTotal = 0;
        // 1. Calcular total bruto e descontos por item
        foreach ($request->itens as $item) {
            $precoBruto = $item["preco_venda"] * $item["quantidade"];

            // Desconto do item
            $desconto = 0;
            if (
                $item["desconto_percent"] !== null &&
                $item["desconto_percent"] > 0
            ) {
                $desconto = $precoBruto * ($item["desconto_percent"] / 100);
            } elseif (
                $item["desconto_fixo"] !== null &&
                $item["desconto_fixo"] > 0
            ) {
                $desconto = $item["desconto_fixo"] * $item["quantidade"];
            }

            $subtotalComIva = $precoBruto - $desconto;

            // Acumula totais gerais
            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        // Desconto geral (se existir)
        $descontoGeral = 0; //$request['desconto_total'] ?? 0;

        if ($request["desconto_tipo"] === "percentual") {
            $descontoGeral =
                $totalSemDesconto * ($request["desconto_total"] / 100);
        } elseif ($request["desconto_tipo"] === "fixo") {
            $descontoGeral = $request["desconto_total"]; // decide se é total ou por unidade
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;

            // para garantir soma exata do desconto distribuído (corrige residual de arredond.)
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];

                // proporção segundo o liquido do grupo
                $proporcao = $linha["liquido"] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    // resto do desconto para o último grupo (evita erro de arredondamento)
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                // aplica desconto ao liquido do grupo
                $linha["liquido"] = round(
                    $linha["liquido"] - $descontoLinha,
                    2,
                );

                // recalcula base e imposto segundo a taxa daquele grupo
                $linha["incidencia"] = round(
                    $linha["liquido"] / (1 + $linha["taxa"] / 100),
                    2,
                );
                $linha["imposto"] = round(
                    $linha["liquido"] - $linha["incidencia"],
                    2,
                );

                unset($linha); // bom hábito ao usar referência
            }

            // (opcional) Recalcule totais finais:
            $totalLiquido = array_sum(array_column($quadroImposto, "liquido"));
            $totalBase = array_sum(array_column($quadroImposto, "incidencia"));
            $totalImposto = array_sum(array_column($quadroImposto, "imposto"));
        }

        // 2. Aplicar desconto geral (apenas no final)
        $totalComIvaFinal =
            $totalSemDesconto - $descontoItensTotal - $descontoGeral;

        // 3. Calcular total final (já com todos descontos)
        $totalFinal = $totalComIvaFinal;

        $totalImpostos = array_sum(array_column($quadroImposto, "imposto"));

        $retencao = 0;
        //Calcular retencao na fonte nos servicos
        foreach ($request["itens"] as $item) {
            if (isset($item["tipo_produto"]) && $item["tipo_produto"] === "S") {
                if ($item["preco_venda"] > 20000) {
                    $retencao += $item["preco_venda"] * 0.06; // 5% de retenção
                }
            }
        }

        $totalEntregue = 0;

        foreach ($request->input("meiosPagamento") as $meioPagamento) {
            $totalEntregue += (float) $meioPagamento["valor"];
        }

        $troco = $totalEntregue - $totalFinal;

        // garantir que não dá troco negativo
        if ($troco < 0) {
            $troco = 0;
        }

        try {
            DB::beginTransaction();

            $infoGuiaId = null;

            if (in_array($request["sigla_fatura"], ["GT", "GR"])) {
                // Se já existe um info_guia associado, atualiza
                if ($documento->guia_info_id) {
                    DB::table("info_guias")
                        ->where("id", $documento->guia_info_id)
                        ->update([
                            "marca" => $request->input("marca"),
                            "matricula" => $request->input("matricula"),
                            "local_origem" => $request->input("local_origem"),
                            "local_destino" => $request->input("local_destino"),
                            "data_origem" => $request->input("data_origem"),
                            "data_destino" => $request->input("data_destino"),
                            "updated_at" => now(),
                        ]);

                    $infoGuiaId = $documento->guia_info_id;
                } else {
                    // Criar nova info_guia
                    $infoGuiaId = DB::table("info_guias")->insertGetId([
                        "marca" => $request->input("marca"),
                        "matricula" => $request->input("matricula"),
                        "local_origem" => $request->input("local_origem"),
                        "local_destino" => $request->input("local_destino"),
                        "data_origem" => $request->input("data_origem"),
                        "data_destino" => $request->input("data_destino"),
                        "created_at" => now(),
                        "updated_at" => now(),
                    ]);
                }
            }

            //verifica o estado de pagamento
            if ($totalFinal - $totalEntregue <= 0) {
                $request["estado_pagamento"] = EstadoPagamento::PAGO->value;
            } elseif ($totalEntregue > 0 && $totalFinal - $totalEntregue > 0) {
                $request["estado_pagamento"] =
                    EstadoPagamento::PARCIALMENTE_PAGO->value;
            } else {
                $request["estado_pagamento"] = EstadoPagamento::NAO_PAGO->value;
            }

            // Criação do documento
            $documentoUpdated = $documento->update([
                "tipo_nome" => $request["tipo_fatura"],
                "tipo_sigla" => $request["sigla_fatura"],
                //'tipo_cor' => $request['tipo_cor'],

                "estado_documento" => $request["estado_documento"] ?? "emitido",
                "estado_pagamento" =>
                $request["estado_pagamento"] ?? "por_pagar",
                "estado_vencimento" =>
                $request["estado_vencimento"] ?? "no_prazo",

                "num_fatura" =>
                $request["estado_documento"] === "rascunho"
                    ? ""
                    : $numFatura,
                "via" => "original",

                "empresa_id" => $request["empresa_id"],
                "empresa_nome" => $request["empresa_nome"],
                "empresa_nif" => $request["empresa_nif"],
                "empresa_telefone" => $request["empresa_telefone"],
                "empresa_email" => $request["empresa_email"],
                "empresa_endereco" => $request["empresa_endereco"],

                "cliente_id" => $request["cliente_id"] ?? null,
                "cliente_nome" => $request["cliente_nome"],
                "cliente_nif" => $request["cliente_nif"],
                "cliente_telefone" => $request["cliente_telefone"],
                "cliente_email" => $request["cliente_email"],
                "cliente_endereco" => $request["cliente_endereco"],

                "caixa" => $request["caixa"],
                "data_emissao" => $request["data_emissao"],
                "data_vencimento" => $request["data_vencimento"],
                "forma_pagamento" => $request["forma_pagamento"],
                "movimenta_stock" => $request["movimenta_stock"],

                "taxa_iva" => "0",
                "valor_iva" => "0",
                "retencao" => $retencao,

                "estado" => "emitido",

                "hash" => "aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd",

                "desconto_tipo" => $request["desconto_tipo"] ?? "",
                "desconto_total" => $descontoItensTotal + $descontoGeral,
                "valor_transporte" => $request["valor_transporte"],
                "total_sem_desconto" => $totalSemDesconto,
                "total_impostos" => $totalImpostos,
                "total_geral" => $totalFinal,
                "troco" => $troco,

                "utilizador_id" => $request["utilizador_id"],
                "utilizador" => $request["utilizador"],

                "info_guia_id" => $infoGuiaId,
            ]);

            $bancos = Conta::with("banco")
                ->where("empresa_id", $request->input("empresa_id"))
                ->where("estado", true)
                ->get();

            $delete = DB::table("bancos_documento")
                ->where("documento_id", $documento->id)
                ->delete();
            foreach ($bancos as $banco) {
                DB::table("bancos_documento")->insert([
                    "documento_id" => $documento->id,
                    "sigla" => $banco["banco"]->sigla,
                    "descricao" => $banco["banco"]->descricao,
                    "numero_conta" => $banco->numero_conta,
                    "iban" => $banco->iban,
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }

            $documento->meiosPagamento()->delete();
            foreach ($request->input("meiosPagamento") as $meioPagamento) {
                MeioPagamentoDocumento::create([
                    "documento_id" => $documento->id,
                    "descricao" => $meioPagamento["descricao"],
                    "valor" => $meioPagamento["valor"],
                ]);
            }

            // 8. Atualizar quadro imposto
            $documento->impostosDocumento()->delete();
            foreach ($quadroImposto as $value) {
                $value["incidencia"] = round($value["incidencia"], 2);
                $value["imposto"] = round($value["imposto"], 2);
                $value["liquido"] = round($value["liquido"], 2);

                ImpostoDocumento::create([
                    "documento_id" => $documento->id,
                    "taxa" => $value["taxa"],
                    "codigo" => $value["codigo"],
                    "isento" => $value["codigo"] === "ISENTO" ? 1 : 0,
                    "motivo_isencao" => $value["motivo_isencao"],
                    "incidencia" => $value["incidencia"],
                    "imposto" => $value["imposto"],
                    //'liquido' => $value['liquido'],
                    "total" => $value["incidencia"] + $value["imposto"],
                ]);
            }

            // Atualizar itens
            $documento->itens()->delete();
            $itens = [];
            foreach ($request["itens"] as $item) {
                $idImpostoTaxa = $item["iva_percent"];
                $taxaIva = TipoTaxaIva::find($idImpostoTaxa)->taxa;
                $codigoIva = TipoTaxaIva::find($idImpostoTaxa)->codigo;
                //$motivoIsencaoCodigo = null;
                $motivoIsencaoDescricao = null;

                if ($codigoIva === "ISENTO") {
                    $motivo = DB::table("motivo_isencao")
                        ->where("id", $item["motivo_isencao_id"]) // <-- aqui
                        ->first();
                    if ($motivo) {
                        $codigoIva = $motivo->codigo;
                        $motivoIsencaoDescricao = $motivo->motivo;
                    }
                }

                $desconto = 0;
                if (
                    isset($item["desconto_percent"]) &&
                    $item["desconto_percent"] > 0
                ) {
                    $desconto =
                        $item["preco_venda"] *
                        ($item["desconto_percent"] / 100);
                } elseif (
                    isset($item["desconto_fixo"]) &&
                    $item["desconto_fixo"] > 0
                ) {
                    $desconto = $item["desconto_fixo"];
                }

                // Calcula o total do item (sem IVA)
                $totalSemDesconto = $item["preco_venda"] * $item["quantidade"];
                $totalItem = $totalSemDesconto - $desconto;

                $itens[] = [
                    "documento_id" => $documento->id,
                    "produto_nome" => $item["produto_nome"],
                    "produto_codigo" => $item["codigo_produto"],
                    "preco_unitario" => $item["preco_venda"],
                    "descricao" => $item["descricao"],
                    "quantidade" => $item["quantidade"],
                    "desconto_percent" => $item["desconto_percent"],
                    "desconto_fixo" => $item["desconto_fixo"],
                    "iva_percent" => $taxaIva ?? 0,
                    "imposto_taxa_id" => $idImpostoTaxa,
                    "codigo_iva" => $codigoIva ?? "",
                    "tipo_id" => $item["tipo_id"],
                    "motivo_isencao" => $motivoIsencaoDescricao,
                    "total_sem_desconto" => $totalSemDesconto,
                    "total" => $totalItem,
                    // Adicione outros campos conforme necessário
                ];
            }

            $documento->itens()->createMany($itens);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(
                [
                    "message" => "Erro ao criar o documento.",
                    "error" => $th->getMessage(),
                ],
                500,
            );
        }

        // Criar Recibo caso o tipo de vencimento for a prazo
        if (
            $request->has("is_apronto") &&
            $request->input("is_apronto") === "1"
        ) {
            // Se for um APRONTO, relaciona com o documento relacionado
            $request->merge([
                "tipo_nome" => "Recibo",
                "tipo_sigla" => "RC",
                "total_geral" => $totalFinal,
                "documento_relacionado_id" => $documento->id,
            ]);

            $data = [
                "tipo_fatura" => "Recibo", // "RECIBO"
                "sigla_fatura" => "RC",
                "total_geral" => $totalFinal,
                "documento_relacionado_id" => $documento->id,
                "empresa_id" => $documento->empresa_id,
                "empresa_nome" => $documento->empresa_nome,
                "cliente_id" => $documento->cliente_id,
                "cliente_nome" => $documento->cliente_nome,
                "meiosPagamento" => $request->input("meiosPagamento"),
                "utilizador_id" => $request->input("utilizador_id"),
                "utilizador" => $request->input("utilizador"),
                "caixa" => $documento->caixa,
                "data_emissao" => $documento->data_emissao,
                "data_vencimento" => $documento->data_vencimento,
            ];

            $recibo = $this->storeRecibo(new Request($data));

            return response()->json(
                [
                    "message" => "Factura e Recibo criados com sucesso.",
                    "documento" => $documento->load("itens"),
                    "documento_recibo" => $recibo->documento ?? "",
                ],
                201,
            );
        }

        return response()->json(
            [
                "message" => "Documento criado com sucesso.",
                "documento" => $documento->load("itens"),
            ],
            201,
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function NumLastDoc(Request $request)
    {
        $tipo = $request->query("tipo");

        $lastDoc = $this->gerarNumeroDocumento(
            $tipo,
            $request->empresa_id ?? "1",
        );

        return response()->json([
            "num_fatura" => $lastDoc,
        ]);
    }
}
