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
        Schema::table('moedas', function (Blueprint $table) {
            $table->dropUnique('moedas_codigo_unique');
            $table->unique(['empresa_id', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moedas', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'codigo']);
            $table->string('codigo')->unique();
        });
    }
};