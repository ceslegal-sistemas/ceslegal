<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estabilidad laboral reforzada / fuero del trabajador.
 *
 * Se guarda la CONCLUSIÓN jurídica (qué fuero aplica), no el dato médico,
 * para minimizar el tratamiento de datos sensibles (Ley 1581 de 2012).
 * Alimenta la alerta de fuero del análisis de sanciones con IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->json('tipos_fuero')->nullable()->after('area');
            $table->text('fuero_nota')->nullable()->after('tipos_fuero');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn(['tipos_fuero', 'fuero_nota']);
        });
    }
};
