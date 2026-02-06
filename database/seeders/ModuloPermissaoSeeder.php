<?php

namespace Database\Seeders;

use App\Models\ModuloPermissao;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModuloPermissaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $modulos = [
            'Produtos',
            'Documentos',
            'Clientes',
            'Relatórios',
            'Compras',
            'Exportar',
            'Stock',
            'Configuração'
        ];

        foreach ($modulos as $modulo) {
            ModuloPermissao::create([
                'nome' => $modulo,
            ]);
        }
       
    }
}
