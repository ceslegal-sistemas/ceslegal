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
            $table->string('area_practica_otro', 100)->nullable()->after('area_practica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->dropColumn('area_practica_otro');
        });
    }
};
