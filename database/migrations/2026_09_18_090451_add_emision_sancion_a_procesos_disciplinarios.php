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
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->string('emision_sancion_estado')->nullable()->after('validaciones_v6_puntos_clave');
            $table->text('emision_sancion_error')->nullable()->after('emision_sancion_estado');
            $table->string('logro_desbloqueado_nombre')->nullable()->after('emision_sancion_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['emision_sancion_estado', 'emision_sancion_error', 'logro_desbloqueado_nombre']);
        });
    }
};
