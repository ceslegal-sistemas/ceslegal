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
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->timestamp('primer_acceso_en')->nullable()->after('trabajador_accedio_en');
            $table->timestamp('tiempo_limite')->nullable()->after('primer_acceso_en');
            $table->boolean('tiempo_expirado')->default(false)->after('tiempo_limite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn(['primer_acceso_en', 'tiempo_limite', 'tiempo_expirado']);
        });
    }
};
