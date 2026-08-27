<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comunicacoes_agt', function (Blueprint $table) {
            $table->string('submission_uuid')->nullable()->after('status');
            $table->string('request_id')->nullable()->after('submission_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('comunicacoes_agt', function (Blueprint $table) {
            $table->dropColumn(['submission_uuid', 'request_id']);
        });
    }
};
