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
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('telefono', 50)->nullable()->change();
            $table->string('email_contacto', 255)->nullable()->change();
            $table->text('direccion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('telefono', 50)->nullable(false)->change();
            $table->string('email_contacto', 255)->nullable(false)->change();
            $table->text('direccion')->nullable(false)->change();
        });
    }
};