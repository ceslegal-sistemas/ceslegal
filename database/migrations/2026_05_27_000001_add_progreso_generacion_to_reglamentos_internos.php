<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('progreso_generacion', 150)->nullable()->after('mensaje_error_ia');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('progreso_generacion');
        });
    }
};
