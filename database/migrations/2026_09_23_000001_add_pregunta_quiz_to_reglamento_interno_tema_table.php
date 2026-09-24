<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamento_interno_tema', function (Blueprint $table) {
            $table->text('pregunta_vf')->nullable()->after('resumen_simple');
            $table->boolean('respuesta_correcta')->nullable()->after('pregunta_vf');
        });
    }

    public function down(): void
    {
        Schema::table('reglamento_interno_tema', function (Blueprint $table) {
            $table->dropColumn(['pregunta_vf', 'respuesta_correcta']);
        });
    }
};
