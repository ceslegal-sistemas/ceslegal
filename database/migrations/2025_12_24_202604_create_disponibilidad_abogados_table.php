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
        Schema::create('disponibilidad_abogados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abogado_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->enum('tipo', ['presencial', 'telefonico', 'ambos'])->default('ambos');
            $table->boolean('disponible')->default(true);
            $table->foreignId('proceso_id')->nullable()->constrained('procesos_disciplinarios')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['abogado_id', 'fecha']);
            $table->index(['disponible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disponibilidad_abogados');
    }
};
