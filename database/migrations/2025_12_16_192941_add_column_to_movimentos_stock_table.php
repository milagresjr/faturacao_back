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
        Schema::table('movimentos_stock', function (Blueprint $table) {
            $table->foreignId('armazem_origem_id')
                ->nullable()
                ->constrained('armazens')
                ->nullOnDelete();

            $table->foreignId('armazem_destino_id')
                ->nullable()
                ->constrained('armazens')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimentos_stock', function (Blueprint $table) {
            $table->dropColumn('armazem_origem_id');
            $table->dropColumn('armazem_destino_id');
        });
    }
};
