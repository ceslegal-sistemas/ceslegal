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
        // Mismo guard ya usado en 2026_01_06_190534_add_missing_tipos_to_notificaciones_table.php
        // para el mismo problema: ALTER ... MODIFY COLUMN ... ENUM(...) es
        // sintaxis MySQL - sqlite (usado por phpunit.xml en tests) no la
        // entiende y tronaba la suite de tests COMPLETA (no solo la de esta
        // tarea) al migrar. sqlite no tiene ENUM real (guarda 'tipo' como
        // TEXT sin restricción), así que no ejecutar este ALTER ahí no
        // cambia ningún comportamiento, solo evita el error de sintaxis.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE trazabilidad_ia_descargos MODIFY COLUMN tipo ENUM(
            'generacion_preguntas',
            'analisis_respuestas'
        ) NOT NULL DEFAULT 'generacion_preguntas'");
    }
};
