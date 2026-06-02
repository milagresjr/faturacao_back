<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {

            // Remove uniques antigos
            $table->dropUnique('fornecedores_nome_unique');
            $table->dropUnique('fornecedores_telefone_unique');

            // Cria uniques compostos por empresa
            $table->unique(['email', 'empresa_id'], 'fornecedores_email_empresa_unique');

            $table->unique(['nif', 'empresa_id'], 'fornecedores_nif_empresa_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {

            // Remove os compostos
            $table->dropUnique('fornecedores_email_empresa_unique');
            $table->dropUnique('fornecedores_nif_empresa_unique');

            // Restaura os antigos
            $table->unique('nome');
        });
    }
};