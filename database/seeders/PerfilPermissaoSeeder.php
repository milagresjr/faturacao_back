<?php

namespace Database\Seeders;

use App\Models\Perfil;
use App\Models\Permissao;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PerfilPermissaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Perfil::where('nome', 'Administrador')->first();
        $caixa = Perfil::where('nome', 'Operador de Caixa')->first();
        $gestorLoja = Perfil::where('nome', 'Gerente de Loja')->first();
        $basico = Perfil::where('nome', 'Básico')->first();

        //Limpar tabela
        Perfil::query()->each(function ($perfil) {
            $perfil->permissoes()->detach();
        });

        if ($admin) {
            $admin->permissoes()->sync(
                Permissao::pluck('id')->toArray()
            );
        }

        if ($caixa) {
            $caixa->permissoes()->sync(
                Permissao::whereIn('nome', [
                    'gestao_entidades',
                    'gestao_clientes',
                    'gestao_vendas'
                ])->pluck('id')->toArray()
            );
        }

        if ($gestorLoja) {
            $gestorLoja->permissoes()->sync(
                Permissao::whereIn('nome', [
                    'gestao_entidades',
                    'gestao_armazens',
                    'gestao_clientes',
                    'gestao_vendas',
                    'gestao_produtos',
                    'relatorios',
                    'mudar_caixa',
                ])->pluck('id')->toArray()
            );
        }

        if($basico) {
            $basico->permissoes()->sync(
                Permissao::whereIn('nome', [
                    'gestao_vendas',
                ])->pluck('id')->toArray()
            );
        }
    }
}
