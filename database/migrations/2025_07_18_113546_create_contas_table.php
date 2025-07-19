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
        Schema::create('contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('banco_id')->constrained('bancos')->onDelete('cascade');
            $table->foreignId('utilizador_id')->constrained('utilizadores')->onDelete('cascade');
            $table->string('numero_conta')->unique();
            $table->string('descricao')->nullable();
            $table->decimal('saldo', 15, 2)->default(0.00);
            $table->string('tipo')->default('corrente'); // 'corrente', 'poupanca', etc.
            $table->string('moeda', 3)->default('AKZ'); // Moeda padrão é AKZ
            $table->string('iban')->nullable()->unique();
            $table->string('swift')->nullable();
            $table->string('titular')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes(); // Adiciona suporte a soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};
