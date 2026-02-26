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
        Schema::table('filiais', function (Blueprint $table) {
            //Remove o unique antigo
            $table->dropUnique(['nome']);
            $table->dropUnique(['telefone']);

            //Cria unique composto
            $table->unique(['empresa_id', 'nome']);
            $table->unique(['empresa_id', 'telefone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('filiais', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'nome']);
            $table->dropUnique(['empresa_id', 'telefone']);

            $table->unique('nome');
            $table->unique('telefone');
        });
    }
};
