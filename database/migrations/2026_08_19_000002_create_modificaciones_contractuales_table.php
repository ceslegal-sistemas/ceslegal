<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificaciones_contractuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_contrato_id')->constrained('solicitudes_contrato');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('abogado_id')->nullable()->constrained('users');

            $table->enum('tipo_modificacion', ['salario', 'cargo', 'jornada', 'tipo_contrato']);
            $table->string('valor_anterior')->nullable();
            $table->string('valor_nuevo');
            $table->text('justificacion')->nullable();
            $table->date('fecha_efectiva');

            $table->text('texto_otrosi_redactado')->nullable();
            $table->string('ruta_otrosi')->nullable();
            $table->timestamp('fecha_generacion_otrosi')->nullable();

            $table->enum('estado', ['borrador', 'otrosi_generado'])->default('borrador');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_contractuales');
    }
};
