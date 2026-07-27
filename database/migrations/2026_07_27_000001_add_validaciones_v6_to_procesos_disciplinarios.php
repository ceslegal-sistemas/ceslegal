<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->string('validaciones_v6_estado')->nullable()->after('sancion_ia_recomendada');
            $table->json('validaciones_v6')->nullable()->after('validaciones_v6_estado');
            $table->timestamp('validaciones_v6_en')->nullable()->after('validaciones_v6');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['validaciones_v6_estado', 'validaciones_v6', 'validaciones_v6_en']);
        });
    }
};
