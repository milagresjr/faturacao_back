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
        Schema::create('caixas', function (Blueprint $table) {
            $table->id();

            /**
             * Identificação e Localização
             */
            $table->string('nome', 150);
            $table->string('localizacao', 255)->nullable();
            $table->enum('tipo', ['fisico', 'virtual', 'movel'])->default('fisico');

            /**
             * Estado operacional
             */
            $table->enum('estado', ['aberto', 'fechado', 'inativo'])->default('fechado');
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->decimal('saldo_atual', 15, 2)->default(0);
            $table->dateTime('data_abertura')->nullable();
            $table->dateTime('data_fechamento')->nullable();
            $table->string('turno', 50)->nullable();
            $table->text('observacoes')->nullable();

            /**
             * Configurações similares ao Vendus
             */
            $table->boolean('imprimir_abertura')->default(false); // Imprimir relatório na abertura
            $table->string('documento_predefinido', 50)->nullable(); // Ex: "fatura", "fatura simplificada"
            $table->string('aspecto', 50)->nullable(); // Layout do documento
            $table->enum('metodo_impressao', ['direto', 'manual'])->default('direto');
            $table->string('modelo_impressao', 50)->nullable(); // Modelo da fatura/talão
            $table->boolean('impressao_papel')->default(true); // Se vai imprimir em papel
            $table->string('modelo_email', 100)->nullable(); // Template para envio por email
            $table->boolean('finalizar_avancado')->default(false); // Permitir mais opções no checkout
            $table->boolean('referencia_produtos')->default(true); // Mostrar referências no POS
            $table->enum('precos_produtos', ['com_iva', 'sem_iva'])->default('com_iva');
            $table->enum('modo_funcionamento', ['rapido', 'restaurante'])->default('rapido');
            $table->boolean('listar_produtos')->default(true); // Listar produtos no POS
            $table->string('grupo_precos', 50)->nullable(); // Grupo de preços
            
            /**
             * Controle e relacionamentos
             */
            $table->boolean('permite_movimento_negativo')->default(false);
            $table->boolean('permite_multiplos_usuarios')->default(false);

            $table->foreignId('usuario_id')->constrained('utilizadores')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caixas');
    }
};
