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
        Schema::create('articulos_legales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50); // Ej: "Art. 58", "Art. 60 Num. 1"
            $table->string('titulo'); // Ej: "Obligaciones especiales del trabajador"
            $table->text('descripcion'); // Descripción completa del artículo
            $table->string('categoria', 100)->nullable(); // Ej: "Obligaciones", "Prohibiciones", etc.
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0); // Para ordenar en el selector
            $table->timestamps();

            $table->index('codigo');
            $table->index('categoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articulos_legales');
    }
};
