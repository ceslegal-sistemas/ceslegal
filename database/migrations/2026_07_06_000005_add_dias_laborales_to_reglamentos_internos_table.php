<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $t) {
            // 'lunes_viernes' | 'lunes_sabado'. Fuente de los días laborales; si es
            // null se usa el fallback de la empresa (empresas.dias_laborales).
            $t->string('dias_laborales')->nullable()->after('fuente');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $t) {
            $t->dropColumn('dias_laborales');
        });
    }
};
