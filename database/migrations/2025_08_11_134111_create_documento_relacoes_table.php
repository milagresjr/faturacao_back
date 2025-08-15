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
        Schema::create('documento_relacoes', function (Blueprint $table) {
            $table->id();
            // Documento principal
            $table->foreignId('documento_id')
                ->constrained('documentos')
                ->cascadeOnDelete();

            // Documento relacionado
            $table->foreignId('documento_relacionado_id')
                ->constrained('documentos')
                ->cascadeOnDelete();

            // Tipo de relação (ex: pagamento, nota_credito, referencia)
            $table->string('tipo_relacao', 50);

            $table->timestamps();

            // Evitar duplicação da mesma relação
            $table->unique(
                ['documento_id', 'documento_relacionado_id', 'tipo_relacao'],
                'relacao_unica'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_relacoes');
    }
};
