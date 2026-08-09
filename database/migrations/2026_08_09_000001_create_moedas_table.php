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
        Schema::create('moedas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->onDelete('cascade');
            $table->string('codigo')->unique();           // Ex: AKZ, USD, EUR
            $table->string('nome');                       // Ex: Kwanza, Dólar Americano, Euro
            $table->string('simbolo')->nullable();        // Ex: Kz, $, €
            $table->integer('casas_decimais')->default(2);
            $table->boolean('predefinida')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moedas');
    }
};