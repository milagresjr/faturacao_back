<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationsRepar extends Command
{
    protected $signature = 'migracoes:reparar {--backup : Cria backup da tabela migrations}';

    protected $description = 'Reconstrói a tabela migrations com base nas tabelas existentes (usa a conexão real do Laravel)';

    public function handle()
    {
        $this->info('Banco conectado: ' . DB::connection()->getDatabaseName());

        $tabelas = collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])->flip();
        $this->line('Tabelas existentes: ' . count($tabelas));
        $this->line('Registos atuais em migrations: ' . DB::table('migrations')->count());

        if (! $this->confirm('Ops: usar este banco? (NÃO toca nos seus dados, só regista migrations)', false)) {
            $this->line('Abortado.');

            return Command::FAILURE;
        }

        if ($this->option('backup')) {
            $backup = 'migrations_backup_' . date('Ymd_His');
            DB::statement("CREATE TABLE `$backup` AS SELECT * FROM migrations");
            $this->info("Backup criado: $backup");
        }

        $ficheiros = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => basename($f, '.php'))
            ->values();

        $aplicadas = collect();
        $pendentes = collect();

        foreach ($ficheiros as $ficheiro) {
            $conteudo = file_get_contents(database_path('migrations/' . $ficheiro . '.php'));
            preg_match_all("/Schema::create\(\s*['\"]([^'\"]+)['\"]/", $conteudo, $cria);

            if (empty($cria[1])) {
                $aplicadas->push($ficheiro);
                continue;
            }

            $todasExistem = collect($cria[1])->every(fn ($t) => $tabelas->has($t));

            if ($todasExistem) {
                $aplicadas->push($ficheiro);
            } else {
                $pendentes->push($ficheiro);
            }
        }

        $this->line('');
        $this->info("Aplicadas: {$aplicadas->count()}");
        $this->info("Pendentes: {$pendentes->count()}");

        DB::table('migrations')->delete();
        $aplicadas->each(fn ($m) => DB::table('migrations')->insert(['migration' => $m, 'batch' => 1]));

        $this->info('Tabela migrations reconstruída com ' . DB::table('migrations')->count() . ' registos.');
        $this->line('Rode agora: php artisan migrate --force');

        return Command::SUCCESS;
    }
}