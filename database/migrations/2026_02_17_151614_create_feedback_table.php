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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('calificacion')->comment('Rating 1-5 stars');
            $table->text('sugerencia')->nullable()->comment('User suggestions');
            $table->string('tipo')->comment('Type: descargo_trabajador, descargo_registro');
            $table->foreignId('proceso_disciplinario_id')->nullable()->constrained('procesos_disciplinarios')->nullOnDelete();
            $table->foreignId('diligencia_descargo_id')->nullable()->constrained('diligencias_descargos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
