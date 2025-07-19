<?php

namespace Database\Seeders;

use App\Models\Banco;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BancosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('bancos')->insert([
            [
                'descricao' => 'Banco de Fomento Angola',
                'sigla' => 'BFA',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco de Poupança e Crédito',
                'sigla' => 'BPC',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Angolano de Investimentos',
                'sigla' => 'BAI',
                'estado' => false,
            ],
            [
                'descricao' => 'Banco Sol',
                'sigla' => '',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Keve',
                'sigla' => 'KEVE',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Económico',
                'sigla' => 'BE',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco BIC',
                'sigla' => 'BIC',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Angolano de Negócios e Comércio',
                'sigla' => 'BANC',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco de Comércio e Indústria',
                'sigla' => 'BCI',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco de Desenvolvimento de Angola',
                'sigla' => 'BDA',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Millennium Atlântico',
                'sigla' => 'BMA',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco de Investimento Rural',
                'sigla' => 'BIR',
                'estado' => true,
            ],
            [
                'descricao' => 'Standard Bank de Angola',
                'sigla' => 'SB',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco de Negócios Internacional',
                'sigla' => 'BNI',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Angolano de Crédito',
                'sigla' => 'BAC',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Comercial do Huambo',
                'sigla' => 'BCH',
                'estado' => true,
            ],
            [
                'descricao' => 'Banco Caixa Geral Angola',
                'sigla' => 'BCGA',
                'estado' => true,
            ]
        ]);
    }
}
