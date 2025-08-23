<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tipo_clientes')->insert([
            ['descricao' => 'Singular', 'empresa_id' => 1, 'utilizador_id' => 1],
            ['descricao' => 'Coletivo', 'empresa_id' => 1, 'utilizador_id' => 1] 
        ]);
    }
}
