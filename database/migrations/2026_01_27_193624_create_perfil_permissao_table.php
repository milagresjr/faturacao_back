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
        Schema::create('perfil_permissao', function (Blueprint $table) {
            $table->foreignId('perfil_id')
                ->constrained('perfis')
                ->cascadeOnDelete();

            $table->foreignId('permissao_id')
                ->constrained('permissoes')
                ->cascadeOnDelete();

            $table->primary(['perfil_id', 'permissao_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfil_permissao');
    }
};
