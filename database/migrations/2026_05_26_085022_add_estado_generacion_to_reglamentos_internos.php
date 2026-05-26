<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // 'generando' = job en progreso (IA aún no responde)
            // 'completado' = texto generado exitosamente
            // 'error'      = falló la generación, se puede reintentar
            $table->string('estado_generacion', 20)->default('completado')->after('fuente');
            $table->text('mensaje_error_ia')->nullable()->after('estado_generacion');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn(['estado_generacion', 'mensaje_error_ia']);
        });
    }
};
