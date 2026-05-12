<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\LoteProduto;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;

class DocumentoService
{

  private $idUtilizador;
  private $documento;
  private $isFaturaCompra;

  public function updateStock2(Documento | DocumentoCompra $documento, $isFC = false)
  {
    $this->idUtilizador = $documento->utilizador_id;
    $this->documento = $documento;
    $this->isFaturaCompra = $isFC;

    foreach ($documento->itens as $item) {
      if (in_array($documento->tipo_sigla, ['FT', 'FR', 'FG', 'NC'])) {
        $this->decreaseStock($item->produto_id, $item->quantidade);
      }
      if ($isFC || in_array($documento->tipo_sigla, ['FS', 'ND'])) {
        $this->increaseStock($item->produto_id, $item->quantidade);
      }
    }
  }

  private function decreaseStock($produtoId, $quantidade)
  {
    $produto = Produto::find($produtoId);

    if (!$produto) {
      return "Poduct not found!";
    }

    if (!$produto->movimenta_stock) {
      return;
    }

    // Encontra o stock do produto no armazém
    $stock = Stock::where('produto_id', $produtoId)
      ->where('armazem_id', $this->documento->armazem_id)
      ->first();

    //Cria um registo na tabela de movimentos de stock
    $this->documento->movimentosStock()->create([
      'stock_id' => $stock->id,
      'empresa_id' => $produto->empresa_id,
      'armazem_id' => $this->documento->armazem_id,
      'produto_id' => $produtoId,
      'quantidade' => $quantidade,
      'operacao' => 'saida',
      'observacao' => 'Movimentação automática por documento',
      'utilizador_id' => $this->idUtilizador,
      'origem_movimento' => $this->documento->num_fatura,
    ]);

    //Atualiza na tabela Stock
    $stock = Stock::where('produto_id', $produtoId)->first();

    if ($stock) {
      $stock->decrement('stock_atual', $quantidade);
    }
  }

  private function increaseStock($produtoId, $quantidade)
  {
    $produto = Produto::find($produtoId);

    if (!$produto) {
      return "Poduct not found!";
    }

    if (!$produto->movimenta_stock) {
      return;
    }

    $stock = Stock::where('produto_id', $produtoId)
      ->where('armazem_id', $this->documento->armazem_id)
      ->first();

    if ($this->isFaturaCompra) {

      $this->documento->movimentosStock()->create([
        'stock_id' => $stock->id,
        'empresa_id' => $produto->empresa_id,
        'armazem_id' => $this->documento->armazem_id,
        'produto_id' => $produtoId,
        'quantidade' => $quantidade,
        'operacao' => 'entrada',
        'observacao' => 'Receção de Encomenda',
        'utilizador_id' => $this->idUtilizador,
        'origem_movimento' => $this->documento->num_fatura,
      ]);
    } else {
      $this->documento->movimentosStock()->create([
        'stock_id' => $stock->id,
        'armazem_id' => $this->documento->armazem_id,
        'produto_id' => $produtoId,
        'quantidade' => $quantidade,
        'operacao' => 'entrada',
        'observacao' => 'Movimentação automática por documento',
        'utilizador_id' => $this->idUtilizador,
        'origem_movimento' => $this->documento->num_fatura,
      ]);
    }

    // Atualizar a tabela Stock
    $stock = Stock::where('produto_id', $produtoId)->first();

    if ($stock) {
      $stock->increment('stock_atual', $quantidade);
    }
  }

  // app/Services/DocumentoService.php

  public function updateStock($documento)
  {
    foreach ($documento->itens as $item) {
      $produto = Produto::find($item->produto_id);

      // Se o produto não movimenta stock, pula
      if (!$produto->movimenta_stock) {
        continue;
      }

      // ==========================================
      // PRODUTO COM VALIDADE (usa lotes)
      // ==========================================
      if ($produto->controla_validade) {
        $this->atualizarStockPorLote($produto, $item, $documento);
      }

      // ==========================================
      // PRODUTO SEM VALIDADE (stock simples)
      // ==========================================
      else {
        $this->atualizarStockSimples($produto, $item, $documento);
      }
    }
  }

