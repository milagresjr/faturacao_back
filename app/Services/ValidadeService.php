<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\LoteProduto;
use App\Models\MovimentoStock;
use App\Models\NotificacaoValidade;
use App\Models\ConfigValidacaoProduto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ValidadeService
{
    /**
     * Selecionar lotes para venda/saída usando lógica FEFO (First Expiry, First Out)
     * Retorna o(s) lote(s) necessário para atender a quantidade
     * 
     * @param int $produtoId
     * @param float $quantidade
     * @param int|null $armazemId (opcional, se quiser filtrar por armazém)
     * @return array
     * @throws \Exception
     */
    public function selecionarLotesParaVenda($produtoId, $quantidade, $armazemId = null)
    {
        // Buscar produto
        $produto = Produto::findOrFail($produtoId);
        
        // Se produto não controla validade, retorna vazio (usa stock simples)
        if (!$produto->controla_validade) {
            return [];
        }
        
        // Query base para buscar lotes válidos
        $query = LoteProduto::where('produto_id', $produtoId)
            ->where('status', 'activo')
            ->where('qtd_atual', '>', 0)
            ->where('data_validade', '>=', Carbon::today())
            ->orderBy('data_validade', 'asc')  // Primeiro o que vence antes
            ->orderBy('created_at', 'asc');     // Depois o mais antigo
        
        // Se tem armazém, filtrar (se sua estrutura suporta)
        if ($armazemId && $this->lotesTemArmazem()) {
            $query->where('armazem_id', $armazemId);
        }
        
        $lotes = $query->get();
        
        if ($lotes->isEmpty()) {
            throw new \Exception("Sem stock disponível para o produto {$produto->nome}");
        }
        
        $lotesSelecionados = [];
        $quantidadeRestante = $quantidade;
        
        foreach ($lotes as $lote) {
            if ($quantidadeRestante <= 0) break;
            
            $quantidadeLote = min($lote->quantidade_actual, $quantidadeRestante);
            $diasRestantes = Carbon::today()->diffInDays($lote->data_validade);
            
            $lotesSelecionados[] = [
                'lote_id' => $lote->id,
                'codigo_lote' => $lote->codigo_lote,
                'quantidade' => $quantidadeLote,
                'data_validade' => $lote->data_validade->format('Y-m-d'),
                'dias_restantes' => $diasRestantes,
                'status_validade' => $this->getStatusValidade($lote, $diasRestantes),
                'preco_custo' => $lote->preco_custo ?? null,
                'localizacao' => $lote->localizacao_armazem ?? null
            ];
            
            $quantidadeRestante -= $quantidadeLote;
        }
        
        if ($quantidadeRestante > 0) {
            throw new \Exception("Stock insuficiente. Faltam {$quantidadeRestante} unidades do produto {$produto->nome}");
        }
        
        return $lotesSelecionados;
    }
    
    /**
     * Selecionar um lote específico para saída (usado em transferências ou quebras)
     * 
     * @param int $produtoId
     * @param string $codigoLote
     * @param float $quantidade
     * @return array|null
     */
    public function selecionarLoteEspecifico($produtoId, $codigoLote, $quantidade)
    {
        $lote = LoteProduto::where('produto_id', $produtoId)
            ->where('codigo_lote', $codigoLote)
            ->where('status', 'activo')
            ->where('qtd_atual', '>=', $quantidade)
            ->where('data_validade', '>=', Carbon::today())
            ->first();
        
        if (!$lote) {
            throw new \Exception("Lote {$codigoLote} não encontrado ou com stock insuficiente");
        }
        
        $diasRestantes = Carbon::today()->diffInDays($lote->data_validade);
        
        return [
            'lote_id' => $lote->id,
            'codigo_lote' => $lote->codigo_lote,
            'quantidade' => $quantidade,
            'data_validade' => $lote->data_validade->format('Y-m-d'),
            'dias_restantes' => $diasRestantes,
            'status_validade' => $this->getStatusValidade($lote, $diasRestantes),
            'quantidade_disponivel' => $lote->qtd_atual
        ];
    }
    
    /**
     * Dar baixa nos lotes após venda/saída
     * 
     * @param int $produtoId
     * @param array $lotesUtilizados (retornado do selecionarLotesParaVenda)
     * @param int|null $movimentoId (opcional, para referência)
     * @return bool
     */
    public function darBaixaLotes($produtoId, array $lotesUtilizados, $movimentoId = null)
    {
        DB::beginTransaction();
        
        try {
            foreach ($lotesUtilizados as $loteInfo) {
                $lote = LoteProduto::findOrFail($loteInfo['lote_id']);
                
                // Dar baixa
                $lote->quantidade_actual -= $loteInfo['quantidade'];
                
                // Se acabou, marcar como consumido
                if ($lote->quantidade_actual <= 0) {
                    $lote->status = 'consumido';
                    $lote->data_consumo = now();
                }
                
                $lote->save();
                
                // Se tiver movimento, atualizar referência
                if ($movimentoId) {
                    MovimentoStock::where('id', $movimentoId)
                        ->where('lote_id', $lote->id)
                        ->update([
                            'quantidade_baixada' => $loteInfo['quantidade'],
                            'data_baixa' => now()
                        ]);
                }
                
                // Verificar se precisa gerar alerta após baixa
                $this->verificarEmitirAlerta($lote);
            }
            
            // Atualizar stock consolidado do produto
            $this->atualizarStockConsolidadoProduto($produtoId);
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao dar baixa em lotes: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Criar ou atualizar lote para entrada de produtos
     * 
     * @param int $produtoId
     * @param array $dadosLote
     * @param float $quantidade
     * @param string $origem
     * @return LoteProduto
     */
    public function criarOuAtualizarLote($produtoId, array $dadosLote, $quantidade, $origem = 'manual')
    {
        DB::beginTransaction();
        
        try {
            $produto = Produto::findOrFail($produtoId);
            
            // Verificar se produto controla validade
            if (!$produto->controla_validade) {
                throw new \Exception("Produto {$produto->nome} não controla validade");
            }
            
            // Validar dados do lote
            $this->validarDadosLote($dadosLote);
            
            // Verificar se lote já existe
            $lote = LoteProduto::where('produto_id', $produtoId)
                ->where('codigo_lote', $dadosLote['codigo_lote'])
                ->first();
            
            if ($lote) {
                // Lote existe - adicionar quantidade
                $lote->quantidade_actual += $quantidade;
                
                // Atualizar data de validade se a nova for mais curta
                $novaDataValidade = Carbon::parse($dadosLote['data_validade']);
                if ($novaDataValidade->lt($lote->data_validade)) {
                    $lote->data_validade = $novaDataValidade;
                }
                
                // Atualizar localização se fornecida
                if (isset($dadosLote['localizacao'])) {
                    $lote->localizacao_armazem = $dadosLote['localizacao'];
                }
                
                $lote->save();
                
                Log::info("Lote atualizado", [
                    'lote' => $lote->codigo_lote,
                    'nova_quantidade' => $lote->quantidade_actual,
                    'origem' => $origem
                ]);
            } else {
                // Criar novo lote
                $lote = LoteProduto::create([
                    'produto_id' => $produtoId,
                    'codigo_lote' => $dadosLote['codigo_lote'],
                    'data_fabricacao' => $dadosLote['data_fabricacao'] ?? null,
                    'data_validade' => $dadosLote['data_validade'],
                    'qtd_atual' => $quantidade,
                    'qtd_inicial' => $quantidade,
                    'localizacao_armazem' => $dadosLote['localizacao'] ?? null,
                    'status' => 'activo',
                    'observacao' => "Criado via: {$origem}",
                    'data_entrada' => now()
                ]);
                
                Log::info("Novo lote criado", [
                    'lote' => $lote->codigo_lote,
                    'validade' => $lote->data_validade,
                    'quantidade' => $lote->quantidade_actual,
                    'origem' => $origem
                ]);
            }
            
            // Verificar se precisa gerar alerta (lote perto de vencer)
            $this->verificarEmitirAlerta($lote);
            
            // Atualizar stock consolidado do produto
            $this->atualizarStockConsolidadoProduto($produtoId);
            
            DB::commit();
            return $lote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Verificar e emitir alerta se o lote está perto de vencer
     * 
     * @param LoteProduto $lote
     * @return bool
     */
    public function verificarEmitirAlerta($lote)
    {
        try {
            $produto = $lote->produto;
            
            // Buscar configuração do produto
            $config = ConfigValidacaoProduto::where('produto_id', $produto->id)->first();
            if (!$config) {
                // Configuração padrão
                $diasAlertaPrecoce = 30;
                $diasAlertaCritico = 7;
            } else {
                $diasAlertaPrecoce = $config->dias_alerta_precoce;
                $diasAlertaCritico = $config->dias_alerta_critico;
            }
            
            $diasRestantes = Carbon::today()->diffInDays($lote->data_validade);
            
            // Verificar se já existe alerta não lido para esse lote
            $alertaExistente = NotificacaoValidade::where('lote_id', $lote->id)
                ->where('lida', false)
                ->where('tipo', '!=', 'expirado')
                ->first();
            
            $tipo = null;
            $mensagem = null;
            $nivel = null;
            
            if ($diasRestantes <= 0 && $lote->quantidade_actual > 0) {
                // Produto expirado
                $tipo = 'expirado';
                $nivel = 'danger';
                $mensagem = "❌ PRODUTO EXPIRADO: {$produto->nome} (Lote: {$lote->codigo_lote}) expirou em {$lote->data_validade->format('d/m/Y')}! Estoque: {$lote->quantidade_actual} unidades. Remova do estoque imediatamente.";
                
            } elseif ($diasRestantes <= $diasAlertaCritico && $diasRestantes > 0) {
                // Alerta crítico
                $tipo = 'critico';
                $nivel = 'warning';
                $mensagem = "⚠️ LOTE CRÍTICO: {$produto->nome} (Lote: {$lote->codigo_lote}) vai vencer em {$diasRestantes} dias! Estoque atual: {$lote->quantidade_actual} unidades. Priorizar venda ou devolução.";
                
            } elseif ($diasRestantes <= $diasAlertaPrecoce && $diasRestantes > $diasAlertaCritico) {
                // Alerta precoce
                $tipo = 'precoce';
                $nivel = 'info';
                $mensagem = "📢 ATENÇÃO: {$produto->nome} (Lote: {$lote->codigo_lote}) vai vencer em {$diasRestantes} dias. Estoque atual: {$lote->quantidade_actual} unidades. Planejar ação comercial.";
            }
            
            if ($tipo && $mensagem && !$alertaExistente) {
                $alerta = NotificacaoValidade::create([
                    'lote_id' => $lote->id,
                    'tipo' => $tipo,
                    'nivel' => $nivel,
                    'mensagem' => $mensagem,
                    'dias_restantes' => $diasRestantes,
                    'quantidade_afetada' => $lote->quantidade_actual,
                    'data_envio' => now(),
                    'lida' => false
                ]);
                
                Log::warning('Alerta de validade gerado', [
                    'lote_id' => $lote->id,
                    'produto' => $produto->nome,
                    'tipo' => $tipo,
                    'dias_restantes' => $diasRestantes
                ]);
                
                // Disparar evento para WebSocket (se configurado)
                $this->dispararEventoWebSocket($alerta);
                
                // Enviar email se for crítico
                if ($tipo === 'critico' || $tipo === 'expirado') {
                    $this->enviarEmailAlerta($alerta);
                }
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Erro ao verificar alerta de validade: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar todos os lotes ativos (task agendada)
     * 
     * @return array
     */
    public function verificarTodosLotes()
    {
        $resultados = [
            'total_analisados' => 0,
            'alertas_gerados' => 0,
            'lotes_expirados' => 0,
            'lotes_criticos' => 0,
            'detalhes' => []
        ];
        
        try {
            // Buscar todos lotes ativos com produto
            $lotes = LoteProduto::where('status', 'activo')
                ->where('qtd_atual', '>', 0)
                ->with('produto.configuracaoValidade')
                ->get();
            
            $resultados['total_analisados'] = $lotes->count();
            
            foreach ($lotes as $lote) {
                // Verificar se já expirou
                if ($lote->data_validade < Carbon::today()) {
                    $lote->status = 'expirado';
                    $lote->data_expiracao = now();
                    $lote->save();
                    $resultados['lotes_expirados']++;
                    $resultados['detalhes'][] = "Lote {$lote->codigo_lote} marcado como expirado";
                }
                
                // Gerar alerta se necessário
                $alertaGerado = $this->verificarEmitirAlerta($lote);
                if ($alertaGerado) {
                    $resultados['alertas_gerados']++;
                    
                    // Contar críticos
                    if ($lote->dias_restantes <= 7 && $lote->dias_restantes > 0) {
                        $resultados['lotes_criticos']++;
                    }
                }
            }
            
            Log::info('Verificação de validade concluída', $resultados);
            
            return $resultados;
            
        } catch (\Exception $e) {
            Log::error('Erro na verificação de validade: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar alertas não lidos
     * 
     * @param int|null $produtoId
     * @param string|null $tipo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function buscarAlertas($produtoId = null, $tipo = null)
    {
        $query = NotificacaoValidade::with(['lote.produto'])
            ->where('lida', false)
            ->orderBy('created_at', 'desc');
        
        if ($produtoId) {
            $query->whereHas('lote', function ($q) use ($produtoId) {
                $q->where('produto_id', $produtoId);
            });
        }
        
        if ($tipo) {
            $query->where('tipo', $tipo);
        }
        
        return $query->get();
    }
    
    /**
     * Marcar alerta como lido
     * 
     * @param string $alertaId
     * @param int|null $utilizadorId
     * @return bool
     */
    public function marcarAlertaComoLido($alertaId, $utilizadorId = null)
    {
        $alerta = NotificacaoValidade::findOrFail($alertaId);
        $alerta->lida = true;
        $alerta->data_leitura = now();
        $alerta->lida_por = $utilizadorId;
        $alerta->save();
        
        return true;
    }
    
    /**
     * Obter estatísticas de validade para dashboard
     * 
     * @param int|null $empresaId
     * @return array
     */
    public function getEstatisticasValidade($empresaId = null)
    {
        $query = LoteProduto::where('status', 'activo')
            ->where('quantidade_actual', '>', 0)
            ->with('produto');
        
        if ($empresaId) {
            $query->whereHas('produto', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            });
        }
        
        $lotes = $query->get();
        
        $stats = [
            'total_lotes' => $lotes->count(),
            'total_quantidade' => $lotes->sum('qtd_atual'),
            'criticos' => [
                'lotes' => 0,
                'quantidade' => 0,
                'produtos' => []
            ],
            'precoces' => [
                'lotes' => 0,
                'quantidade' => 0,
                'produtos' => []
            ],
            'expirados' => [
                'lotes' => 0,
                'quantidade' => 0,
                'produtos' => []
            ],
            'valor_em_risco' => 0
        ];
        
        foreach ($lotes as $lote) {
            $diasRestantes = Carbon::today()->diffInDays($lote->data_validade);
            $status = $this->getStatusValidade($lote, $diasRestantes);
            $valorLote = $lote->quantidade_actual * ($lote->produto->preco_custo ?? $lote->produto->preco);
            
            if ($status === 'critico') {
                $stats['criticos']['lotes']++;
                $stats['criticos']['quantidade'] += $lote->quantidade_actual;
                $stats['criticos']['produtos'][] = [
                    'produto' => $lote->produto->nome,
                    'lote' => $lote->codigo_lote,
                    'quantidade' => $lote->quantidade_actual,
                    'validade' => $lote->data_validade->format('d/m/Y'),
                    'dias' => $diasRestantes
                ];
                $stats['valor_em_risco'] += $valorLote;
                
            } elseif ($status === 'precoce') {
                $stats['precoces']['lotes']++;
                $stats['precoces']['quantidade'] += $lote->quantidade_actual;
                $stats['precoces']['produtos'][] = [
                    'produto' => $lote->produto->nome,
                    'lote' => $lote->codigo_lote,
                    'quantidade' => $lote->quantidade_actual,
                    'validade' => $lote->data_validade->format('d/m/Y'),
                    'dias' => $diasRestantes
                ];
                
            } elseif ($diasRestantes <= 0) {
                $stats['expirados']['lotes']++;
                $stats['expirados']['quantidade'] += $lote->quantidade_actual;
                $stats['expirados']['produtos'][] = [
                    'produto' => $lote->produto->nome,
                    'lote' => $lote->codigo_lote,
                    'quantidade' => $lote->quantidade_actual,
                    'validade' => $lote->data_validade->format('d/m/Y'),
                    'dias_atraso' => abs($diasRestantes)
                ];
                $stats['valor_em_risco'] += $valorLote;
            }
        }
        
        return $stats;
    }
    
    /**
     * Relatório de produtos a expirar
     * 
     * @param int $dias
     * @param int|null $empresaId
     * @return array
     */
    public function relatorioProdutosAExpirar($dias = 30, $empresaId = null)
    {
        $query = LoteProduto::where('status', 'activo')
            ->where('qtd_atual', '>', 0)
            ->where('data_validade', '<=', Carbon::today()->addDays($dias))
            ->with(['produto' => function($q) use ($empresaId) {
                if ($empresaId) {
                    $q->where('empresa_id', $empresaId);
                }
            }, 'produto.configuracaoValidade'])
            ->orderBy('data_validade', 'asc');
        
        $lotes = $query->get();
        
        $relatorio = [
            'data_geracao' => now()->format('Y-m-d H:i:s'),
            'periodo_dias' => $dias,
            'data_limite' => Carbon::today()->addDays($dias)->format('Y-m-d'),
            'total_produtos' => $lotes->unique('produto_id')->count(),
            'total_lotes' => $lotes->count(),
            'total_quantidade' => $lotes->sum('qtd_atual'),
            'valor_total_risco' => 0,
            'itens' => []
        ];
        
        foreach ($lotes as $lote) {
            $diasRestantes = Carbon::today()->diffInDays($lote->data_validade);
            $valorLote = $lote->qtd_atual * ($lote->produto->preco_custo ?? $lote->produto->preco);
            $relatorio['valor_total_risco'] += $valorLote;
            
            $relatorio['itens'][] = [
                'produto_id' => $lote->produto_id,
                'produto_nome' => $lote->produto->nome,
                'produto_codigo' => $lote->produto->codigo_produto,
                'lote_codigo' => $lote->codigo_lote,
                'lote_validade' => $lote->data_validade->format('d/m/Y'),
                'dias_restantes' => $diasRestantes,
                'status' => $this->getStatusValidade($lote, $diasRestantes),
                'quantidade' => $lote->qtd_atual,
                'valor_unitario' => $lote->produto->preco_custo ?? $lote->produto->preco,
                'valor_total' => $valorLote,
                'localizacao' => $lote->localizacao_armazem,
                'sugestao_acao' => $this->getSugestaoAcao($diasRestantes)
            ];
        }
        
        return $relatorio;
    }
    
    /**
     * Obter sugestão de ação baseada nos dias restantes
     * 
     * @param int $diasRestantes
     * @return string
     */
    private function getSugestaoAcao($diasRestantes)
    {
        if ($diasRestantes <= 0) {
            return "REMOVER IMEDIATAMENTE - Produto expirado, descartar ou devolver ao fornecedor";
        } elseif ($diasRestantes <= 7) {
            return "URGENTE - Promoção relâmpago ou devolução ao fornecedor";
        } elseif ($diasRestantes <= 15) {
            return "PRIORIDADE ALTA - Oferta combinada ou desconto progressivo";
        } elseif ($diasRestantes <= 30) {
            return "ATENÇÃO - Campanha de liquidação ou doação fiscal";
        } else {
            return "MONITORAR - Rotação normal de estoque";
        }
    }
    
    /**
     * Atualizar stock consolidado do produto (soma de todos lotes)
     * 
     * @param int $produtoId
     * @return void
     */
    public function atualizarStockConsolidadoProduto($produtoId)
    {
        $totalStock = LoteProduto::where('id_produto', $produtoId)
            ->where('status', 'activo')
            ->where('data_validade', '>=', Carbon::today())
            ->sum('quantidade_actual');
        
        Produto::where('id', $produtoId)->update([
            'stock' => $totalStock,
            'updated_at' => now()
        ]);
    }
    
    /**
     * Validar dados do lote
     * 
     * @param array $dadosLote
     * @throws \Exception
     */
    private function validarDadosLote($dadosLote)
    {
        if (empty($dadosLote['codigo_lote'])) {
            throw new \Exception("Código do lote é obrigatório");
        }
        
        if (empty($dadosLote['data_validade'])) {
            throw new \Exception("Data de validade é obrigatória");
        }
        
        $dataValidade = Carbon::parse($dadosLote['data_validade']);
        
        if ($dataValidade < Carbon::today()) {
            throw new \Exception("Não é possível dar entrada em produto com data de validade vencida ({$dataValidade->format('d/m/Y')})");
        }
        
        // Limitar validade muito longa (ex: 5 anos)
        if ($dataValidade > Carbon::today()->addYears(5)) {
            throw new \Exception("Data de validade muito distante (>5 anos). Verifique se está correta.");
        }
    }
    
    /**
     * Obter status da validade
     * 
     * @param LoteProduto $lote
     * @param int $diasRestantes
     * @return string
     */
    private function getStatusValidade($lote, $diasRestantes)
    {
        if ($diasRestantes <= 0) return 'expirado';
        
        $config = $lote->produto->configuracaoValidade ?? null;
        
        if ($config) {
            if ($diasRestantes <= $config->dias_alerta_critico) return 'critico';
            if ($diasRestantes <= $config->dias_alerta_precoce) return 'precoce';
        } else {
            // Valores padrão
            if ($diasRestantes <= 7) return 'critico';
            if ($diasRestantes <= 30) return 'precoce';
        }
        
        return 'normal';
    }
    
    /**
     * Disparar evento WebSocket
     * 
     * @param NotificacaoValidade $alerta
     */
    private function dispararEventoWebSocket($alerta)
    {
        try {
            // Se você usa Laravel Broadcasting
            // event(new \App\Events\AlertaValidadeEvent($alerta));
            
            // Log apenas para debug
            Log::debug('WebSocket event disparado', ['alerta_id' => $alerta->id]);
            
        } catch (\Exception $e) {
            Log::warning('Erro ao disparar evento WebSocket: ' . $e->getMessage());
        }
    }
    
    /**
     * Enviar email de alerta
     * 
     * @param NotificacaoValidade $alerta
     */
    private function enviarEmailAlerta($alerta)
    {
        try {
            // Configurar emails dos responsáveis
            $emails = config('validade.emails_notificacao', []);
            
            if (empty($emails)) {
                return;
            }
            
            // Exemplo de envio (ajuste conforme seu Mail)
            // Mail::to($emails)->send(new AlertaValidadeMail($alerta));
            
            Log::info('Email de alerta enviado', [
                'destinatarios' => $emails,
                'alerta_id' => $alerta->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de alerta: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar se lotes têm suporte a armazém
     * 
     * @return bool
     */
    private function lotesTemArmazem()
    {
        return Schema::hasColumn('lotes_produto', 'armazem_id');
    }
    
    /**
     * Ajustar estoque de inventário (sobras ou quebras)
     * 
     * @param int $produtoId
     * @param string $codigoLote
     * @param float $quantidadeAjuste (positivo para sobra, negativo para quebra)
     * @param string $motivo
     * @return LoteProduto|null
     */
    public function ajustarLoteInventario($produtoId, $codigoLote, $quantidadeAjuste, $motivo)
    {
        DB::beginTransaction();
        
        try {
            $lote = LoteProduto::where('produto_id', $produtoId)
                ->where('codigo_lote', $codigoLote)
                ->first();
            
            if (!$lote && $quantidadeAjuste > 0) {
                // Sobra sem lote existente - criar novo lote
                $lote = LoteProduto::create([
                    'produto_id' => $produtoId,
                    'codigo_lote' => $codigoLote,
                    'data_validade' => Carbon::today()->addDays(30), // Valor padrão
                    'qtd_atual' => $quantidadeAjuste,
                    'qtd_inicial' => $quantidadeAjuste,
                    'status' => 'activo',
                    'observacao' => "Sobra de inventário: {$motivo}"
                ]);
            } elseif ($lote) {
                $lote->qtd_atual += $quantidadeAjuste;
                
                if ($lote->qtd_atual <= 0) {
                    $lote->status = 'consumido';
                }
                
                $lote->save();
            } else {
                throw new \Exception("Lote {$codigoLote} não encontrado para dar baixa");
            }
            
            // Atualizar stock consolidado
            $this->atualizarStockConsolidadoProduto($produtoId);
            
            DB::commit();
            return $lote;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}