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
        Schema::create('taxas_cambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->onDelete('cascade');
            $table->foreignId('moeda_id')->constrained('moedas')->onDelete('cascade');
            $table->decimal('taxa', 18, 6);               // 1 unidade da moeda expressa em AKZ
            $table->date('data')->nullable();
            $table->string('fonte')->default('manual');   // manual | banco
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
        Schema::dropIfExists('taxas_cambio');
    }
};