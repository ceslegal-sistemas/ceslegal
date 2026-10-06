<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug real reportado por el usuario en vivo (demo 2026-10-05, empresa
 * RENBEL): el quiz de socialización del RIT nunca mostraba preguntas de
 * selección múltiple, solo Sí/No.
 *
 * Causa raíz: TemaClasificadorService::asegurarPreguntasQuiz() decide si
 * regenerar las preguntas comparando el hash del texto del RIT y
 * verificando que pregunta_vf ya esté lleno - cualquier RIT cuyas preguntas
 * se generaron ANTES de que existiera el formato de selección múltiple
 * (migración 2026_10_03_140000_add_seleccion_multiple_a_quiz_tema) ya
 * "tenía todas" sus preguntas con ese criterio, así que nunca se volvía a
 * generar mientras el texto del RIT no cambiara - y el texto de un RIT ya
 * publicado normalmente no vuelve a cambiar solo.
 *
 * NO se puede distinguir esto con una condición de código: la columna
 * tipo_pregunta tiene DEFAULT 'vf' a nivel de base de datos (ver esa misma
 * migración), así que una fila vieja (nunca procesada con el prompt nuevo)
 * es indistinguible de una fila nueva donde la IA genuinamente eligió
 * Verdadero/Falso para ese tema - ambas tienen tipo_pregunta='vf'.
 *
 * Fix: una migración de datos, de una sola vez. Limpiar pregunta_vf fuerza
 * que la próxima vez que se llame asegurarPreguntasQuiz() para ese RIT (ya
 * sea por ReglamentoInternoObserver al guardar, o por la llamada perezosa
 * agregada en SocializacionRit::iniciarQuiz()) regenere con el prompt que
 * SÍ puede producir selección múltiple. No reversible a propósito - no
 * tiene sentido "deshacer" un refresco de caché.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reglamento_interno_tema')
            ->whereNotNull('pregunta_vf')
            ->update(['pregunta_vf' => null]);
    }

    public function down(): void
    {
        // Intencional: no hay forma de "deshacer" un refresco de caché.
    }
};
