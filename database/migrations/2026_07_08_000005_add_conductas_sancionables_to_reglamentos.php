<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listado de conductas sancionables generado para el RIT (por gravedad: leve, grave,
 * gravísima), con su medida disciplinaria y base legal. Reemplaza el catálogo estático
 * 'sanciones_laborales' como fuente de conductas por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->json('conductas_sancionables')->nullable()->after('sanciones_extraidas');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('conductas_sancionables');
        });
    }
};
