<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // Creamos el campo como tipo 'json' para almacenar el array de Filament.
            // Lo dejamos como ->nullable() por si hay registros existentes sin esta info.
            $table->json('tipos_contrato')->nullable()->after('progreso_generacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // Regla de oro: si revertimos la migración, eliminamos la columna.
            $table->dropColumn('tipos_contrato');
        });
    }
};