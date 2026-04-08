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
        Schema::create('lotes_produto', function (Blueprint $table) {
            $table->id(); // ← ÚNICO auto_increment
            $table->unsignedBigInteger('produto_id');
            $table->string('codigo_lote', 50);
            $table->date('data_fabricacao')->nullable();
            $table->date('data_validade');

            // ← CORREÇÃO: NÃO usar auto_increment, apenas unsigned integer
            $table->unsignedInteger('qtd_atual')->default(0);
            $table->unsignedInteger('qtd_inicial')->default(0);

            $table->enum('status', ['activo', 'expirado', 'bloqueado', 'consumido'])
                ->default('activo');
            $table->text('observacao')->nullable();
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->foreign('produto_id')
                ->references('id')
                ->on('produtos')
                ->onDelete('cascade');

            $table->unique(['produto_id', 'codigo_lote']);
            $table->index('data_validade');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes_produto');
    }
};
