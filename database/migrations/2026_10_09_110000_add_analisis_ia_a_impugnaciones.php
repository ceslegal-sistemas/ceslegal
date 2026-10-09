<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Análisis con IA de la impugnación, generado en segundo plano apenas el trabajador
 * la presenta (antes solo existía el botón "Sugerir fundamento con IA" dentro del
 * modal de resolver, que bloqueaba la pantalla 30-90 s). Se guarda el resultado
 * estructurado para mostrarlo en "Ver impugnación" y precargar la resolución.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impugnaciones', function (Blueprint $table) {
            $table->json('analisis_ia')->nullable()->after('analisis_impugnacion');
            // pendiente | procesando | completado | error
            $table->string('analisis_ia_estado', 20)->nullable()->after('analisis_ia');
            $table->dateTime('analisis_ia_en')->nullable()->after('analisis_ia_estado');
        });
    }

    public function down(): void
    {
        Schema::table('impugnaciones', function (Blueprint $table) {
            $table->dropColumn(['analisis_ia', 'analisis_ia_estado', 'analisis_ia_en']);
        });
    }
};
