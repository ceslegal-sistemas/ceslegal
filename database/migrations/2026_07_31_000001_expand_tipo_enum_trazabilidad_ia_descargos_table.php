<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug real encontrado: la columna 'tipo' quedó como ENUM('generacion_preguntas',
 * 'analisis_respuestas') desde que existía un solo prompt de generación de
 * preguntas (migración 2025_12_23_145008). Cuando se introdujo el pipeline de
 * 3 agentes (commit b31af3b, IADescargoService::registrarTrazabilidad() con
 * tipo='director_estrategico'/'generador_preguntas'/'evaluador_suficiencia'),
 * nadie amplió este ENUM - MySQL rechaza cualquier valor fuera de la lista
 * ("Data truncated for column 'tipo'"), lo que lanzaba una excepción DENTRO
 * del try/catch de generarPreguntasDinamicas() y abortaba TODO el pipeline
 * justo después de la llamada al Director Estratégico, sin importar lo que
 * este hubiera decidido. Efecto real: nunca se generaba ninguna pregunta de
 * seguimiento dinámica, solo el lote inicial fijo de preguntas con IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trazabilidad_ia_descargos MODIFY COLUMN tipo ENUM(
            'generacion_preguntas',
            'analisis_respuestas',
            'director_estrategico',
            'generador_preguntas',
            'evaluador_suficiencia'
        ) NOT NULL DEFAULT 'generacion_preguntas'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trazabilidad_ia_descargos MODIFY COLUMN tipo ENUM(
            'generacion_preguntas',
            'analisis_respuestas'
        ) NOT NULL DEFAULT 'generacion_preguntas'");
    }
};
