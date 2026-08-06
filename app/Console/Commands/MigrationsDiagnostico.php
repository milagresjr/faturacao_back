<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationsDiagnostico extends Command
{
    protected $signature = 'migracoes:diagnostico';

    protected $description = 'Diagnóstico da ligação e tabela de migrations usada pelo Laravel';

    public function handle()
    {
        $this->info('=== LIGAÇÃO PADRÃO (DB::table) ===');
        $this->line('connection name: ' . DB::getDefaultConnection());
        $this->line('database: ' . DB::connection()->getDatabaseName());
        $this->line('host: ' . DB::connection()->getConfig('host') . ':' . DB::connection()->getConfig('port'));
        $this->line('prefix: "' . DB::connection()->getTablePrefix() . '"');
        $this->line('migrations table config: ' . config('database.migrations.table'));

        $this->info('=== TABELAS COM "migration" NO NOME ===');
        foreach (DB::select('SHOW TABLES') as $t) {
            $name = array_values((array) $t)[0];
            if (stripos($name, 'migration') !== false) {
                $this->line('  - ' . $name);
            }
        }

        $this->info('=== REPOSITÓRIO DO MIGRATOR ===');
        $repo = app('migration.repository');
        $this->line('class: ' . get_class($repo));
        $ref = new \ReflectionObject($repo);
        foreach (['connection', 'table', 'resolver'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $val = $p->getValue($repo);
                if (is_object($val) && method_exists($val, 'getDatabaseName')) {
                    $this->line("$prop: " . get_class($val) . ' -> ' . $val->getDatabaseName());
                } elseif (is_object($val)) {
                    $this->line("$prop: " . get_class($val));
                } else {
                    $this->line("$prop: " . json_encode($val));
                }
            }
        }

        $this->info('=== TABELA migrations (via DB::table) ===');
        $this->line('count: ' . DB::table('migrations')->count());
        $rows = DB::table('migrations')->orderBy('id')->limit(5)->get();
        foreach ($rows as $r) {
            $this->line('  [' . $r->id . '] ' . $r->migration . ' batch=' . var_export($r->batch, true) . ' type=' . gettype($r->batch));
        }

        $this->info('=== REPOSITÓRIO: getRan / getMigrationBatches ===');
        $ran = $repo->getRan();
        $batches = $repo->getMigrationBatches();
        $this->line('getRan count: ' . count($ran));
        $this->line('getMigrationBatches count: ' . count($batches));
        $falta = array_diff($ran, array_keys($batches));
        $this->line('Em getRan mas NÃO em batches: ' . json_encode(array_values($falta)));
        $this->line('primeiros ran: ' . json_encode(array_slice($ran, 0, 5)));
        $this->line('primeiras batches: ' . json_encode(array_slice($batches, 0, 5)));

        return Command::SUCCESS;
    }
}