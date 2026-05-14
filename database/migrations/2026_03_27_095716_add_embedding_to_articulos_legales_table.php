<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            // Vector embedding (Gemini text-embedding-004 → 768 dimensiones)
            $table->json('embedding')->nullable()->after('orden');
            // Texto completo del artículo (puede diferir de 'descripcion' que es más corta)
            $table->text('texto_completo')->nullable()->after('descripcion');
            // Fuente legal para el RAG
            $table->string('fuente', 100)->nullable()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'texto_completo', 'fuente']);
        });
    }
};
