<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->softDeletes(); // Isso adiciona a coluna deleted_at
        });
    }

    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Remove a coluna caso precise reverter
        });
    }
};
