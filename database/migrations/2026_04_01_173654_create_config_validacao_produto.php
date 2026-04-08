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
        Schema::create('config_validacao_produto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->primary()->constrained('produtos')->onDelete('cascade');
            $table->integer('dias_alerta_precoce')->default(30);
            $table->integer('dias_alerta_critico')->default(7);
            $table->boolean('permitir_venda_expirado')->default(false);
            $table->boolean('exigir_lote_obrigatorio')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_validacao_produto');
    }
};
