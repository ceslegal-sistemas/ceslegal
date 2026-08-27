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
        Schema::create('sugerencias_actualizacion_rit', function (Blueprint $table) {
            $table->id();
            // Denormalizado desde reglamentos_internos.empresa_id al crear -
            // ScopedToBufeteOrEmpresa exige esta columna directa en el propio
            // modelo (no resuelve el scope a traves de una relacion), mismo
            // patron ya usado en ModificacionContractual.
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos');
            $table->foreignId('documento_legal_id')->constrained('documentos_legales');
            // Indice dentro del arreglo que devuelve RitDiffService::partirEnBloques()
            // sobre el texto_completo del RIT en el momento de evaluar el cambio -
            // ver nota en el modelo sobre por que esto puede quedar desalineado
            // si el RIT cambia entre que se propone y se aprueba la sugerencia.
            $table->unsignedInteger('bloque_indice');
            $table->enum('tipo_cambio', ['modificar', 'agregar', 'eliminar']);
            $table->text('texto_anterior')->nullable();
            $table->text('texto_propuesto')->nullable();
            $table->text('justificacion_ia');
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('resuelto_por')->nullable()->constrained('users');
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sugerencias_actualizacion_rit');
    }
};
