<?php

namespace App\Console\Commands;

use App\Models\AlertaStock;
use App\Models\Stock;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerificarStockMinimo extends Command
{
    protected $signature = 'stock:verificar-stock-minimo';
    protected $description = 'Verifica produtos com stock abaixo do mínimo e envia SMS';

    public function handle()
    {
        Log::info('Scheduler de stock mínimo EXECUTADO');

        // Busca stocks críticos (produto + armazém)
        $stocksCriticos = Stock::whereColumn('stock_atual', '<=', 'stock_min')
            ->whereHas('movimentos') // só pega stocks com pelo menos 1 movimentação
            ->get();

        $smsService = app(SmsService::class);

        foreach ($stocksCriticos as $stock) {

            Log::info('Stock baixo detectado', [
                'produto_id' => $stock->produto_id,
                'produto_nome' => $stock->produto->nome,
                'armazem_id' => $stock->armazem_id,
                'armazem_nome' => $stock->armazem->nome,
                'stock_atual' => $stock->stock_atual,
                'stock_min' => $stock->stock_min,
            ]);

            // Verifica se já enviou alerta hoje
            $jaAlertado = AlertaStock::where('stock_id', $stock->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($jaAlertado) {
                continue; // evita SMS duplicado
            }

            // Cria registro de alerta
            $alerta = AlertaStock::create([
                'stock_id' => $stock->id,
                'produto_id' => $stock->produto_id,
                'armazem_id' => $stock->armazem_id,
                'stock_atual' => $stock->stock_atual,
                'empresa_id' => $stock->empresa_id,
            ]);

            // Envia SMS
            $smsService->enviarStockBaixo($stock);

            // Atualiza registro de alerta
            $alerta->update([
                'sms_enviado' => true,
                'enviado_em' => now()
            ]);
        }

        return Command::SUCCESS;
    }
}
