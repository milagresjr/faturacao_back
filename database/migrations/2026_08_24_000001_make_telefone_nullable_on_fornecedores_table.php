<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fornecedores', function () {
            // Telefone deixa de ser obrigatório
            DB::statement('ALTER TABLE fornecedores MODIFY telefone VARCHAR(255) NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fornecedores', function () {
            DB::statement("UPDATE fornecedores SET telefone = '' WHERE telefone IS NULL");
            DB::statement('ALTER TABLE fornecedores MODIFY telefone VARCHAR(255) NOT NULL');
        });
    }
};
