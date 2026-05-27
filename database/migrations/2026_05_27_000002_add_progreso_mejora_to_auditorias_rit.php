<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->string('progreso_mejora', 150)->nullable()->after('estado_mejora');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropColumn('progreso_mejora');
        });
    }
};
