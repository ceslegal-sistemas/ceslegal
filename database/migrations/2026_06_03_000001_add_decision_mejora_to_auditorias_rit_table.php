<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            // Decisión del cliente sobre el RIT mejorado generado:
            //   null/'pendiente' → aún debe elegir
            //   'adoptado'       → reemplazó su RIT por la versión mejorada
            //   'rechazado'      → mantuvo su RIT actual (subido manualmente)
            $table->string('decision_mejora', 20)
                ->nullable()
                ->after('reglamento_mejorado_id');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropColumn('decision_mejora');
        });
    }
};
