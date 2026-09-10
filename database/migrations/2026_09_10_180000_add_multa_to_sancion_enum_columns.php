<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug real en producción (2026-09-10): "SQLSTATE[01000]: Warning: 1265
 * Data truncated for column 'tipo_sancion'" al emitir una sanción tipo
 * 'multa' - sanciones.tipo_sancion nunca incluyó 'multa' ni 'no_sancion'
 * en su ENUM original (solo llamado_atencion/suspension/terminacion,
 * ver 2025_12_18_021007_create_sanciones_table.php). La migración
 * 2026_05_22_000001_add_no_sancion_to_enum_columns.php ya había corregido
 * este mismo problema para analisis_juridicos e impugnaciones, pero solo
 * agregó 'no_sancion' - se le olvidó 'multa' en ambas, y no tocó
 * sanciones.tipo_sancion en absoluto. Se corrigen las 3 columnas de una
 * vez con el universo completo de $sancionMeta (5 valores).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sanciones MODIFY COLUMN tipo_sancion ENUM('llamado_atencion','suspension','terminacion','multa','no_sancion') NOT NULL");
        DB::statement("ALTER TABLE analisis_juridicos MODIFY COLUMN tipo_sancion_recomendada ENUM('llamado_atencion','suspension','terminacion','no_sancion','multa') NULL");
        DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN nueva_sancion_tipo ENUM('llamado_atencion','suspension','terminacion','no_sancion','multa') NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sanciones MODIFY COLUMN tipo_sancion ENUM('llamado_atencion','suspension','terminacion') NOT NULL");
        DB::statement("ALTER TABLE analisis_juridicos MODIFY COLUMN tipo_sancion_recomendada ENUM('llamado_atencion','suspension','terminacion','no_sancion') NULL");
        DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN nueva_sancion_tipo ENUM('llamado_atencion','suspension','terminacion','no_sancion') NULL");
    }
};
