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
        Schema::create('solicitudes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('restrict');
            $table->foreignId('abogado_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->enum('estado', [
                'solicitado',
                'en_analisis',
                'revision_objeto',
                'contrato_generado',
                'enviado_rrhh',
                'cerrado'
            ])->default('solicitado');
            $table->enum('tipo_contrato', ['labor_obra'])->default('labor_obra');
            $table->dateTime('fecha_solicitud');
            $table->foreignId('trabajador_id')->nullable()->constrained('trabajadores')->onDelete('restrict');
            $table->string('trabajador_nombres', 255);
            $table->string('trabajador_apellidos', 255);
            $table->enum('trabajador_documento_tipo', ['CC', 'CE', 'TI', 'PASS']);
            $table->string('trabajador_documento_numero', 50);
            $table->string('trabajador_email', 255)->nullable();
            $table->string('trabajador_telefono', 50)->nullable();
            $table->text('trabajador_direccion')->nullable();
            $table->string('cargo_contrato', 255);
            $table->text('responsabilidades');
            $table->text('objeto_comercial');
            $table->text('manual_funciones');
            $table->string('ruta_orden_compra', 255)->nullable();
            $table->string('ruta_manual_funciones', 255)->nullable();
            $table->date('fecha_inicio_propuesta')->nullable();
            $table->decimal('salario_propuesto', 15, 2)->nullable();
            $table->dateTime('fecha_analisis')->nullable();
            $table->text('objeto_juridico_redactado')->nullable();
            $table->text('observaciones_juridicas')->nullable();
            $table->dateTime('fecha_generacion_contrato')->nullable();
            $table->string('ruta_contrato', 255)->nullable();
            $table->dateTime('fecha_envio_rrhh')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id', 'idx_empresa');
            $table->index('estado', 'idx_estado');
            $table->index('abogado_id', 'idx_abogado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_contrato');
    }
};
