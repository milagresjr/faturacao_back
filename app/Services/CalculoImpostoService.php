<?php

namespace App\Services;

use App\Models\TipoTaxaIva;
use Illuminate\Support\Facades\DB;

class CalculoImpostoService
{
    public function calcularQuadroImposto(array $itens): array
    {
        $quadroImposto = [];
        $totalLiquido = 0;
        $totalBase = 0;
        $subtotalBruto = 0;

        foreach ($itens as $item) {
            $impostoTaxaId = $item['iva_percent'] ?? $item['imposto_taxa_id'] ?? null;
            $tipo = $impostoTaxaId ? TipoTaxaIva::find($impostoTaxaId) : null;
            $taxaIva = $tipo?->taxa ?? 0;
            $codigo = $tipo?->codigo ?? 'NOR';

            $motivoIsencaoId = $item['motivo_isencao_id'] ?? '';
            $motivo = '';
            if ($codigo === 'ISENTO' && $motivoIsencaoId) {
                $motivo = DB::table('motivo_isencao')
                    ->where('id', $motivoIsencaoId)
                    ->value('motivo');
            }

            $subtotalBrutoItem = $item['preco_venda'] * $item['quantidade'];

            $desconto = 0;
            if (!empty($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $subtotalBrutoItem * ($item['desconto_percent'] / 100);
            } elseif (!empty($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            $subtotalLiquido = $subtotalBrutoItem - $desconto;

            $base = round($subtotalLiquido / (1 + $taxaIva / 100), 2);
            $imposto = round($subtotalLiquido - $base, 2);

            $chave = $taxaIva . '|' . $motivoIsencaoId;

            if (!isset($quadroImposto[$chave])) {
                $quadroImposto[$chave] = [
                    'taxa' => $taxaIva,
                    'codigo' => $codigo,
                    'motivo_isencao' => $motivo,
                    'incidencia' => 0.0,
                    'imposto' => 0.0,
                    'liquido' => 0.0,
                ];
            }

            $quadroImposto[$chave]['incidencia'] += $base;
            $quadroImposto[$chave]['imposto'] += $imposto;
            $quadroImposto[$chave]['liquido'] += $subtotalLiquido;

            $totalLiquido += $subtotalLiquido;
            $totalBase += $base;
            $subtotalBruto += $subtotalBrutoItem;
        }

        return [
            'quadro' => $quadroImposto,
            'total_liquido' => $totalLiquido,
            'total_base' => $totalBase,
            'subtotal_bruto' => $subtotalBruto,
        ];
    }

    public function calcularTotais(array $itens, ?string $descontoTipo, ?float $descontoTotal, array &$quadroImposto, float $totalLiquido): array
    {
        $totalSemDesconto = 0;
        $descontoItensTotal = 0;

        foreach ($itens as $item) {
            $precoBruto = $item['preco_venda'] * $item['quantidade'];
            $desconto = 0;

            if (!empty($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $precoBruto * ($item['desconto_percent'] / 100);
            } elseif (!empty($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'] * $item['quantidade'];
            }

            $totalSemDesconto += $precoBruto;
            $descontoItensTotal += $desconto;
        }

        $descontoGeral = 0;
        if ($descontoTipo === 'percentual') {
            $descontoGeral = $totalSemDesconto * ($descontoTotal / 100);
        } elseif ($descontoTipo === 'fixo') {
            $descontoGeral = $descontoTotal;
        }

        if ($descontoGeral > 0 && $totalLiquido > 0) {
            $totalLiquidoOriginal = $totalLiquido;
            $groupKeys = array_keys($quadroImposto);
            $lastKey = end($groupKeys);
            $assigned = 0.0;

            foreach ($groupKeys as $key) {
                $linha = &$quadroImposto[$key];
                $proporcao = $linha['liquido'] / $totalLiquidoOriginal;

                if ($key !== $lastKey) {
                    $descontoLinha = round($descontoGeral * $proporcao, 2);
                    $assigned += $descontoLinha;
                } else {
                    $descontoLinha = round($descontoGeral - $assigned, 2);
                }

                $linha['liquido'] = round($linha['liquido'] - $descontoLinha, 2);
                $linha['incidencia'] = round($linha['liquido'] / (1 + $linha['taxa'] / 100), 2);
                $linha['imposto'] = round($linha['liquido'] - $linha['incidencia'], 2);

                unset($linha);
            }

            $totalLiquido = array_sum(array_column($quadroImposto, 'liquido'));
            $totalBase = array_sum(array_column($quadroImposto, 'incidencia'));
            $totalImposto = array_sum(array_column($quadroImposto, 'imposto'));
        }

        $totalFinal = $totalSemDesconto - $descontoItensTotal - $descontoGeral;
        $totalImpostos = array_sum(array_column($quadroImposto, 'imposto'));

        return [
            'total_sem_desconto' => $totalSemDesconto,
            'desconto_itens_total' => $descontoItensTotal,
            'desconto_geral' => $descontoGeral,
            'total_final' => $totalFinal,
            'total_impostos' => $totalImpostos,
        ];
    }

    public function calcularRetencao(array $itens): float
    {
        $retencao = 0;
        foreach ($itens as $item) {
            if (isset($item['tipo_produto']) && $item['tipo_produto'] === 'S') {
                if ($item['preco_venda'] > 20000) {
                    $retencao += $item['preco_venda'] * 0.06;
                }
            }
        }
        return $retencao;
    }

    public function calcularTroco(array $meiosPagamento, float $totalFinal): float
    {
        $totalEntregue = 0;
        foreach ($meiosPagamento as $meio) {
            $totalEntregue += (float) $meio['valor'];
        }
        $troco = $totalEntregue - $totalFinal;
        return max($troco, 0);
    }

    public function determinarEstadoPagamento(float $totalFinal, float $totalEntregue): string
    {
        if ($totalFinal - $totalEntregue <= 0) {
            return \App\Enums\EstadoPagamento::PAGO->value;
        } elseif ($totalEntregue > 0 && $totalFinal - $totalEntregue > 0) {
            return \App\Enums\EstadoPagamento::PARCIALMENTE_PAGO->value;
        }
        return \App\Enums\EstadoPagamento::NAO_PAGO->value;
    }

    public function montarItensParaInsercao(array $itens, int $documentoId): array
    {
        $result = [];
        foreach ($itens as $item) {
            $idImpostoTaxa = $item['iva_percent'] ?? $item['imposto_taxa_id'] ?? null;
            $tipoTaxa = $idImpostoTaxa ? TipoTaxaIva::find($idImpostoTaxa) : null;
            $taxaIva = $tipoTaxa?->taxa ?? 0;
            $codigoIva = $tipoTaxa?->codigo ?? 'NOR';
            $motivoIsencaoDescricao = null;

            if ($codigoIva === 'ISENTO') {
                $motivo = DB::table('motivo_isencao')
                    ->where('id', $item['motivo_isencao_id'])
                    ->first();
                if ($motivo) {
                    $codigoIva = $motivo->codigo;
                    $motivoIsencaoDescricao = $motivo->motivo;
                }
            }

            $desconto = 0;
            if (!empty($item['desconto_percent']) && $item['desconto_percent'] > 0) {
                $desconto = $item['preco_venda'] * ($item['desconto_percent'] / 100);
            } elseif (!empty($item['desconto_fixo']) && $item['desconto_fixo'] > 0) {
                $desconto = $item['desconto_fixo'];
            }

            $totalSemDesconto = $item['preco_venda'] * $item['quantidade'];
            $totalItem = $totalSemDesconto - $desconto;

            $result[] = [
                'documento_id' => $documentoId,
                'produto_id' => $item['produto_id'] ?? null,
                'produto_nome' => $item['produto_nome'],
                'produto_codigo' => $item['codigo_produto'],
                'preco_unitario' => $item['preco_venda'],
                'descricao' => $item['descricao'] ?? '',
                'quantidade' => $item['quantidade'],
                'desconto_percent' => $item['desconto_percent'],
                'desconto_fixo' => $item['desconto_fixo'],
                'iva_percent' => $taxaIva ?? 0,
                'imposto_taxa_id' => $idImpostoTaxa,
                'codigo_iva' => $codigoIva ?? '',
                'tipo_id' => $item['tipo_id'] ?? null,
                'motivo_isencao' => $motivoIsencaoDescricao,
                'total_sem_desconto' => $totalSemDesconto,
                'total' => $totalItem,
            ];
        }
        return $result;
    }
}
