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
        Schema::create('preguntas_descargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diligencia_descargo_id')->constrained('diligencias_descargos')->cascadeOnDelete();
            $table->text('pregunta');
            $table->integer('orden')->default(0);
            $table->boolean('es_generada_por_ia')->default(false);
            $table->foreignId('pregunta_padre_id')->nullable()->constrained('preguntas_descargos')->nullOnDelete();
            $table->enum('estado', ['activa', 'respondida'])->default('activa');
            $table->timestamps();

            $table->index('diligencia_descargo_id');
            $table->index(['diligencia_descargo_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preguntas_descargos');
    }
};
