<?php

namespace App\Console\Commands;

use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcularStockAtual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:recalcular-stock-atual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula o stock_atual de todos os produtos a partir das movimentações';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('A recalcular stock...');

        DB::transaction(function () {

            // percorre cada combinação produto + armazém
            $pares = MovimentoStock::select('produto_id', 'armazem_id')
                ->distinct()
                ->get();

            foreach ($pares as $par) {

                $entrada = MovimentoStock::where('produto_id', $par->produto_id)
                    ->where('armazem_id', $par->armazem_id)
                    ->whereIn('operacao', ['entrada', 'entrada_inventario'])
                    ->sum('quantidade');

                $saida = MovimentoStock::where('produto_id', $par->produto_id)
                    ->where('armazem_id', $par->armazem_id)
                    ->whereIn('operacao', ['saida','nota_quebra','saida_inventario'])
                    ->sum('quantidade');

                Stock::updateOrCreate(
                    [
                        'produto_id' => $par->produto_id,
                        'armazem_id' => $par->armazem_id,
                    ],
                    [
                        'stock_atual' => $entrada - $saida,
                    ]
                );
            }
        });

        $this->info('Stock recalculado com sucesso ✅');

        return Command::SUCCESS;
    }
}
