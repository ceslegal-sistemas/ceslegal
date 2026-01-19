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
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->string('lugar_especifico', 255)->nullable()->after('lugar_diligencia');
            $table->text('link_reunion')->nullable()->after('lugar_especifico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn(['lugar_especifico', 'link_reunion']);
        });
    }
};
