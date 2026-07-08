<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días hábiles como conjunto (no binario): permite cualquier combinación de días,
 * incluido domingo y 24/7. Se guarda como JSON (arreglo de días ISO 1..7).
 * El antiguo 'dias_laborales' (lunes_viernes/lunes_sabado) se conserva por
 * compatibilidad; 'dias_habiles' tiene prioridad cuando está definido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->json('dias_habiles')->nullable()->after('dias_laborales');
        });

        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->json('dias_habiles')->nullable()->after('dias_laborales');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('dias_habiles');
        });

        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('dias_habiles');
        });
    }
};
