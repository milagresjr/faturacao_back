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
        // Utilizador::factory(10)->create();

        Utilizador::factory()->create([
            'name' => 'Test Utilizador',
            'email' => 'test@example.com',
        ]);
    }
}
