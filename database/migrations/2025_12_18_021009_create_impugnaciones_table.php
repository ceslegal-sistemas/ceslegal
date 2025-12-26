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
        Schema::create('impugnaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->onDelete('cascade');
            $table->foreignId('sancion_id')->constrained('sanciones')->onDelete('cascade');
            $table->dateTime('fecha_impugnacion');
            $table->text('motivos_impugnacion');
            $table->text('pruebas_adicionales')->nullable();
            $table->dateTime('fecha_analisis_impugnacion')->nullable();
            $table->foreignId('abogado_analisis_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->text('analisis_impugnacion')->nullable();
            $table->enum('decision_final', ['confirma_sancion', 'revoca_sancion', 'modifica_sancion'])->nullable();
            $table->enum('nueva_sancion_tipo', ['llamado_atencion', 'suspension', 'terminacion'])->nullable();
            $table->text('fundamento_decision')->nullable();
            $table->dateTime('fecha_decision')->nullable();
            $table->boolean('documento_generado')->default(false);
            $table->string('ruta_documento', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impugnaciones');
    }
};
