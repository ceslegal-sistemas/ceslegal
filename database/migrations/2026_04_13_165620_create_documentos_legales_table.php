<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_legales', function (Blueprint $table) {
            $table->id();

            $table->string('titulo');
            $table->enum('tipo', [
                'sentencia_cc',        // Corte Constitucional
                'sentencia_csj',       // Corte Suprema de Justicia
                'sentencia_ce',        // Consejo de Estado
                'cst',                 // Código Sustantivo del Trabajo
                'ley',                 // Leyes (Ley 2466/2025, etc.)
                'concepto_ministerio', // Conceptos del Ministerio de Trabajo
                'doctrina',            // Libros y artículos doctrinales
                'rit_referencia',      // Reglamentos internos de referencia
                'otro',
            ])->default('otro');

            $table->string('referencia')->nullable()
                ->comment('Ej: T-239/2021, Art. 115 CST, Ley 2466/2025');
            $table->text('descripcion')->nullable();

            $table->string('archivo_path', 500)->nullable()
                ->comment('Ruta del archivo PDF o DOCX subido');
            $table->string('archivo_nombre_original', 255)->nullable();

            $table->enum('estado', ['pendiente', 'procesando', 'procesado', 'error'])
                ->default('pendiente');
            $table->text('error_mensaje')->nullable();

            $table->unsignedInteger('total_fragmentos')->default(0);
            $table->unsignedInteger('total_palabras')->default(0);

            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_legales');
    }
};
