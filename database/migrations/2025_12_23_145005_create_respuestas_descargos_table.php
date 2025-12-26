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
        Schema::create('respuestas_descargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_descargo_id')->constrained('preguntas_descargos')->cascadeOnDelete();
            $table->text('respuesta');
            $table->timestamp('respondido_en')->nullable();
            $table->timestamps();

            $table->index('pregunta_descargo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respuestas_descargos');
    }
};
