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
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            // Antes "Rechazar" no pedía ningún motivo - a los pocos días
            // nadie sabía por qué se había rechazado una solicitud
            // (trazabilidad real en un sistema legal). Se pide al momento de
            // rechazar y queda guardado junto al registro.
            $table->text('motivo_rechazo')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->dropColumn('motivo_rechazo');
        });
    }
};
