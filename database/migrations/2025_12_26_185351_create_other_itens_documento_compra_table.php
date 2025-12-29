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
        Schema::create('other_itens_documento_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documento_compra_id'); // referência sem relação direta
            $table->string('nome');
            $table->decimal('preco_custo', 20, 2);
            $table->string('descricao')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('desconto_percent', 5, 2)->default(0);
            $table->decimal('desconto_fixo', 20, 2)->default(0);
            $table->decimal('iva_percent', 5, 2)->default(0);
            $table->decimal('total', 20, 2);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_itens_documento_compra');
    }
};
