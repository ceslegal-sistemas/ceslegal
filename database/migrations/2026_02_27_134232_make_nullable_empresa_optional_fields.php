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
            $table->string('direccion')->nullable()->change();
            $table->string('telefono', 50)->nullable()->change();
            $table->string('email_contacto', 255)->nullable()->change();
            $table->string('ciudad', 100)->nullable()->change();
            $table->string('departamento', 100)->nullable()->change();
            $table->string('representante_legal', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('direccion')->nullable(false)->change();
            $table->string('telefono', 50)->nullable(false)->change();
            $table->string('email_contacto', 255)->nullable(false)->change();
            $table->string('ciudad', 100)->nullable(false)->change();
            $table->string('departamento', 100)->nullable(false)->change();
            $table->string('representante_legal', 255)->nullable(false)->change();
        });
    }
};
