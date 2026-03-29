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
        Schema::create('configuracoes_fatura', function (Blueprint $table) {
            $table->id();

            //Relacionamento (se tiver multi-empresa)
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();

            //Dados da empresa
            $table->boolean('nome_empresa')->default(true);
            $table->boolean('nif')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('telefone')->default(true);
            $table->boolean('endereco')->default(true);
            $table->boolean('website')->default(true);

            //Dados do Cliente
            $table->boolean('endereco_cliente')->default(true);

            //Identidade visual
            $table->string('logo')->nullable(); // caminho do ficheiro
        
            //Layout da fatura
            $table->enum('template', ['classic', 'modern', 'minimal'])->default('classic');

            //Conteúdo da fatura
            $table->text('rodape')->nullable(); // ex: IBAN, agradecimento
            
            //Extras
            $table->boolean('mostrar_utilizador')->default(true);
            $table->boolean('mostrar_logo')->default(true);
            $table->boolean('mostrar_nif')->default(true);
            $table->boolean('mostrar_rodape')->default(true);

            //Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes_empresa');
    }
};
