<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Señales de comportamiento por respuesta individual
        Schema::table('respuestas_descargos', function (Blueprint $table) {
            $table->boolean('fue_pegada')->default(false)->after('respondido_en')
                ->comment('Texto fue pegado (Ctrl+V o paste) en lugar de tecleado');
            $table->unsignedSmallInteger('tiempo_respuesta_segundos')->nullable()->after('fue_pegada')
                ->comment('Segundos entre que apareció la pregunta y se envió la respuesta');
            $table->unsignedTinyInteger('cambios_pestana_durante')->default(0)->after('tiempo_respuesta_segundos')
                ->comment('Veces que el trabajador cambió de pestaña mientras respondía esta pregunta');
        });

        // Resumen de comportamiento agregado de toda la diligencia
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->json('resumen_comportamiento')->nullable()->after('evidencia_metadata')
                ->comment('Señales de comportamiento: nivel_alerta, cambios_pestana totales, preguntas_pegadas, detalle por pregunta');
        });
    }

    public function down(): void
    {
        Schema::table('respuestas_descargos', function (Blueprint $table) {
            $table->dropColumn(['fue_pegada', 'tiempo_respuesta_segundos', 'cambios_pestana_durante']);
        });

        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn('resumen_comportamiento');
        });
    }
};
