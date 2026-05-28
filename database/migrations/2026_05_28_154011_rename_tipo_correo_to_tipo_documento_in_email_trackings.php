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
        Schema::table('email_trackings', function (Blueprint $table) {
            $table->renameColumn('tipo_correo', 'tipo_documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_trackings', function (Blueprint $table) {
            $table->renameColumn('tipo_documento', 'tipo_correo');
        });
    }
};
