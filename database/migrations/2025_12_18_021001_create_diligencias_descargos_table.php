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
        Schema::create('diligencias_descargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->onDelete('cascade');
            $table->dateTime('fecha_diligencia');
            $table->string('lugar_diligencia', 255);
            $table->boolean('trabajador_asistio');
            $table->text('motivo_inasistencia')->nullable();
            $table->string('acompanante_nombre', 255)->nullable();
            $table->string('acompanante_cargo', 255)->nullable();
            $table->json('preguntas_formuladas')->nullable();
            $table->json('respuestas')->nullable();
            $table->boolean('pruebas_aportadas')->default(false);
            $table->text('descripcion_pruebas')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('acta_generada')->default(false);
            $table->string('ruta_acta', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diligencias_descargos');
    }
};
