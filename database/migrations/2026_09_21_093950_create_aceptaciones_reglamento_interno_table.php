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
        Schema::create('aceptaciones_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->onDelete('cascade');
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->onDelete('cascade');
            $table->timestamp('aceptado_en');
            $table->string('ip_aceptacion', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['trabajador_id', 'reglamento_interno_id'], 'aceptacion_rit_trabajador_reglamento_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aceptaciones_reglamento_interno');
    }
};
