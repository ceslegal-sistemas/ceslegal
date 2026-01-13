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
        Schema::create('procesos_disciplinarios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('restrict');
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('restrict');
            $table->foreignId('abogado_id')->constrained('users')->onDelete('restrict')->nullable();
            $table->enum('estado', [
                'apertura',
                'traslado',
                'descargos_pendientes',
                'descargos_realizados',
                'analisis_juridico',
                'pendiente_gerencia',
                'sancion_definida',
                'notificado',
                'impugnado',
                'cerrado',
                'archivado'
            ])->default('apertura');
            $table->text('hechos');
            $table->date('fecha_ocurrencia')->nullable();
            $table->text('normas_incumplidas')->nullable();
            $table->text('pruebas_iniciales')->nullable();
            $table->dateTime('fecha_solicitud')->nullable();
            $table->dateTime('fecha_apertura')->nullable();
            $table->dateTime('fecha_descargos_programada')->nullable();
            $table->dateTime('fecha_descargos_realizada')->nullable();
            $table->dateTime('fecha_analisis')->nullable();
            $table->boolean('decision_sancion')->nullable();
            $table->text('motivo_archivo')->nullable();
            $table->enum('tipo_sancion', ['llamado_atencion', 'suspension', 'terminacion'])->nullable();
            $table->dateTime('fecha_notificacion')->nullable();
            $table->dateTime('fecha_limite_impugnacion')->nullable();
            $table->boolean('impugnado')->default(false);
            $table->dateTime('fecha_impugnacion')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id', 'idx_empresa');
            $table->index('trabajador_id', 'idx_trabajador');
            $table->index('estado', 'idx_estado');
            $table->index('abogado_id', 'idx_abogado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procesos_disciplinarios');
    }
};
