<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_agt', function (Blueprint $table) {
            $table->text('chave_publica')->nullable()->after('certificado_digital');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_agt', function (Blueprint $table) {
            $table->dropColumn('chave_publica');
        });
    }
};
