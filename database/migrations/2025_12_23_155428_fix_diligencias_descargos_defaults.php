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
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            // Modificar trabajador_asistio para que tenga valor por defecto
            $table->boolean('trabajador_asistio')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            // Revertir el cambio
            $table->boolean('trabajador_asistio')->nullable()->change();
        });
    }
};
