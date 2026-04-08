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
        Schema::create('notificacoes_validade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes_produto');
            $table->foreignId('utilizador_id')->nullable()->constrained('utilizadores')->nullOnDelete();
            $table->enum('tipo', ['precoce', 'critico', 'expirado']);
            $table->text('mensagem');
            $table->boolean('lida')->default(false);
            $table->timestamp('data_envio')->useCurrent();
            $table->timestamps();

            $table->index(['lida', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacoes_validade');
    }
};
