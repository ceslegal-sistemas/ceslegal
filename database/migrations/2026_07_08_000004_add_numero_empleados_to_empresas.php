<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de empleados permanentes de la empresa. Junto con la actividad económica
 * determina si la empresa está obligada a tener Reglamento Interno de Trabajo
 * (Art. 105 CST: comercial >5, industrial >10, agropecuaria/forestal >20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedInteger('numero_empleados')->nullable()->after('actividad_economica_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('numero_empleados');
        });
    }
};
