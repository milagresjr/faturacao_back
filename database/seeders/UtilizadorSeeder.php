<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UtilizadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('utilizadores')->insert([
            'nome_pessoal' => 'Softseven', 
            'nome_de_utilizador' => 'admino', 
            'email' => 'geral@sofyseven.a', 
            'senha' => Hash::make('12345678'),
            'nivel_acesso' => 'Admin',
            'estado' => '1',
            'perfil_id' => 1,
            'empresa_id' => 1
        ]);
    }
}
