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
        Schema::table('lotes_produto', function (Blueprint $table) {
            $table->string("lote")->nullable()->after('produto_id');
            $table->string("codigo_barra")->nullable()->after('lote');
            $table->unsignedBigInteger('empresa_id')->nullable()->after('codigo_barra');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes_produto', function (Blueprint $table) {
            $table->dropColumn(['lote', 'codigo_barra', 'empresa_id']);
        });
    }
};
