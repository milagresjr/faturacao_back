<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicacoes_agt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('documento_id')->nullable()->constrained('documentos');
            $table->string('tipo_comunicacao'); // envio_documento, consulta_estado, anulacao
            $table->string('status'); // pendente, enviado, erro, confirmado
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('codigo_erro')->nullable();
            $table->string('codigo_validacao_agt')->nullable();
            $table->integer('tentativas')->default(0);
            $table->timestamp('ultima_tentativa')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacoes_agt');
    }
};
