<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar 'citacion_descargos' al ENUM de tipo_documento
        DB::statement("ALTER TABLE documentos MODIFY COLUMN tipo_documento ENUM(
            'apertura_proceso',
            'citacion_descargos',
            'acta_descargos',
            'analisis_juridico',
            'memorando_llamado',
            'memorando_suspension',
            'memorando_terminacion',
            'contrato_labor_obra',
            'decision_impugnacion'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original (sin 'citacion_descargos')
        DB::statement("ALTER TABLE documentos MODIFY COLUMN tipo_documento ENUM(
            'apertura_proceso',
            'acta_descargos',
            'analisis_juridico',
            'memorando_llamado',
            'memorando_suspension',
            'memorando_terminacion',
            'contrato_labor_obra',
            'decision_impugnacion'
        ) NOT NULL");
    }
};