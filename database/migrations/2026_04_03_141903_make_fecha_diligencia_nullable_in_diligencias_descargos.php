<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dateTime('fecha_diligencia')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dateTime('fecha_diligencia')->nullable(false)->change();
        });
    }
};
