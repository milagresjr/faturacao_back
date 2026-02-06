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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnDelete();

            $table->foreignId('armazem_id')
                ->constrained('armazens')
                ->cascadeOnDelete();

            // Quantidade atual em stock
            $table->integer('stock_atual')->default(0);

            // Limites de controlo
            $table->integer('stock_min')->default(0);
            $table->integer('stock_max')->nullable();
            $table->integer('stock_ideal')->nullable();

            // Controle
            $table->timestamps();

            // Garante 1 stock por produto + armazém
            $table->unique(['produto_id', 'armazem_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
