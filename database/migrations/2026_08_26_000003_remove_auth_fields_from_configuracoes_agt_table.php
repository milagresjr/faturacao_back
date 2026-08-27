<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_agt', function (Blueprint $table) {
            $table->dropColumn(['username_agt', 'senha_agt']);
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_agt', function (Blueprint $table) {
            $table->string('username_agt')->nullable()->after('comunicacao_ativa');
            $table->string('senha_agt')->nullable()->after('username_agt');
        });
    }
};
