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
        // Modificar el ENUM para incluir los tipos faltantes
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM(
            'apertura',
            'descargos_pendientes',
            'descargos_realizados',
            'termino_vencido',
            'sancion_emitida',
            'impugnacion_realizada',
            'cerrado',
            'contrato_generado'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM(
            'apertura',
            'descargos_pendientes',
            'termino_vencido',
            'sancion_emitida',
            'impugnacion_realizada',
            'contrato_generado'
        ) NOT NULL");
    }
};
