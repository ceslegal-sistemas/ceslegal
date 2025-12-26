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
        Schema::create('sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->onDelete('cascade');
            $table->enum('tipo_sancion', ['llamado_atencion', 'suspension', 'terminacion']);
            $table->integer('dias_suspension')->nullable();
            $table->date('fecha_inicio_suspension')->nullable();
            $table->date('fecha_fin_suspension')->nullable();
            $table->text('motivo_sancion');
            $table->text('fundamento_legal');
            $table->text('observaciones')->nullable();
            $table->boolean('documento_generado')->default(false);
            $table->string('ruta_documento', 255)->nullable();
            $table->dateTime('fecha_notificacion_rrhh')->nullable();
            $table->dateTime('fecha_notificacion_trabajador')->nullable();
            $table->string('notificado_por', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanciones');
    }
};
