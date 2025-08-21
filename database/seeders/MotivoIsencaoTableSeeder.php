<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivoIsencaoTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('motivo_isencao')->insert([
            ['taxa' => 0, 'taxa_retorno' => 0, 'codigo' => 'M00', 'motivo' => 'Regime Transitório', 'texto' => 'OUT', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 0, 'codigo' => 'M04', 'motivo' => 'IVA – Regime de não sujeição', 'texto' => 'OUT', 'alteracao_manual' => 0],
            ['taxa' => 2, 'taxa_retorno' => 100, 'codigo' => 'OU', 'motivo' => 'Regime especial', 'texto' => 'OUT', 'alteracao_manual' => 1],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'NA', 'motivo' => 'NA', 'texto' => 'NA', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M00', 'motivo' => 'IVA - Regime Simplificado', 'texto' => 'OUT', 'alteracao_manual' => 0],
            ['taxa' => 14, 'taxa_retorno' => 100, 'codigo' => 'NOR', 'motivo' => 'Taxa normal', 'texto' => 'NOR', 'alteracao_manual' => 1],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'NS', 'motivo' => 'Transmissão de bens e serviço não sujeita', 'texto' => 'OUT', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'RED', 'motivo' => 'IVA - Regime de Exclusão', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'NS', 'motivo' => 'IVA - Regime de Exclusão', 'texto' => 'IVA - Regime de Exclusão', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M01', 'motivo' => 'Isento nos termos da alínea a) do no1 do art', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M02', 'motivo' => 'Isento nos termos da alínea b) do no1 do art', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M03', 'motivo' => 'Isento nos termos da alínea c) do no1 do art', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 5, 'taxa_retorno' => 100, 'codigo' => 'M10', 'motivo' => 'Isento Artigo 12.º a) do CIVA', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 5, 'taxa_retorno' => 100, 'codigo' => 'M11', 'motivo' => 'Isento Artigo 12.º b) do CIVA', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 5, 'taxa_retorno' => 100, 'codigo' => 'M12', 'motivo' => 'Isento Artigo 12.º c) do CIVA', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 5, 'taxa_retorno' => 100, 'codigo' => 'M13', 'motivo' => 'Isento Artigo 12.º d) do CIVA', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M14', 'motivo' => 'Isento Artigo 12.º e) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M15', 'motivo' => 'Isento Artigo 12.º f) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M16', 'motivo' => 'Isento Artigo 12.º g) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M17', 'motivo' => 'Isento Artigo 12.º h) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M18', 'motivo' => 'Isento Artigo 12.º i) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M19', 'motivo' => 'Isento Artigo 12.º j) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M20', 'motivo' => 'Isento Artigo 12.º k) do CIVA', 'texto' => 'RED', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M30', 'motivo' => 'Isento Artigo 15.º 1 a) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M31', 'motivo' => 'Isento Artigo 15.º 1 b) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M32', 'motivo' => 'Isento Artigo 15.º 1 c) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M33', 'motivo' => 'Isento Artigo 15.º 1 d) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M34', 'motivo' => 'Isento Artigo 15.º 1 e) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M35', 'motivo' => 'Isento Artigo 15.º 1 f) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M36', 'motivo' => 'Isento Artigo 15.º 1 g) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M37', 'motivo' => 'Isento Artigo 15.º 1 h) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
            ['taxa' => 0, 'taxa_retorno' => 100, 'codigo' => 'M38', 'motivo' => 'Isento Artigo 15.º 1 i) do CIVA', 'texto' => 'ISE', 'alteracao_manual' => 0],
        ]);
    }
}
