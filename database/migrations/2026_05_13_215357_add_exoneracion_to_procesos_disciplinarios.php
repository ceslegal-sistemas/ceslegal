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
            $table->string('sancion_ia_recomendada')->nullable()->after('tipo_sancion');
            $table->json('autoridad_rango_rit')->nullable()->after('sancion_ia_recomendada');
            $table->string('autorizador_nombre')->nullable()->after('autoridad_rango_rit');
            $table->string('autorizador_cargo')->nullable()->after('autorizador_nombre');
            $table->boolean('exoneracion_aceptada')->nullable()->after('autorizador_cargo');
            $table->timestamp('exoneracion_aceptada_en')->nullable()->after('exoneracion_aceptada');
            $table->string('exoneracion_ip')->nullable()->after('exoneracion_aceptada_en');
            $table->text('razon_divergencia')->nullable()->after('exoneracion_ip');
            $table->string('foto_autorizador_path')->nullable()->after('razon_divergencia');
            $table->timestamp('foto_autorizador_en')->nullable()->after('foto_autorizador_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn([
                'sancion_ia_recomendada',
                'autoridad_rango_rit',
                'autorizador_nombre',
                'autorizador_cargo',
                'exoneracion_aceptada',
                'exoneracion_aceptada_en',
                'exoneracion_ip',
                'razon_divergencia',
                'foto_autorizador_path',
                'foto_autorizador_en',
            ]);
        });
    }
};
