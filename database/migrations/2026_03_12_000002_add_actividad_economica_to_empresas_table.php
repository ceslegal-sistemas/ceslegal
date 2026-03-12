<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('actividad_economica_id')
                ->nullable()
                ->after('dias_laborales')
                ->constrained('actividades_economicas')
                ->nullOnDelete();
        });

        Schema::create('empresa_actividades_secundarias', function (Blueprint $table) {
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actividad_economica_id')->constrained('actividades_economicas')->cascadeOnDelete();
            $table->primary(['empresa_id', 'actividad_economica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_actividades_secundarias');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['actividad_economica_id']);
            $table->dropColumn('actividad_economica_id');
        });
    }
};
