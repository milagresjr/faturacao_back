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
        Schema::table('notificacoes_validade', function (Blueprint $table) {
            $table->string('nivel')->nullable()->after('tipo');
            $table->integer('dias_restantes')->nullable();
            $table->integer('quantidade_afetada')->nullable();
            $table->timestamp('data_leitura')->nullable();
            $table->unsignedBigInteger('lida_por')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificacoes_validade', function (Blueprint $table) {
            $table->dropColumn(['nivel', 'dias_restantes', 'quantidade_afetada', 'data_leitura', 'lida_por']);
        });
    }
};