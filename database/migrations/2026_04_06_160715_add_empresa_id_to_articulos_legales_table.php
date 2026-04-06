<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            // NULL = artículo universal (CST, jurisprudencia)
            // empresa_id = artículo específico del RIT de esa empresa
            $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::table('articulos_legales', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropIndex(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
