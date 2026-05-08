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
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['email']);

            $table->unique(['empresa_id', 'email']);
            $table->unique(['empresa_id', 'nif']);
            $table->unique(['empresa_id', 'numero_bi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'email']);
            $table->dropUnique(['empresa_id', 'nif']);
            $table->dropUnique(['empresa_id', 'numero_bi']);

            $table->unique(['email']);
        });
    }
};
