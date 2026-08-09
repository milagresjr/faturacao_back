<?php

namespace Database\Seeders;

use App\Models\AlertaStock;
use App\Models\ConfigValidacaoProduto;
use App\Models\LoteProduto;
use App\Models\NotificacaoValidade;
use App\Models\Produto;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NotificacaoSeeder extends Seeder
{
    public function run(): void
    {
        $empresaId = 1;

        // 1) Garantir configurações de validade dos produtos que controlam validade
        $produtosValidade = Produto::where('empresa_id', $empresaId)
            ->where('controla_validade', true)
            ->get();

        $configs = 0;
        foreach ($produtosValidade as $produto) {
            $existe = ConfigValidacaoProduto::where('produto_id', $produto->id)->exists();
            if (!$existe) {
                ConfigValidacaoProduto::create([
                    'produto_id' => $produto->id,
                    'dias_alerta_precoce' => 30,
                    'dias_alerta_critico' => 7,
                ]);
                $configs++;
            }
        }
        $this->command->info("Configurações de validade garantidas: {$configs}");

        // 2 notificações de validade baseadas nos lotes ativos com stock
        NotificacaoValidade::whereHas('lote', function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId);
        })->delete();

        $lotes = LoteProduto::with('produto')
            ->where('empresa_id', $empresaId)
            ->where('status', 'activo')
            ->where('qtd_atual', '>', 0)
            ->get();

        $criadas = 0;
        foreach ($lotes as $lote) {
            $diasRestantes = Carbon::today()->diffInDays($lote->data_validade, false);

            $diasRestantes = (int) $diasRestantes;

            if ($diasRestantes > 30) {
                continue; // fora da janela de alerta
            }

            if ($diasRestantes <= 0) {
                $tipo = 'expirado';
                $nivel = 'danger';
                $mensagem = "❌ PRODUTO EXPIRADO: {$lote->produto->nome} (Lote: {$lote->codigo_lote}) expirou em {$lote->data_validade->format('d/m/Y')}! Qtd.: {$lote->qtd_atual} unidades. Remover do stock imediatamente.";
            } elseif ($diasRestantes <= 7) {
                $tipo = 'critico';
                $nivel = 'warning';
                $mensagem = "⚠️ LOTE CRÍTICO: {$lote->produto->nome} (Lote: {$lote->codigo_lote}) vai vencer em {$diasRestantes} dias. Qtd. atual: {$lote->qtd_atual} unidades. Priorizar venda.";
            } else {
                $tipo = 'precoce';
                $nivel = 'info';
                $mensagem = "📢 ATENÇÃO: {$lote->produto->nome} (Lote: {$lote->codigo_lote}) vai vencer em {$diasRestantes} dias. Qtd. atual: {$lote->qtd_atual} unidades. Planear ação comercial.";
            }

            NotificacaoValidade::create([
                'lote_id' => $lote->id,
                'tipo' => $tipo,
                'nivel' => $nivel,
                'mensagem' => $mensagem,
                'dias_restantes' => max(0, $diasRestantes),
                'quantidade_afetada' => $lote->qtd_atual,
                'data_envio' => now(),
                'lida' => false,
            ]);
            $criadas++;
        }

        // Se não há lotes aptos, criar exemplos mínimos para demonstração
        if ($criadas === 0) {
            foreach ($lotes->take(3) as $lote) {
                NotificacaoValidade::create([
                    'lote_id' => $lote->id,
                    'tipo' => 'precoce',
                    'nivel' => 'info',
                    'mensagem' => "📢 ATENÇÃO: {$lote->produto->nome} (Lote: {$lote->codigo_lote}) vai vencer em breve. Qtd. atual: {$lote->qtd_atual} unidades.",
                    'dias_restantes' => 15,
                    'quantidade_afetada' => $lote->qtd_atual,
                    'data_envio' => now()->subHours(2),
                    'lida' => false,
                ]);
            }
            $this->command->warn('Sem lotes aptos; criados exemplos de demonstração.');
        }

        $this->command->info("Notificações de validade criadas: {$criadas}");

        // 3 são deletadas as anteriores e criados alertas de stock baixo reais
        AlertaStock::where('empresa_id', $empresaId)->delete();

        $stocksBaixo = Stock::with(['produto', 'armazem'])
            ->where('empresa_id', $empresaId)
            ->whereColumn('stock_atual', '<=', 'stock_min')
            ->get();

        $alertas = 0;
        foreach ($stocksBaixo as $stock) {
            AlertaStock::create([
                'stock_id' => $stock->id,
                'produto_id' => $stock->produto_id,
                'armazem_id' => $stock->armazem_id,
                'stock_atual' => $stock->stock_atual,
                'sms_enviado' => false,
                'empresa_id' => $empresaId,
                'lida' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $alertas++;
        }

        // Exemplificar com alguns stocks não críticos, para a tabela não ficar vazia
        if ($alertas === 0) {
            $algunsStocks = Stock::with(['produto', 'armazem'])
                ->where('empresa_id', $empresaId)
                ->whereHas('movimentos')
                ->limit(3)
                ->get();

            foreach ($algunsStocks as $stock) {
                AlertaStock::create([
                    'stock_id' => $stock->id,
                    'produto_id' => $stock->produto_id,
                    'armazem_id' => $stock->armazem_id,
                    'stock_atual' => $stock->stock_atual,
                    'sms_enviado' => false,
                    'empresa_id' => $empresaId,
                    'lida' => false,
                    'created_at' => now()->subHours(3),
                    'updated_at' => now()->subHours(3),
                ]);
            }
            $this->command->warn('Sem stocks baixos; criados exemplos com stocks existentes.');
        }

        $this->command->info("Alertas de stock criados: {$alertas}");
        $this->command->info('Notificações populaças com sucesso!');
    }
}