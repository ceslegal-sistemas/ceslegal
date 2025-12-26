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
            $table->json('articulos_legales_ids')->nullable()->after('normas_incumplidas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn('articulos_legales_ids');
        });
    }
};
