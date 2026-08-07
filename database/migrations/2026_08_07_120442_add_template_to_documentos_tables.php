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
            $table->enum('template', ['classic', 'modern', 'minimal'])->default('classic')->after('tipo_sigla');
        });

        Schema::table('documentos_interno', function (Blueprint $table) {
            $table->enum('template', ['classic', 'modern', 'minimal'])->default('classic')->after('tipo_sigla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn('template');
        });

        Schema::table('documentos_interno', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};