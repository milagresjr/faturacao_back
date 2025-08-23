<?php

namespace Database\Seeders;

use App\Models\Utilizador;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            EmpresaTableSeeder::class,
            TipoTaxaIvaSeeder::class,
            PerfilSeeder::class,
            UtilizadorSeeder::class,
            MotivoIsencaoTableSeeder::class,
            BancosSeeder::class,
            TipoClienteSeeder::class,
            TipoProdutoSeeder::class
        ]);
    }
}
