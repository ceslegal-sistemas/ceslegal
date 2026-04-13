<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragmentos_documento', function (Blueprint $table) {
            $table->id();

            $table->foreignId('documento_legal_id')
                ->constrained('documentos_legales')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('orden');
            $table->mediumText('contenido');
            $table->json('embedding')->nullable()
                ->comment('Vector Gemini gemini-embedding-001 (3072 dims)');

            $table->index(['documento_legal_id', 'orden']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragmentos_documento');
    }
};
