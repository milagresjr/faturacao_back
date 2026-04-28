<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoCompra;
use App\Models\LoteProduto;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;

class DocumentoCompraService
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


  //==================================================
  public function updateStock(DocumentoCompra $documento)
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
        $this->atualizarStockPorLoteEntrada($produto, $item, $documento);
      }

      // ==========================================
      // PRODUTO SEM VALIDADE (stock simples)
      // ==========================================
      else {
        $this->atualizarStockSimplesEntrada($produto, $item, $documento);
      }
    }
  }

  /**
   * Atualizar stock para produtos com validade (ENTRADA - compra)
   * Cria ou atualiza lote no armazém
   */
  private function atualizarStockPorLoteEntrada($produto, $item, $documento)
  {
    // Verificar se veio informação do lote no item da compra
    $codigoLote = $item->codigo_lote ?? null;

    // Se não veio código do lote, gerar automaticamente
    if (empty($codigoLote)) {
      $codigoLote = $this->gerarCodigoLote($produto->id);
    }

    // Validar data de validade (obrigatória para produtos com validade)
    if (empty($item->data_validade)) {
      throw new \Exception("Data de validade é obrigatória para o produto {$produto->nome}");
    }

    $dataValidade = \Carbon\Carbon::parse($item->data_validade);
    if ($dataValidade < now()) {
      throw new \Exception("Não é possível dar entrada em produto com data de validade vencida: {$dataValidade->format('d/m/Y')}");
    }

    // Verificar se lote já existe neste armazém
    $lote = LoteProduto::where('produto_id', $produto->id)
      ->where('armazem_id', $documento->armazem_id)
      ->where('codigo_lote', $codigoLote)
      ->first();

    if ($lote) {
      // Lote já existe → adicionar quantidade
      $quantidadeAnterior = $lote->qtd_atual;
      $lote->qtd_atual += $item->quantidade;

      // Atualizar data de validade se a nova for mais curta
      if ($dataValidade < $lote->data_validade) {
        $lote->data_validade = $dataValidade;
      }

      // Atualizar preço de custo (opcional)
      if (isset($item->preco_custo)) {
        $lote->preco_custo = $item->preco_custo;
      }

      $lote->save();

      $detalhesLote = [
        'tipo' => 'atualizacao',
        'quantidade_anterior' => $quantidadeAnterior,
        'quantidade_adicionada' => $item->quantidade,
        'quantidade_atual' => $lote->qtd_atual
      ];
    } else {
      // Criar novo lote
      $lote = LoteProduto::create([
        'produto_id' => $produto->id,
        'armazem_id' => $documento->armazem_id,
        'codigo_lote' => $codigoLote,
        'data_fabricacao' => $item->data_fabricacao ?? null,
        'data_validade' => $dataValidade,
        'qtd_atual' => $item->quantidade,
        'qtd_inicial' => $item->quantidade,
        'preco_custo' => $item->preco_custo ?? null,
        'status' => 'activo',
        'observacao' => "Compra - Documento: {$documento->num_fatura}",
        'data_entrada' => now()
      ]);

      $detalhesLote = [
        'tipo' => 'criacao',
        'lote_novo' => true,
        'quantidade_inicial' => $item->quantidade
      ];
    }

    // Registrar movimento de stock (entrada)
    $this->registrarMovimentoStockEntrada([
      'produto_id' => $produto->id,
      'quantidade' => $item->quantidade,
      'operacao' => 'entrada',
      'observacao' => "Compra - Lote: {$codigoLote}",
      'armazem_id' => $documento->armazem_id,
      'utilizador_id' => $documento->utilizador_id,
      'origem_movimento' => $documento->num_fatura,
      'empresa_id' => $documento->empresa_id,
      'lote_id' => $lote->id,
      'codigo_lote' => $codigoLote,
      'data_validade_lote' => $dataValidade,
      'detalhes_lote' => $detalhesLote
    ], $documento);

    // Atualizar stock consolidado do produto
    $this->atualizarStockConsolidadoProduto($produto->id);

    // Atualizar o item da compra com o código do lote gerado (opcional)
    if (empty($item->codigo_lote)) {
      $item->update([
        'codigo_lote' => $codigoLote
      ]);
    }
  }

  /**
   * Atualizar stock para produtos sem validade (ENTRADA - compra)
   */
  private function atualizarStockSimplesEntrada($produto, $item, $documento)
  {
    $stock = Stock::where('produto_id', $produto->id)
      ->where('armazem_id', $documento->armazem_id)
      ->first();

    if ($stock) {
      $stock->increment('stock_atual', $item->quantidade);
    } else {
      // Criar stock se não existir
      $stock = Stock::create([
        'produto_id' => $produto->id,
        'armazem_id' => $documento->armazem_id,
        'stock_atual' => $item->quantidade,
        'stock_min' => $produto->stock_min ?? 0,
        'stock_max' => $produto->stock_max ?? 0,
        'stock_ideal' => $produto->stock_ideal ?? 0,
        'empresa_id' => $documento->empresa_id
      ]);
    }

    // Registrar movimento de stock
    $this->registrarMovimentoStockEntrada([
      'produto_id' => $produto->id,
      'quantidade' => $item->quantidade,
      'operacao' => 'entrada',
      'observacao' => "Compra - Documento: {$documento->num_fatura}",
      'armazem_id' => $documento->armazem_id,
      'utilizador_id' => $documento->utilizador_id,
      'origem_movimento' => $documento->num_fatura,
      'empresa_id' => $documento->empresa_id,
      'stock_id' => $stock->id
    ], $documento);
  }

  /**
   * Registrar movimento de stock (ENTRADA)
   */
  private function registrarMovimentoStockEntrada(array $dados, $documento = null)
  {
    $movimentoData = [
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
      'stock_id' => $dados['stock_id'] ?? null,
      'detalhes_lote' => isset($dados['detalhes_lote']) ? json_encode($dados['detalhes_lote']) : null
    ];

    // Se temos um documento, usar a relação polimórfica
    if ($documento) {
      $documento->movimentosStock()->create($movimentoData);
    } else {
      // Fallback: criar sem relação polimórfica
      MovimentoStock::create($movimentoData);
    }
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

  /**
   * Gerar código de lote automaticamente
   */
  private function gerarCodigoLote($produtoId)
  {
    $dataHoje = now()->format('Ymd');
    $prefixo = "COMPRA-{$produtoId}-{$dataHoje}";

    $ultimoLote = LoteProduto::where('produto_id', $produtoId)
      ->where('codigo_lote', 'LIKE', $prefixo . '%')
      ->orderBy('codigo_lote', 'desc')
      ->first();

    if ($ultimoLote) {
      $partes = explode('-', $ultimoLote->codigo_lote);
      $sequencial = intval(end($partes)) + 1;
    } else {
      $sequencial = 1;
    }

    $sequencialFormatado = str_pad($sequencial, 3, '0', STR_PAD_LEFT);
    return "{$prefixo}-{$sequencialFormatado}";
  }
}
