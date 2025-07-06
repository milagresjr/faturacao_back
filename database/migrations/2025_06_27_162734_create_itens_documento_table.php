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
        Schema::create('itens_documento', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('documento_id'); // referência sem relação direta
            $table->string('produto_nome');
            $table->string('produto_codigo')->nullable();
            $table->decimal('preco_unitario', 20, 2);
            
            $table->integer('quantidade');
            $table->decimal('desconto_percent', 5, 2)->default(0);
            $table->decimal('desconto_fixo', 20, 2)->default(0);
            $table->decimal('iva_percent', 5, 2)->default(0);
            $table->decimal('total', 20, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itens_documento');
    }
};
