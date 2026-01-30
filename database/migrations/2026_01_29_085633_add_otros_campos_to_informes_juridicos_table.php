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
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->string('tipo_gestion_otro', 100)->nullable()->after('tipo_gestion');
            $table->string('subtipo_otro', 100)->nullable()->after('subtipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->dropColumn(['tipo_gestion_otro', 'subtipo_otro']);
        });
    }
};
