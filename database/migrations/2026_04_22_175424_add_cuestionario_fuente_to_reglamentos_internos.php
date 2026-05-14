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
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->json('respuestas_cuestionario')->nullable()->after('texto_completo');
            $table->enum('fuente', ['subido', 'construido_ia'])->default('subido')->after('respuestas_cuestionario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn(['respuestas_cuestionario', 'fuente']);
        });
    }
};
