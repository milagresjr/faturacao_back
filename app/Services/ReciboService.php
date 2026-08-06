<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Empresa;
use App\Models\MeioPagamentoDocumento;
use App\Models\Serie;
use Illuminate\Support\Facades\DB;

class ReciboService
{
    public function __construct(
        private DocumentoNumeroService $numeroService,
    ) {}

    public function criar(array $dados, string $tipoRelacao = 'RECIBO_FATURA')
    {
        $empresa = Empresa::find($dados['empresa_id']);
        if (!$empresa || !$empresa->nif) {
            return response()->json([
                'message' => 'A empresa não tem NIF cadastrado.',
                'error' => 'MISSING_NIF'
            ], 422);
        }

        $ano = now()->year;
        $serie = Serie::where('empresa_id', $dados['empresa_id'])
            ->where('tipo_documento', 'recibo')
            ->where('ano', $ano)
            ->where('padrao', true)
            ->where('ativo', true)
            ->first();

        if (!$serie) {
            $serie = Serie::where('empresa_id', $dados['empresa_id'])
                ->where('tipo_documento', 'recibo')
                ->where('ativo', true)
                ->latest('ano')
                ->first();
        }

        if (!$serie) {
            return response()->json([
                'message' => 'Nenhuma série padrão de recibo encontrada para esta empresa.'
            ], 422);
        }

        $numRecibo = $this->numeroService->gerarNumeroDocumento($serie->id);

        $totalEntregue = 0;
        foreach (($dados['meiosPagamento'] ?? []) as $meio) {
            $totalEntregue += (float) $meio['valor'];
        }

        $troco = max($totalEntregue - $dados['total_geral'], 0);

        $documento = Documento::create([
            'tipo_nome' => $dados['tipo_fatura'],
            'tipo_sigla' => $dados['sigla_fatura'],
            'num_fatura' => $numRecibo,
            'via' => 'Original',
            'empresa_id' => $dados['empresa_id'],
            'empresa_nome' => $dados['empresa_nome'],
            'empresa_nif' => $dados['empresa_nif'],
            'cliente_id' => $dados['cliente_id'] ?? null,
            'cliente_nome' => $dados['cliente_nome'] ?? null,
            'cliente_nif' => $dados['cliente_nif'] ?? null,
            'caixa' => $dados['caixa'] ?? 'CAIXA PRINCIPAL',
            'data_emissao' => $dados['data_emissao'],
            'movimenta_stock' => false,
            'total_geral' => $dados['total_geral'],
            'troco' => $troco,
            'estado' => 'emitido',
            'hash' => 'rfsuhihuhuycgygyfyukgeyggfavdyvd',
            'serie_id' => $serie->id,
            'utilizador_id' => $dados['utilizador_id'],
            'utilizador' => $dados['utilizador'],
        ]);

        DB::table('documento_relacoes')->insert([
            'documento_id' => $documento->id,
            'documento_relacionado_id' => $dados['documento_relacionado_id'],
            'tipo_relacao' => $tipoRelacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (($dados['meiosPagamento'] ?? []) as $meio) {
            MeioPagamentoDocumento::create([
                'documento_id' => $documento->id,
                'descricao' => $meio['descricao'],
                'valor' => $meio['valor'],
            ]);
        }

        return response()->json([
            'message' => 'Recibo criado com sucesso.',
            'documento' => $documento,
        ], 201);
    }
}
