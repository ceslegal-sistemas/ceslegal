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
            $table->text('descripcion_obra_labor')->nullable()->after('lugar_labores');
            $table->text('duracion_terminacion_obra_redactada')->nullable()->after('objeto_juridico_redactado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->dropColumn(['descripcion_obra_labor', 'duracion_terminacion_obra_redactada']);
        });
    }
};
