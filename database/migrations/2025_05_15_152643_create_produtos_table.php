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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->decimal('preco_custo', 10, 2)->nullable();
            $table->decimal('preco_venda', 10, 2)->nullable();
            $table->decimal('preco_final', 10, 2)->nullable();
            $table->decimal('valor_iva', 10, 2)->nullable();
            $table->integer('margem_lucro')->nullable();
            $table->integer('stock_min')->nullable();
            $table->integer('stock_max')->nullable();
            $table->integer('stock_ideial')->nullable();
            $table->string('modelo')->nullable();
            $table->string('imagem')->nullable();
            $table->boolean('movimenta_stock')->default(true);
            $table->string('codigo_produto')->nullable();
            $table->string('codigo_barra')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('imposto')->nullable();
            $table->string('unidade')->nullable();
            $table->boolean('estado')->default(true);
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->onDelete('cascade');
            $table->foreignId('tipo_id')->constrained('tipo_produtos')->onDelete('cascade');
            $table->foreignId('armazem_id')->constrained('armazens')->onDelete('cascade');
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->foreignId('sub_categoria_id')->nullable()->constrained('sub_categorias')->onDelete('cascade');
            $table->foreignId('motivo_isencao_id')->nullable()->constrained('motivo_isencao')->onDelete('cascade');
            $table->foreignId('tipo_stock_id')->constrained('tipo_stock')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->onDelete('cascade');
            $table->foreignId('utilizador_id')->constrained('utilizadores')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
