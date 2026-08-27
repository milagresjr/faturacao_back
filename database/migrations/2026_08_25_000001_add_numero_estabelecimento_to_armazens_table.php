<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armazens', function (Blueprint $table) {
            $table->string('numero_estabelecimento')->nullable()->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('armazens', function (Blueprint $table) {
            $table->dropColumn('numero_estabelecimento');
        });
    }
};
