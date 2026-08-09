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
        Schema::table('alertas_stock', function (Blueprint $table) {
            $table->boolean('lida')->default(false)->after('sms_enviado');
            $table->timestamp('data_leitura')->nullable()->after('lida');
            $table->unsignedBigInteger('lida_por')->nullable()->after('data_leitura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alertas_stock', function (Blueprint $table) {
            $table->dropColumn(['lida', 'data_leitura', 'lida_por']);
        });
    }
};