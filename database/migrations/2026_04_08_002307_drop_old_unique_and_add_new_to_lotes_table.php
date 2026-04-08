<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Método mais suave: usar SQL puro e ignorar erros
        try {
            // Tentar remover a constraint antiga (ignora se não existir)
            DB::statement('ALTER TABLE lotes_produto DROP INDEX lotes_produto_produto_id_codigo_lote_unique');
        } catch (\Exception $e) {
            // Se não existir, continua
        }

        try {
            // Tentar remover outro possível nome da constraint
            DB::statement('ALTER TABLE lotes_produto DROP INDEX lotes_produto_codigo_lote_unique');
        } catch (\Exception $e) {
            // Se não existir, continua
        }

        // Adicionar a nova constraint (com armazem_id)
        try {
            DB::statement('ALTER TABLE lotes_produto ADD UNIQUE INDEX lotes_produto_unique (produto_id, armazem_id, codigo_lote)');
        } catch (\Exception $e) {
            // Se já existir, ignora
        }
    }

    public function down()
    {
        try {
            DB::statement('ALTER TABLE lotes_produto DROP INDEX lotes_produto_unique');
        } catch (\Exception $e) {
            // Ignora
        }

        try {
            DB::statement('ALTER TABLE lotes_produto ADD UNIQUE INDEX lotes_produto_produto_id_codigo_lote_unique (produto_id, codigo_lote)');
        } catch (\Exception $e) {
            // Ignora
        }
    }
};
