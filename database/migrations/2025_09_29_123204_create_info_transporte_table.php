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
        Schema::create('info_guias', function (Blueprint $table) {
            $table->id();
            $table->string('marca')->nullable();
            $table->string('matricula')->nullable();
            $table->string('local_origem')->nullable();
            $table->string('local_destino')->nullable();
            $table->date('data_origem')->nullable();
            $table->date('data_destino')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('info_guias');
    }
};
