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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            // Tipo de documento
            $table->string('tipo_nome')->default('Factura');
            $table->string('tipo_sigla')->default('FA');
            $table->string('tipo_cor')->nullable();

            //Numero de fatura
            $table->string('num_fatura');
            $table->string('via');

            // Dados da empresa emissora
            $table->string('empresa_id');
            $table->string('empresa_nome');
            $table->string('empresa_nif')->nullable();
            $table->string('empresa_telefone')->nullable();
            $table->string('empresa_email')->nullable();
            $table->string('empresa_endereco')->nullable();

            // Dados do cliente no momento do documento
            $table->string('cliente_id');
            $table->string('cliente_nome');
            $table->string('cliente_nif')->nullable();
            $table->string('cliente_telefone')->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('cliente_endereco')->nullable();

            // Configurações
            $table->string('caixa')->nullable();
            $table->date('data_emissao');
            $table->date('data_vencimento')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->boolean('movimenta_stock')->default(false);
            $table->string('descricao_iva')->nullable();

            // Descontos e transporte
            $table->decimal('desconto_total', 20, 2)->default(0);
            $table->decimal('taxa_iva', 20, 2)->default(0);
            $table->decimal('valor_iva', 20, 2)->default(0);
            $table->decimal('retencao', 20, 2)->default(0);
            $table->decimal('valor_transporte', 20, 2)->default(0);

            // Totais
            $table->decimal('total_sem_desconto', 20, 2)->default(0);
            $table->decimal('total_impostos', 20, 2)->default(0);
            $table->decimal('total_geral', 20, 2)->default(0);

            //Hash
            $table->text('hash');

            //Utilizador
            $table->string('utilizador_id');
            $table->string('utilizador');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
