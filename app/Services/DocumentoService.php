<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\MovimentoStock;
use App\Models\Produto;

class DocumentoService
{

  private $idUtilizador;

  public function updateStock(Documento $documento)
  {
    $this->idUtilizador = $documento->utilizador_id;
    foreach ($documento->itens as $item) {
      if ($documento->movimenta_stock) {
        if (in_array($documento->tipo_sigla, ['FT', 'FR', 'FG', 'NC'])) {
          $this->decreaseStock($item->produto_id, $item->quantidade);
        } elseif (in_array($documento->tipo_sigla, ['FS', 'ND'])) {
          $this->increaseStock($item->produto_id, $item->quantidade);
        }
      }
    }
  }

  private function decreaseStock($produtoId, $quantidade)
  {
    $produto = Produto::findOrFail($produtoId);
    //Cria um registo na tabela de movimentos de stock
    MovimentoStock::create([
      'armazem_id' => $produto->armazem_id,
      'produto_id' => $produtoId,
      'quantidade' => $quantidade,
      'operacao' => 'saida',
      'observacao' => 'Movimentação automática por documento',
      'utilizador_id' => $this->idUtilizador,
      'origem_movimento' => 'documento',
    ]);
  }

  private function increaseStock($produtoId, $quantidade)
  {
    $produto = Produto::findOrFail($produtoId);

    MovimentoStock::create([
      'armazem_id' => $produto->armazem_id,
      'produto_id' => $produtoId,
      'quantidade' => $quantidade,
      'operacao' => 'entrada',
      'observacao' => 'Movimentação automática por documento',
      'utilizador_id' => $this->idUtilizador,
      'origem_movimento' => 'documento',
    ]);
  }
}
