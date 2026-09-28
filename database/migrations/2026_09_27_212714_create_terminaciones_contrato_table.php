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
        Schema::create('terminaciones_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_contrato_id')->constrained('solicitudes_contrato');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('abogado_id')->nullable()->constrained('users');

            // string, no enum: evita el gotcha ya conocido en este proyecto
            // (ALTER TABLE manual si se agrega un valor nuevo - ver
            // ModificacionContractual).
            $table->string('tipo', 30);
            $table->text('motivo')->nullable();
            $table->date('fecha_terminacion');

            $table->decimal('salario_base_calculo', 12, 2)->nullable();
            $table->unsignedInteger('dias_indemnizacion')->nullable();
            $table->decimal('monto_indemnizacion', 12, 2)->nullable();
            $table->text('detalle_calculo')->nullable();

            $table->text('texto_documento_redactado')->nullable();
            $table->string('ruta_documento')->nullable();
            $table->timestamp('fecha_generacion_documento')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminaciones_contrato');
    }
};
