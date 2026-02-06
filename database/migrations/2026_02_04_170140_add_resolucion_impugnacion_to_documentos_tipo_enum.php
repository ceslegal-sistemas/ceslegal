<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE documentos MODIFY COLUMN tipo_documento ENUM(
            'apertura_proceso',
            'citacion_descargos',
            'acta_descargos',
            'analisis_juridico',
            'sancion',
            'memorando_llamado',
            'memorando_suspension',
            'memorando_terminacion',
            'contrato_labor_obra',
            'decision_impugnacion',
            'resolucion_impugnacion'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE documentos MODIFY COLUMN tipo_documento ENUM(
            'apertura_proceso',
            'citacion_descargos',
            'acta_descargos',
            'analisis_juridico',
            'sancion',
            'memorando_llamado',
            'memorando_suspension',
            'memorando_terminacion',
            'contrato_labor_obra',
            'decision_impugnacion'
        ) NOT NULL");
    }
};
