<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_agt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('armazem_id')->nullable()->constrained('armazens');
            $table->string('series_code', 60); // código atribuído pela AGT (ex: FT6325S2042N)
            $table->string('tipo_documento', 2); // FT, FR, NC, ND, FG, etc.
            $table->string('series_year', 4); // 2026
            $table->string('contingency_indicator', 1)->default('N'); // N=normal, C=contingência
            $table->bigInteger('authorized_quantity')->default(0);
            $table->string('first_document_no', 60)->nullable();
            $table->string('last_document_no', 60)->nullable();
            $table->string('status', 20)->default('ativa'); // ativa, inativa, erro
            $table->text('solicitud_payload')->nullable();
            $table->text('resposta_payload')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'tipo_documento', 'series_year']);
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_agt');
    }
};
