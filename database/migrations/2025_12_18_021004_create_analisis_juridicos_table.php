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
        Schema::create('analisis_juridicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->onDelete('cascade');
            $table->foreignId('abogado_id')->constrained('users')->onDelete('restrict');
            $table->dateTime('fecha_analisis');
            $table->text('analisis_hechos');
            $table->text('analisis_pruebas');
            $table->text('analisis_normativo');
            $table->text('conclusion');
            $table->enum('recomendacion', ['archivo', 'sancion']);
            $table->enum('tipo_sancion_recomendada', ['llamado_atencion', 'suspension', 'terminacion'])->nullable();
            $table->text('fundamento_legal');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_juridicos');
    }
};
