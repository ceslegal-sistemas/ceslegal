<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglamento_interno_tema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->foreignId('tema_normativo_id')->constrained('temas_normativos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reglamento_interno_id', 'tema_normativo_id'], 'rit_tema_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglamento_interno_tema');
    }
};
