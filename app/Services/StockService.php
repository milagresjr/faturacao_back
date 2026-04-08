<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\LoteProduto;
use App\Models\MovimentoStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
  /**
   * Dar entrada de produtos (compra)
   * Suporta tanto produtos com lote quanto sem lote
   */
  public function darEntrada($produtoId, $quantidade, $preco, $dadosLote = null)
  {
    $produto = Produto::findOrFail($produtoId);

    DB::beginTransaction();

    try {
      if ($produto->precisaControlarValidade() && $dadosLote) {
        // Produto com validade - criar lote
        $lote = LoteProduto::create([
          'produto_id' => $produto->id,
          'codigo_lote' => $dadosLote['codigo_lote'],
          'data_fabricacao' => $dadosLote['data_fabricacao'] ?? null,
          'data_validade' => $dadosLote['data_validade'],
          'qtd_atual' => $quantidade,
          'qtd_inicial' => $quantidade,
          'status' => 'activo'
        ]);

        // Registrar movimento
        MovimentoStock::create([
          'produto_id' => $produto->id,
          'operacao' => 'entrada',
          'quantidade' => $quantidade,
          // 'preco_unitario' => $preco,
          'origem_movimento' => 'compra',
          'lote_id' => $lote->id,
          'codigo_lote' => $lote->codigo_lote,
          'observacao' => "Compra - Lote: {$lote->codigo_lote}",
          'empresa_id' => $produto->empresa_id
        ]);
      } else {
        // Produto sem validade - manter sistema antigo
        $produto->stock += $quantidade;
        $produto->save();

        // Registrar movimento (sem lote)
        MovimentoStock::create([
          'produto_id' => $produto->id,
          'tipo' => 'entrada',
          'quantidade' => $quantidade,
          // 'preco_unitario' => $preco,
          'origem_movimento' => 'compra',
          'observacao' => "Compra - Sistema legado",
          'empresa_id' => $produto->empresa_id,
        ]);
      }

      DB::commit();
      return true;
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Erro ao dar entrada: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Dar saída de produtos (venda)
   * Prioriza produtos com validade mais próxima (FEFO)
   */
  public function darSaida($produtoId, $quantidade, $preco, $faturaId = null)
  {
    $produto = Produto::findOrFail($produtoId);

    DB::beginTransaction();

    try {
      if ($produto->precisaControlarValidade()) {
        // Buscar lotes FEFO (primeiro a vencer)
        $lotes = LoteProduto::where('produto_id', $produto->id)
          ->where('status', 'activo')
          ->where('qtd_atual', '>', 0)
          ->where('data_validade', '>=', now())
          ->orderBy('data_validade', 'asc')
          ->get();

        $quantidadeRestante = $quantidade;

        foreach ($lotes as $lote) {
          if ($quantidadeRestante <= 0) break;

          $quantidadeLote = min($lote->qtd_atual, $quantidadeRestante);

          // Dar baixa no lote
          $lote->darBaixa($quantidadeLote);

          // Registrar movimento
          MovimentoStock::create([
            'produto_id' => $produto->id,
            'operacao' => 'saida',
            'quantidade' => $quantidadeLote,
            // 'preco_unitario' => $preco,
            'lote_id' => $lote->id,
            'codigo_lote' => $lote->codigo_lote,
            'data_validade_momento' => $lote->data_validade,
            'observacao' => "Venda - Fatura: {$faturaId}"
          ]);

          $quantidadeRestante -= $quantidadeLote;
        }

        if ($quantidadeRestante > 0) {
          throw new \Exception("Stock insuficiente. Faltam {$quantidadeRestante} unidades");
        }
      } else {
        // Produto sem validade - sistema antigo
        if ($produto->stock < $quantidade) {
          throw new \Exception("Stock insuficiente");
        }

        $produto->stock -= $quantidade;
        $produto->save();

        MovimentoStock::create([
          'produto_id' => $produto->id,
          'operacao' => 'saida',
          'quantidade' => $quantidade,
          // 'preco_unitario' => $preco,
          'observacao' => "Venda - Fatura: {$faturaId}",
          'empresa_id' => $produto->empresa_id
        ]);
      }

      DB::commit();
      return true;
    } catch (\Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }

  /**
   * Consultar stock (compatível com sistema antigo)
   */
  public function consultarStock($produtoId)
  {
    $produto = Produto::findOrFail($produtoId);

    if ($produto->precisaControlarValidade()) {
      return [
        'stock_total' => $produto->stock_valido,
        'detalhe_lotes' => $produto->lotes()
          ->where('status', 'activo')
          ->where('qtd_atual', '>', 0)
          ->get()
          ->map(function ($lote) {
            return [
              'lote' => $lote->codigo_lote,
              'quantidade' => $lote->qtd_atual,
              'validade' => $lote->data_validade->format('d/m/Y'),
              'dias_restantes' => $lote->dias_restantes
            ];
          })
      ];
    } else {
      return [
        'stock_total' => $produto->stock,
        'detalhe_lotes' => []
      ];
    }
  }
}
