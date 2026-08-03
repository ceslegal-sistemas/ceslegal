<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca una pregunta generada por IA como "último intento" cuando el
 * Director Estratégico detectó patron_evasivo_detectado=true al generarla
 * (segunda respuesta evasiva consecutiva sobre el mismo dato). El prompt ya
 * le dice al Generador que, si la respuesta a ESTA pregunta también resulta
 * evasiva, debe rendirse (NO_REQUIERE) - pero en producción no siempre lo
 * respetó (caso real: PD-2026-0057, 25 preguntas de IA sobre un mismo tema
 * antes de chocar con el tope de 30). Esta columna permite cortar por
 * código en el siguiente turno, sin depender de que el modelo recuerde su
 * propia regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preguntas_descargos', function (Blueprint $table) {
            $table->boolean('es_intento_final_evasivo')->default(false)->after('es_generada_por_ia');
        });
    }

    public function down(): void
    {
        Schema::table('preguntas_descargos', function (Blueprint $table) {
            $table->dropColumn('es_intento_final_evasivo');
        });
    }
};
