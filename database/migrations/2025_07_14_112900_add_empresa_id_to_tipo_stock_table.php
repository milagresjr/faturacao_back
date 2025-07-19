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
        Schema::table('tipo_stock', function (Blueprint $table) {
            //$table->unsignedBigInteger('empresa_id');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipo_stock', function (Blueprint $table) {
            $table->dropColumn('empresa_id');
        });
    }
};
