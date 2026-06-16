<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurisprudencia como unidad discreta y curada (no RAG por fragmentos).
 *
 * Se indexa un EXTRACTO (tema + tesis/sub-regla) que la IA usa entero y cita;
 * se conserva el texto_completo como fuente de verdad. El embedding es del
 * extracto (cabe completo, alta precisión). 'activo' es la compuerta de
 * curaduría: solo las activas las usa la IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisprudencias', function (Blueprint $table) {
            $table->id();
            $table->string('referencia')->unique();          // T-1040/2006
            $table->string('tipo')->default('sentencia_cc');  // sentencia_cc / csj / ce
            $table->string('corporacion')->default('Corte Constitucional');
            $table->date('fecha')->nullable();
            $table->string('magistrado')->nullable();
            $table->string('tema')->nullable();               // descriptor corto
            $table->text('tesis')->nullable();                // extracto / sub-regla (lo indexado)
            $table->longText('texto_completo')->nullable();   // fuente de verdad
            $table->json('embedding')->nullable();            // embedding del extracto
            $table->enum('estado', ['pendiente', 'procesando', 'procesado', 'error'])->default('pendiente');
            $table->text('error_mensaje')->nullable();
            $table->unsignedInteger('total_palabras')->default(0);
            $table->boolean('activo')->default(false)->index(); // curaduría: revisar antes de activar
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisprudencias');
    }
};
