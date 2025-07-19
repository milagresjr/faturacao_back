<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoTaxaIvaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tipos_taxa_iva')->insert([
            ['codigo' => 'ISENTO', 'descricao' => 'Isento', 'taxa' => 0.00],
            ['codigo' => 'RED',    'descricao' => 'Taxa Reduzida', 'taxa' => 1.00],
            ['codigo' => 'INT',    'descricao' => 'Taxa Intermédia', 'taxa' => 5.00],
            ['codigo' => 'TX7',    'descricao' => 'Taxa 7%', 'taxa' => 7.00],
            ['codigo' => 'NOR',    'descricao' => 'Taxa Normal', 'taxa' => 14.00],
        ]);
    }
}
