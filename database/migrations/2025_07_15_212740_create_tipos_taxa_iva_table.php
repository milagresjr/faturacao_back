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
        Schema::create('tipos_taxa_iva', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();           // Ex: ISENTO, NOR, RED
            $table->string('descricao');                  // Ex: Taxa Normal, Isento, etc.
            $table->decimal('taxa', 5, 2);    // Ex: 0.00, 1.00, 14.00
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_taxa_iva');
    }
};
