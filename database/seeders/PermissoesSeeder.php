<?php

namespace Database\Seeders;

use App\Models\Permissao;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissoesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Limpa a tabela primeiro
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('permissoes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $permissoes = [
            ['nome' => 'gestao_produtos', 'descricao' => 'Permite gerir produtos'],
            ['nome' => 'criar_produtos', 'descricao' => 'Permite criar novos produtos'],
            ['nome' => 'gestao_stock', 'descricao' => 'Permite gerir stock'],
            ['nome' => 'consulta_stock', 'descricao' => 'Permite consultar o stock'],
            ['nome' => 'documentos', 'descricao' => 'Permite aceder aos documentos'],
            ['nome' => 'criar_documentos', 'descricao' => 'Permite criar documentos'],
            ['nome' => 'criar_nota_credito', 'descricao' => 'Permite criar notas de crédito'],
            ['nome' => 'emitir_anular_recibos', 'descricao' => 'Permite emitir e anular recibos'],
            ['nome' => 'gestao_clientes', 'descricao' => 'Permite gerir clientes'],
            ['nome' => 'consulta_conta_corrente', 'descricao' => 'Permite consultar conta corrente dos clientes'],
            ['nome' => 'relatorios', 'descricao' => 'Permite aceder aos relatórios'],
            ['nome' => 'relatorios_performance', 'descricao' => 'Permite aceder a relatórios de performance'],
            ['nome' => 'gestao_compras', 'descricao' => 'Permite gerir compras'],
            ['nome' => 'ver_documentos_compra', 'descricao' => 'Permite ver documentos de compra'],
            ['nome' => 'exportar', 'descricao' => 'Permite exportar dados'],
            ['nome' => 'configuracao', 'descricao' => 'Permite aceder à configuração'],
            ['nome' => 'gestao_utilizadores', 'descricao' => 'Permite gerir utilizadores'],
            ['nome' => 'gestao_perfis', 'descricao' => 'Permite gerir perfis'],
            ['nome' => 'gestao_empresas', 'descricao' => 'Permite gerir empresas'],
            ['nome' => 'configuracoes_conta', 'descricao' => 'Permite gerir configurações de conta'],
            ['nome' => 'subscricao', 'descricao' => 'Permite gerir subscrições'],
            ['nome' => 'gestao_armazens', 'descricao' => 'Permite gerir armazéns'],
            ['nome' => 'mudar_caixa', 'descricao' => 'Permite mudar caixa'],
            ['nome' => 'gestao_vendas', 'descricao' => 'Permite gerir vendas'],
            ['nome' => 'saft', 'descricao' => 'Permite aceder ao SAFT'],
            ['nome' => 'gestao_faturacao', 'descricao' => 'Permite gerir faturação'],
            ['nome' => 'emitir_saft', 'descricao' => 'Permite emitir SAFT'],
            ['nome' => 'gestao_entidades', 'descricao' => 'Permite gerir entidades'],
            ['nome' => 'gestao_armazens', 'descricao' => 'Permite gerir armazéns'],
        ];

        foreach ($permissoes as $permissao) {
            Permissao::firstOrCreate($permissao);
        }
    }
}
