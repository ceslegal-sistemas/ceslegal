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
        Schema::create('fragmentos_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')
                ->constrained('reglamentos_internos')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->mediumText('contenido');
            $table->json('embedding')->nullable();
            $table->index(['reglamento_interno_id', 'orden']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fragmentos_reglamento_interno');
    }
};
