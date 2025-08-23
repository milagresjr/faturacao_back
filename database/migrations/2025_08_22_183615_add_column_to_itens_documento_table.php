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
            $table->foreignId('imposto_taxa_id')->nullable()->constrained('tipos_taxa_iva')->after('desconto_fixo')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento', function (Blueprint $table) {
            $table->dropForeign(['imposto_taxa_id']);
            $table->dropColumn('imposto_taxa_id');
        });
    }
};
