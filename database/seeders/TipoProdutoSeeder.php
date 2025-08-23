<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoProdutoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         DB::table('tipo_produtos')->insert([
            ['nome' => 'Produto', 'descricao' => '', 'empresa_id' => 1, 'utilizador_id' => 1],
            ['nome' => 'Serviço', 'descricao' => '', 'empresa_id' => 1, 'utilizador_id' => 1] 
        ]);
    }
}
