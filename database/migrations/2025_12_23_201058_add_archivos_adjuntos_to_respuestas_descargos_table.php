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
        Schema::table('respuestas_descargos', function (Blueprint $table) {
            $table->json('archivos_adjuntos')->nullable()->after('respuesta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('respuestas_descargos', function (Blueprint $table) {
            $table->dropColumn('archivos_adjuntos');
        });
    }
};
