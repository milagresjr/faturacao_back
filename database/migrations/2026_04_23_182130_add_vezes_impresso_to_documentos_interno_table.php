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
            $table->integer('vezes_impresso')->default(0)->after('via');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_interno', function (Blueprint $table) {
            $table->dropColumn('vezes_impresso');
        });
    }
};
