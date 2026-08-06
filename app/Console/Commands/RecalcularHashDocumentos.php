<?php

namespace App\Console\Commands;

use App\Models\Documento;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalcularHashDocumentos extends Command
{
    protected $signature = 'documentos:recalcular-hash
        {--empresa-id= : Filtrar por empresa específica}
        {--force : Recalcular hash de todos os documentos, mesmo os que já têm hash válido}';

    protected $description = 'Recalcula o hash AGT de todos os documentos';

    public function handle()
    {
        $query = Documento::query();

        if ($empresaId = $this->option('empresa-id')) {
            $query->where('empresa_id', $empresaId);
        }

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('hash')
                  ->orWhere('hash', 'aheshtsjrjsryrjyrkyrkylfmcszndbgabvdkabvdkd');
            });
        }

        $total = $query->count();
        $this->info("A recalcular hash de {$total} documento(s)...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $documentos = $query->orderBy('empresa_id')->orderBy('tipo_sigla')->orderBy('id')->get();

        foreach ($documentos as $documento) {
            try {
                $hash = $this->calcularHash($documento);

                if ($hash) {
                    $documento->update(['hash' => $hash]);
                    $this->line(" [OK] #{$documento->id} {$documento->num_fatura}");
                } else {
                    $this->warn(" [FALHA] #{$documento->id} {$documento->num_fatura} - erro ao calcular hash");
                }
            } catch (\Exception $e) {
                $this->error(" [ERRO] #{$documento->id} {$documento->num_fatura} - {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Hash recalculado com sucesso.');

        return Command::SUCCESS;
    }

    private function calcularHash(Documento $documento): ?string
    {
        $invoiceDate = Carbon::parse($documento->data_emissao)->format('Y-m-d');
        $systemEntryDate = Carbon::parse($documento->created_at)->format('Y-m-d\TH:i:s');
        $grossTotal = number_format($documento->total_geral, 2, '.', '');

        $hashAnterior = Documento::where('empresa_id', $documento->empresa_id)
            ->where('tipo_sigla', $documento->tipo_sigla)
            ->whereYear('data_emissao', $documento->data_emissao->year)
            ->where('id', '<', $documento->id)
            ->orderBy('id', 'desc')
            ->value('hash') ?? '';

        $mensagem = $invoiceDate . ';' .
            $systemEntryDate . ';' .
            $documento->num_fatura . ';' .
            $grossTotal . ';' .
            $hashAnterior;

        $privateKeyPath = storage_path('app/keys/ChavePrivada.pem');

        if (!file_exists($privateKeyPath)) {
            $this->warn("Ficheiro de chave privada não encontrado: {$privateKeyPath}");
            return null;
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

        if (!$privateKey) {
            $this->warn("Erro ao carregar chave privada: " . openssl_error_string());
            return null;
        }

        $assinatura = null;
        $success = openssl_sign($mensagem, $assinatura, $privateKey, OPENSSL_ALGO_SHA1);

        if (!$success) {
            $this->warn("Erro ao assinar mensagem: " . openssl_error_string());
            return null;
        }

        return base64_encode($assinatura);
    }
}
