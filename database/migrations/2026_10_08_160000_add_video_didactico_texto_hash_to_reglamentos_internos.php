<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de Andrés Sarmiento / Dr. Ernesto (reunión 2026-10-05, item 3): la
 * prueba de que el trabajador vio el video didáctico no es "demostrar que
 * no se pudo adelantar" - es poder guardar el video y recuperarlo cuando se
 * necesite como evidencia. Cita literal: "Lo que sí hay que hacer, Juan
 * Pablo, es guardar el vídeo, que uno lo pueda ir a invocar cuando quiera,
 * lo pueda uno venir a traer. Eso sería la prueba."
 *
 * Este hash identifica a QUÉ versión del texto del RIT corresponde el video
 * vigente (video_didactico_capitulos) - sin esto, al archivar un video
 * reemplazado (ver HistoricoVideoDidacticoRit) no habría forma de saber qué
 * versión del Reglamento explicaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('video_didactico_texto_hash', 64)->nullable()->after('video_didactico_generado_en');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('video_didactico_texto_hash');
        });
    }
};
