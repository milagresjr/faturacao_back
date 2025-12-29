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
        Schema::table('itens_documento_compra', function (Blueprint $table) {
            $table->decimal('total_sem_desconto', 20, 2)->default(0)->after('iva_percent');
            $table->decimal('total_sem_imposto', 20, 2)->default(0)->after('total_sem_desconto');
            $table->decimal('valor_imposto', 20, 2)->default(0)->after('total_sem_imposto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itens_documento_compra', function (Blueprint $table) {
            $table->dropColumn('total_sem_desconto');
            $table->dropColumn('total_sem_imposto');
            $table->dropColumn('valor_imposto');
        });
    }
};
