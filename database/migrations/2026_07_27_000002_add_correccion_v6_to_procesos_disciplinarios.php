<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->json('analisis_recomendacion')->nullable()->after('validaciones_v6_en');
            $table->json('analisis_recomendacion_original')->nullable()->after('analisis_recomendacion');
            $table->text('correccion_v6_motivo')->nullable()->after('analisis_recomendacion_original');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['analisis_recomendacion', 'analisis_recomendacion_original', 'correccion_v6_motivo']);
        });
    }
};
