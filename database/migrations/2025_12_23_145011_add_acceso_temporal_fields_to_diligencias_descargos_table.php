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
            $table->string('token_acceso', 64)->unique()->nullable()->after('respuestas');
            $table->timestamp('token_expira_en')->nullable()->after('token_acceso');
            $table->boolean('acceso_habilitado')->default(false)->after('token_expira_en');
            $table->date('fecha_acceso_permitida')->nullable()->after('acceso_habilitado');
            $table->timestamp('trabajador_accedio_en')->nullable()->after('fecha_acceso_permitida');
            $table->string('ip_acceso', 45)->nullable()->after('trabajador_accedio_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn([
                'token_acceso',
                'token_expira_en',
                'acceso_habilitado',
                'fecha_acceso_permitida',
                'trabajador_accedio_en',
                'ip_acceso'
            ]);
        });
    }
};
