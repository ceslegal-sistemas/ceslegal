<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación fotográfica del responsable que acepta las sugerencias del RIT.
 * Equivalencia funcional de firma manuscrita (Ley 527/1999 Art. 7-8, Decreto 2364/2012):
 * refuerza la declaración de autoridad + datos del responsable ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->string('responsable_foto_path')->nullable()->after('autoridad_declarada_at');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropColumn('responsable_foto_path');
        });
    }
};
