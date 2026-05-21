<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía el ENUM fuente para incluir 'mejora_ia' (RIT generado por IA post-auditoría).
     */
    public function up(): void
    {
        // ALTER COLUMN ENUM es DDL nativo — no tiene equivalente directo en Blueprint
        DB::statement("ALTER TABLE `reglamentos_internos`
            MODIFY COLUMN `fuente` ENUM('subido', 'construido_ia', 'mejora_ia')
            NOT NULL DEFAULT 'subido'");
    }

    public function down(): void
    {
        // Revertir: primero pasar los 'mejora_ia' a 'construido_ia' para no violar el enum
        DB::statement("UPDATE `reglamentos_internos` SET `fuente` = 'construido_ia' WHERE `fuente` = 'mejora_ia'");
        DB::statement("ALTER TABLE `reglamentos_internos`
            MODIFY COLUMN `fuente` ENUM('subido', 'construido_ia')
            NOT NULL DEFAULT 'subido'");
    }
};
