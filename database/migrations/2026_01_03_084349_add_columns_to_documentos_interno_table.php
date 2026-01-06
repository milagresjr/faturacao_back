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
        Schema::table('documentos_interno', function (Blueprint $table) {
            $table->unsignedBigInteger('armazem_origem_id');
            $table->unsignedBigInteger('armazem_destino_id');
            $table->string("armazem_origem")->nullable();
            $table->string("armazem_destino")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_interno', function (Blueprint $table) {
            $table->dropColumn('armazem_origem_id');
            $table->dropColumn('armazem_destino_id');
            $table->dropColumn('armazem_origem');
            $table->dropColumn('armazem_destino');
        });
    }
};
