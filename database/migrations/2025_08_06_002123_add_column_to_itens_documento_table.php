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
        Schema::table('itens_documento', function (Blueprint $table) {
            $table->decimal('total_sem_desconto', 10, 2)->nullable()->after('iva_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento', function (Blueprint $table) {
            //
        });
    }
};
