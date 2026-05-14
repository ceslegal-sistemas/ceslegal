<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // Sanciones extraídas con IA del texto_completo (RITs subidos manualmente).
            // Para RITs construidos con el wizard, esta columna permanece null
            // y se usa respuestas_cuestionario directamente.
            // Estructura: {faltas_leves:[], faltas_graves:[], sanciones:[]}
            $table->json('sanciones_extraidas')->nullable()->after('respuestas_cuestionario');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('sanciones_extraidas');
        });
    }
};