  /**
   * Atualizar stock para produtos com validade (usando lotes)
   */
  private function atualizarStockPorLote($produto, $item, $documento)
  {
    // Buscar lotes disponíveis no armazém da fatura
    $lotes = LoteProduto::where('produto_id', $produto->id)
      ->where('armazem_id', $documento->armazem_id)
      ->where('status', 'activo')
      ->where('qtd_atual', '>', 0)
      ->where('data_validade', '>=', now())
      ->orderBy('data_validade', 'asc')  // FEFO: primeiro o que vence antes
      ->orderBy('created_at', 'asc')
      ->get();

    $quantidadeVender = $item->quantidade;
    $lotesUtilizados = [];
   
    foreach ($lotes as $lote) {
      if ($quantidadeVender <= 0) break;

      // Quantidade a vender deste lote (o mínimo entre o que temos no lote e o que ainda precisamos vender)
      $quantidadeLote = min($lote->qtd_atual, $quantidadeVender);

      // Dar baixa no lote
      $lote->qtd_atual -= $quantidadeLote;

      if ($lote->qtd_atual <= 0) {
        $lote->status = 'consumido';
      }

      $lote->save();

      $lotesUtilizados[] = [
        'lote_id' => $lote->id,
        'codigo_lote' => $lote->codigo_lote,
        'quantidade' => $quantidadeLote,
        'data_validade' => $lote->data_validade
      ];

      // Diminuir a quantidade que ainda precisamos vender
      $quantidadeVender -= $quantidadeLote;

      // Registrar movimento de stock com o lote
      $this->registrarMovimentoStock([
        'produto_id' => $produto->id,
        'quantidade' => $quantidadeLote,
        'operacao' => 'saida',
        'observacao' => "Venda - Documento: {$documento->num_fatura}",
        'armazem_id' => $documento->armazem_id,
        'utilizador_id' => $documento->utilizador_id,
        'origem_movimento' => $documento->num_fatura,
        'documento_relacionado_id' => $documento->id,
        'empresa_id' => $documento->empresa_id,
        'lote_id' => $lote->id,
        'codigo_lote' => $lote->codigo_lote,
        'data_validade_lote' => $lote->data_validade
      ], $documento);
    }

    if ($quantidadeVender > 0) {
      // Se ainda falta quantidade para vender, significa que não temos stock suficiente nos lotes
      throw new \Exception("Stock insuficiente para o produto {$produto->nome}. Faltam {$quantidadeVender} unidades.");
    }

    // Atualizar stock consolidado do produto
    $this->atualizarStockConsolidadoProduto($produto->id);

    // Registrar os lotes utilizados no item da fatura (opcional)
    $item->update([
      'detalhes_lote' => json_encode($lotesUtilizados)
    ]);

  }

  /**
   * Atualizar stock para produtos sem validade
   */
  private function atualizarStockSimples($produto, $item, $documento)
  {
    $stock = Stock::where('produto_id', $produto->id)
      ->where('armazem_id', $documento->armazem_id)
      ->first();

    if (!$stock) {
      throw new \Exception("Stock não configurado para o produto {$produto->nome} no armazém");
    }

    if ($stock->stock_atual < $item->quantidade) {
      throw new \Exception("Stock insuficiente para o produto {$produto->nome}. Disponível: {$stock->stock_atual}, Solicitado: {$item->quantidade}");
    }

    $stock->decrement('stock_atual', $item->quantidade);

    // Registrar movimento de stock
    $this->registrarMovimentoStock([
      'produto_id' => $produto->id,
      'quantidade' => $item->quantidade,
      'operacao' => 'saida',
      'observacao' => "Venda - Documento: {$documento->num_fatura}",
      'armazem_id' => $documento->armazem_id,
      'utilizador_id' => $documento->utilizador_id,
      'origem_movimento' => $documento->num_fatura,
      'documento_relacionado_id' => $documento->id,
      'empresa_id' => $documento->empresa_id,
      'stock_id' => $stock->id
    ], $documento);
  }

  /**
   * Registrar movimento de stock
   */
  private function registrarMovimentoStock(array $dados, $documento = null)
  {
    // Se temos um documento, usar a relação polimórfica
    if ($documento) {
      $movimento = $documento->movimentosStock()->create([
        'produto_id' => $dados['produto_id'],
        'quantidade' => $dados['quantidade'],
        'operacao' => $dados['operacao'],
        'observacao' => $dados['observacao'] ?? null,
        'armazem_id' => $dados['armazem_id'] ?? null,
        'utilizador_id' => $dados['utilizador_id'] ?? null,
        'origem_movimento' => $dados['origem_movimento'] ?? null,
        'empresa_id' => $dados['empresa_id'],
        'lote_id' => $dados['lote_id'] ?? null,
        'codigo_lote' => $dados['codigo_lote'] ?? null,
        'data_validade_lote' => $dados['data_validade_lote'] ?? null,
        'stock_id' => $dados['stock_id'] ?? null
      ]);
    }

    // Fallback: criar sem relação polimórfica (como já fazia)
    MovimentoStock::create([
      'produto_id' => $dados['produto_id'],
      'quantidade' => $dados['quantidade'],
      'operacao' => $dados['operacao'],
      'observacao' => $dados['observacao'] ?? null,
      'armazem_id' => $dados['armazem_id'] ?? null,
      'utilizador_id' => $dados['utilizador_id'] ?? null,
      'origem_movimento' => $dados['origem_movimento'] ?? null,
      'documento_relacionado_id' => $dados['documento_relacionado_id'] ?? null,
      'empresa_id' => $dados['empresa_id'],
      'lote_id' => $dados['lote_id'] ?? null,
      'codigo_lote' => $dados['codigo_lote'] ?? null,
      'data_validade_lote' => $dados['data_validade_lote'] ?? null,
      'stock_id' => $dados['stock_id'] ?? null
    ]);
  }

  /**
   * Atualizar stock consolidado do produto (soma de todos lotes)
   */
  private function atualizarStockConsolidadoProduto($produtoId)
  {
    $totalStock = LoteProduto::where('produto_id', $produtoId)
      ->where('status', 'activo')
      ->where('data_validade', '>=', now())
      ->sum('qtd_atual');

    Produto::where('id', $produtoId)->update([
      'stock_atual' => $totalStock
    ]);
  }
}
