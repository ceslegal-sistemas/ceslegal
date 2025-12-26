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
        Schema::create('trazabilidad_ia_descargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diligencia_descargo_id')->constrained('diligencias_descargos')->cascadeOnDelete();
            $table->longText('prompt_enviado');
            $table->longText('respuesta_recibida');
            $table->enum('tipo', ['generacion_preguntas', 'analisis_respuestas'])->default('generacion_preguntas');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('diligencia_descargo_id');
            $table->index(['diligencia_descargo_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trazabilidad_ia_descargos');
    }
};
