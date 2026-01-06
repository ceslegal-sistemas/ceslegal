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
        // Paso 1: Migrar datos existentes
        // Actualizar hora_descargos_programada con la hora de fecha_descargos_programada
        DB::statement("
            UPDATE procesos_disciplinarios
            SET hora_descargos_programada = TIME(fecha_descargos_programada)
            WHERE fecha_descargos_programada IS NOT NULL
            AND hora_descargos_programada IS NULL
        ");

        // Paso 2: Cambiar fecha_descargos_programada de DATETIME a DATE
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->date('fecha_descargos_programada')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a DATETIME
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dateTime('fecha_descargos_programada')->nullable()->change();
        });
    }
};