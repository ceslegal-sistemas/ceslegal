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
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropForeign(['disponibilidad_id']);
            $table->dropColumn('disponibilidad_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->foreignId('disponibilidad_id')
                ->nullable()
                ->after('fecha_descargos_programada')
                ->constrained('disponibilidad_abogados')
                ->nullOnDelete();
        });
    }
};
