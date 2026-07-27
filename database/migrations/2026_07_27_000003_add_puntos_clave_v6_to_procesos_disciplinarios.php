<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->json('validaciones_v6_puntos_clave')->nullable()->after('correccion_v6_motivo');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn('validaciones_v6_puntos_clave');
        });
    }
};
