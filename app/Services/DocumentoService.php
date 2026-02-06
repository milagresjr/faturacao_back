<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;

class DocumentoService
{

  private $idUtilizador;
  private $documento;
  private $isFaturaCompra;

  public function updateStock(Documento | DocumentoCompra $documento, $isFC = false)
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
}
