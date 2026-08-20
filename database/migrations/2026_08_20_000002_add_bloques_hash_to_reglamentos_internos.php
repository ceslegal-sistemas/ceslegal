<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('bloques_texto_hash', 64)->nullable()->after('texto_completo');
            $table->timestamp('bloques_generados_en')->nullable()->after('bloques_texto_hash');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn(['bloques_texto_hash', 'bloques_generados_en']);
        });
    }
};
