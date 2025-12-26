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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 255);
            $table->unsignedBigInteger('documentable_id');
            $table->enum('tipo_documento', [
                'apertura_proceso',
                'acta_descargos',
                'analisis_juridico',
                'memorando_llamado',
                'memorando_suspension',
                'memorando_terminacion',
                'contrato_labor_obra',
                'decision_impugnacion'
            ]);
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 500);
            $table->enum('formato', ['pdf', 'docx']);
            $table->foreignId('generado_por')->constrained('users')->onDelete('restrict');
            $table->integer('version')->default(1);
            $table->string('plantilla_usada', 255)->nullable();
            $table->json('variables_usadas')->nullable();
            $table->dateTime('fecha_generacion');
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'idx_documentable');
            $table->index('tipo_documento', 'idx_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
