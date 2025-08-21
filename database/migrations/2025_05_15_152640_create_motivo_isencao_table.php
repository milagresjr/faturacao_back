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
        Schema::create('motivo_isencao', function (Blueprint $table) {
            $table->id();
            $table->integer('taxa')->default(0);
            $table->integer('taxa_retorno')->default(0);
            $table->string('codigo')->nullable();
            $table->text('motivo')->nullable();
            $table->string('texto')->nullable();
            $table->boolean('alteracao_manual')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motivo_isencao');
    }
};
