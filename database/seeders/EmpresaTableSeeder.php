<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpresaTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('empresas')->insert([
                'nome' => 'Softseven', 
                'email' => 'geral@sofyseven.ao', 
                'nif' => '9999999999', 
                'telefone' => '941608052',
                'morada' => 'Luanda, Camama',
                'logo' => '',
                'indicativo_fatura' => 'SFSV'
            ]);
    }
}
