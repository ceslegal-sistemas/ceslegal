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
        Schema::table('impugnaciones', function (Blueprint $table) {
            $table->enum('medio_recepcion', ['correo_electronico', 'carta_fisica', 'verbal', 'otro'])
                ->nullable()
                ->after('fecha_impugnacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impugnaciones', function (Blueprint $table) {
            $table->dropColumn('medio_recepcion');
        });
    }
};
