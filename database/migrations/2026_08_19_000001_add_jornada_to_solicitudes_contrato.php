<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->string('jornada')->nullable()->after('cargo_contrato');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->dropColumn('jornada');
        });
    }
};
