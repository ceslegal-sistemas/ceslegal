<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('temas_texto_hash', 64)->nullable()->after('texto_completo');
            $table->timestamp('temas_clasificados_en')->nullable()->after('temas_texto_hash');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn(['temas_texto_hash', 'temas_clasificados_en']);
        });
    }
};
