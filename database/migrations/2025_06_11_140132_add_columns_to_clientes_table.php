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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('pais')->default('AO'); // Angola como padrão
            $table->unsignedBigInteger('gestor_id')->nullable(); // ID do gestor de conta (relacional)
            $table->string('vencimento')->nullable()->default('A Pronto'); // Condição de pagamento

            $table->string('telemovel')->nullable();

            $table->boolean('fatura_eletronica')->default(false);
            $table->string('website')->nullable();

            $table->unsignedBigInteger('grupo_preco_id')->nullable(); // Relacional com tabela de grupos
            $table->text('observacoes')->nullable();

            $table->boolean('faz_retencao')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            //
        });
    }
};
