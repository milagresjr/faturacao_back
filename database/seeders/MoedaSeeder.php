<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Moeda;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MoedaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            $existe = Moeda::where('empresa_id', $empresa->id)
                ->where('codigo', 'AKZ')
                ->first();

            if (!$existe) {
                Moeda::create([
                    'empresa_id' => $empresa->id,
                    'codigo' => 'AKZ',
                    'nome' => 'Kwanza',
                    'simbolo' => 'Kz',
                    'casas_decimais' => 2,
                    'predefinida' => true,
                    'estado' => true,
                ]);

                // Garante que apenas o Kwanza fica como predefinida
                Moeda::where('empresa_id', $empresa->id)
                    ->where('codigo', '!=', 'AKZ')
                    ->update(['predefinida' => false]);
            }
        }
    }
}