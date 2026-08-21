<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloques_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->text('contenido');
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['reglamento_interno_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques_reglamento_interno');
    }
};
