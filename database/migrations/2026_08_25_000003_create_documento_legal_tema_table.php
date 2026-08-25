<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_legal_tema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_legal_id')->constrained('documentos_legales')->cascadeOnDelete();
            $table->foreignId('tema_normativo_id')->constrained('temas_normativos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['documento_legal_id', 'tema_normativo_id'], 'documento_tema_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_legal_tema');
    }
};
