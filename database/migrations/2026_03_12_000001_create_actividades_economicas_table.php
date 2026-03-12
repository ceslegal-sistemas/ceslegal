<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_economicas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique()->comment('Código CIIU, ej: 4711');
            $table->string('seccion', 2)->comment('Letra de sección, ej: G');
            $table->string('nombre_seccion')->comment('Nombre de la sección CIIU');
            $table->string('nombre')->comment('Nombre de la clase/actividad');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['seccion', 'activo']);
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_economicas');
    }
};
