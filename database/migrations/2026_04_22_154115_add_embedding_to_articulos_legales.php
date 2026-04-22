<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            // Vector de embedding Gemini para búsqueda semántica (RAG)
            $table->longText('embedding')->nullable()->after('texto_completo');
            // ID de empresa propietaria (null = universal / CST / jurisprudencia)
            if (!Schema::hasColumn('articulos_legales', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('embedding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            $table->dropColumn('embedding');
            if (Schema::hasColumn('articulos_legales', 'empresa_id')) {
                $table->dropColumn('empresa_id');
            }
        });
    }
};
