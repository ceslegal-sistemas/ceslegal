<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->unique()->after('id');
            $table->date('fecha_gestion')->nullable()->after('mes');
            $table->json('adjuntos')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'fecha_gestion', 'adjuntos']);
        });
    }
};
