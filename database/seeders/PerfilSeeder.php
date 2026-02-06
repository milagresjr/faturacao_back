<?php

namespace Database\Seeders;

use App\Models\Perfil;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfilSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $perfis = [
            ['nome' => 'Administrador', 'descricao' => 'Acesso total ao sistema'],
            ['nome' => 'Gerente de Loja', 'descricao' => 'Acesso ao módulo de gestão de loja'],
            ['nome' => 'Operador de Caixa', 'descricao' => 'Acesso limitado ao módulo de vendas'],
            ['nome' => 'Básico', 'descricao' => 'Acesso básico ao sistema']
        ];

        foreach ($perfis as $perfil) {
            Perfil::firstOrCreate($perfil);
        }
    }
}
