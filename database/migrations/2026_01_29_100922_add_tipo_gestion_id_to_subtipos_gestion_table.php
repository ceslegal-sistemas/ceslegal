<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtipos_gestion', function (Blueprint $table) {
            $table->foreignId('tipo_gestion_id')->nullable()->after('id')->constrained('tipos_gestion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subtipos_gestion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_gestion_id');
        });
    }
};
