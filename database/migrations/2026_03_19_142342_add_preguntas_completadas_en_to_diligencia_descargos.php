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
            $table->timestamp('preguntas_completadas_en')->nullable()->after('primer_acceso_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn('preguntas_completadas_en');
        });
    }
};
