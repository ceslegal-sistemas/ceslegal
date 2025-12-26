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
        Schema::create('terminos_legales', function (Blueprint $table) {
            $table->id();
            $table->enum('proceso_tipo', ['proceso_disciplinario', 'contrato']);
            $table->unsignedBigInteger('proceso_id');
            $table->enum('termino_tipo', [
                'traslado_descargos',
                'impugnacion',
                'analisis_juridico',
                'respuesta_gerencia'
            ]);
            $table->dateTime('fecha_inicio');
            $table->integer('dias_habiles');
            $table->dateTime('fecha_vencimiento');
            $table->integer('dias_transcurridos')->default(0);
            $table->enum('estado', ['activo', 'vencido', 'cerrado'])->default('activo');
            $table->dateTime('fecha_cierre')->nullable();
            $table->boolean('notificacion_enviada')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['proceso_tipo', 'proceso_id'], 'idx_proceso');
            $table->index('estado', 'idx_estado');
            $table->index('fecha_vencimiento', 'idx_vencimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminos_legales');
    }
};
