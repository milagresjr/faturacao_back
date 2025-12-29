<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\MovimentoStock;
use App\Models\Produto;

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

    //Cria um registo na tabela de movimentos de stock
    $this->documento->movimentosStock()->create([
      'armazem_id' => $this->documento->armazem_id,
      'produto_id' => $produtoId,
      'quantidade' => $quantidade,
      'operacao' => 'saida',
      'observacao' => 'Movimentação automática por documento',
      'utilizador_id' => $this->idUtilizador,
      'origem_movimento' => $this->documento->num_fatura,
    ]);
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

    
    if ($this->isFaturaCompra) {
      
      $this->documento->movimentosStock()->create([
        'armazem_id' => $this->documento->armazem_id,
        'produto_id' => $produtoId,
        'quantidade' => $quantidade,
        'operacao' => 'entrada',
        'observacao' => 'Movimentação automática por documento',
        'utilizador_id' => $this->idUtilizador,
        'origem_movimento' => $this->documento->num_fatura,
      ]);
    } else {
      $this->documento->movimentosStock()->create([
        'armazem_id' => $this->documento->armazem_id,
        'produto_id' => $produtoId,
        'quantidade' => $quantidade,
        'operacao' => 'entrada',
        'observacao' => 'Movimentação automática por documento',
        'utilizador_id' => $this->idUtilizador,
        'origem_movimento' => $this->documento->num_fatura,
      ]);
    }
  }
}
