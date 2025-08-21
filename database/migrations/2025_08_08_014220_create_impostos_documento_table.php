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
        Schema::create('impostos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')
                ->constrained('documentos')
                ->onDelete('cascade');
            // $table->foreignId('tipo_taxa_iva_id')
            //     ->constrained('tipo_taxa_iva')
            //     ->onDelete('cascade');
            $table->decimal('taxa', 5, 2);
            $table->string('codigo', 20)->nullable();
            $table->boolean('isento')->default(false);
            $table->string('motivo_isencao', 255)->nullable();
            $table->decimal('incidencia', 10, 2)->default(0);
            $table->decimal('base', 10, 2)->default(0);
            $table->decimal('imposto', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
             $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impostos_documento');
    }
};
