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
        Schema::table('sancion_laborals', function (Blueprint $table) {
            // Referencia a la sanción padre (primera vez) para agrupar reincidencias
            $table->foreignId('sancion_padre_id')->nullable()->after('orden')
                ->constrained('sancion_laborals')->nullOnDelete();
            // Orden de reincidencia: 1 = primera vez, 2 = segunda vez, etc.
            $table->unsignedTinyInteger('orden_reincidencia')->nullable()->after('sancion_padre_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sancion_laborals', function (Blueprint $table) {
            $table->dropForeign(['sancion_padre_id']);
            $table->dropColumn(['sancion_padre_id', 'orden_reincidencia']);
        });
    }
};
