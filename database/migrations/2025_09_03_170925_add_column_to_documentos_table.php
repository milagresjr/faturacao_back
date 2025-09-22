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
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('local_entrega')->nullable();
            $table->date('data_recepcao')->nullable();
            $table->string('observacoes')->nullable();
            $table->boolean('paga')->default(false);
            $table->decimal('valor_pago', 20, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            //
        });
    }
};
