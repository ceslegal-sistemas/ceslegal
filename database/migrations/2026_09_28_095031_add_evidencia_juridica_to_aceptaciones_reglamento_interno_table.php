<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Bug real de integridad probatoria (2026-09-28, pedido explícito del
        // usuario/su equipo): la restricción única [trabajador_id,
        // reglamento_interno_id] hacía que updateOrCreate() SOBREESCRIBIERA
        // la aceptación anterior si el mismo registro de RIT cambiaba de
        // texto (Plan B) - se perdía el rastro de que el trabajador aceptó
        // una versión anterior. Cada aceptación ahora es un registro
        // histórico permanente, nunca se sobreescribe.
        Schema::table('aceptaciones_reglamento_interno', function (Blueprint $table) {
            // InnoDB exige que cada columna con FK tenga algún índice de
            // soporte - el único índice que cubre trabajador_id hoy es
            // justamente el UNIQUE que se va a eliminar abajo. Se agrega un
            // índice normal primero para que el FK no se quede sin soporte.
            $table->index(['trabajador_id', 'reglamento_interno_id'], 'aceptacion_rit_trabajador_reglamento_index');
            $table->dropUnique('aceptacion_rit_trabajador_reglamento_unique');
        });

        Schema::table('aceptaciones_reglamento_interno', function (Blueprint $table) {
            // Snapshot inmutable del texto exacto aceptado + su hash SHA-256
            // - el texto real del ReglamentoInterno puede mutar después
            // (Plan B), pero esta copia y su hash nunca cambian, permitiendo
            // demostrar exactamente qué fue lo que el trabajador aceptó.
            $table->longText('texto_rit_snapshot')->nullable()->after('reglamento_interno_id');
            $table->string('texto_rit_hash', 64)->nullable()->after('texto_rit_snapshot');

            // Selfie tomada EN ESE MOMENTO específico de aceptación (no la
            // foto_referencia_path del trabajador, que puede ser de una
            // sesión anterior sin relación con este acto puntual).
            $table->string('foto_aceptacion_path')->nullable()->after('user_agent');

            // Resultado completo del quiz de comprensión: por cada pregunta,
            // el texto, la respuesta correcta, y CADA intento del trabajador
            // con su resultado y timestamp - hoy esto se perdía por completo
            // al terminar la sesión.
            $table->json('quiz_resultado')->nullable()->after('foto_aceptacion_path');

            // PDF "Acta de Socialización" generado para esta aceptación -
            // documento formal descargable/archivable por la empresa.
            $table->string('ruta_acta')->nullable()->after('quiz_resultado');
            $table->timestamp('fecha_generacion_acta')->nullable()->after('ruta_acta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aceptaciones_reglamento_interno', function (Blueprint $table) {
            $table->dropColumn([
                'texto_rit_snapshot',
                'texto_rit_hash',
                'foto_aceptacion_path',
                'quiz_resultado',
                'ruta_acta',
                'fecha_generacion_acta',
            ]);
        });

        Schema::table('aceptaciones_reglamento_interno', function (Blueprint $table) {
            $table->dropIndex('aceptacion_rit_trabajador_reglamento_index');
            $table->unique(['trabajador_id', 'reglamento_interno_id'], 'aceptacion_rit_trabajador_reglamento_unique');
        });
    }
};
