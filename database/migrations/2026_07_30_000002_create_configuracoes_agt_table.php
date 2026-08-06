<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_agt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->unique();
            $table->string('numero_validacao_software')->nullable();
            $table->text('certificado_digital')->nullable();
            $table->string('ambiente')->default('testes'); // testes, producao
            $table->boolean('comunicacao_ativa')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_agt');
    }
};
