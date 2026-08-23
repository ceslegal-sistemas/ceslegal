<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->date('fecha_fin_contrato')->nullable()->after('fecha_inicio_propuesta');
            $table->string('periodo_pago', 20)->nullable()->default('quincenal')->after('salario_propuesto');
            $table->string('lugar_labores', 255)->nullable()->after('periodo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->dropColumn(['fecha_fin_contrato', 'periodo_pago', 'lugar_labores']);
        });
    }
};
