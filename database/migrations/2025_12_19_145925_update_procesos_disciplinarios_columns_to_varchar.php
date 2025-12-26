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
        // Cambiar estado de ENUM a VARCHAR para mayor flexibilidad
        DB::statement("ALTER TABLE procesos_disciplinarios MODIFY COLUMN estado VARCHAR(50) DEFAULT 'apertura'");

        // Cambiar tipo_sancion de ENUM a VARCHAR para mayor flexibilidad
        DB::statement("ALTER TABLE procesos_disciplinarios MODIFY COLUMN tipo_sancion VARCHAR(50) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a los ENUMs originales
        DB::statement("ALTER TABLE procesos_disciplinarios MODIFY COLUMN estado ENUM('apertura', 'traslado', 'descargos_pendientes', 'descargos_realizados', 'analisis_juridico', 'pendiente_gerencia', 'sancion_definida', 'notificado', 'impugnado', 'cerrado', 'archivado') DEFAULT 'apertura'");
        DB::statement("ALTER TABLE procesos_disciplinarios MODIFY COLUMN tipo_sancion ENUM('llamado_atencion', 'suspension', 'terminacion') NULL");
    }
};
