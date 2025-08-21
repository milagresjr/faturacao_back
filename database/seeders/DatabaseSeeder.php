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
           TipoTaxaIvaSeeder::class,
           PerfilSeeder::class,
           EmpresaTableSeeder::class,
           UtilizadorSeeder::class,
           MotivoIsencaoTableSeeder::class,
           BancosSeeder::class
        ]);
    }
}
