<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->string('otp_codigo', 6)->nullable()->after('ip_acceso');
            $table->timestamp('otp_expira_en')->nullable()->after('otp_codigo');
            $table->timestamp('otp_verificado_en')->nullable()->after('otp_expira_en');
            $table->tinyInteger('otp_intentos')->default(0)->after('otp_verificado_en');
            $table->string('otp_canal', 20)->nullable()->after('otp_intentos');
            $table->string('otp_enviado_a', 255)->nullable()->after('otp_canal');
            $table->timestamp('disclaimer_aceptado_en')->nullable()->after('otp_enviado_a');
            $table->string('disclaimer_ip', 45)->nullable()->after('disclaimer_aceptado_en');
            $table->string('foto_inicio_path')->nullable()->after('disclaimer_ip');
            $table->timestamp('foto_inicio_en')->nullable()->after('foto_inicio_path');
            $table->string('foto_fin_path')->nullable()->after('foto_inicio_en');
            $table->timestamp('foto_fin_en')->nullable()->after('foto_fin_path');
            $table->json('evidencia_metadata')->nullable()->after('foto_fin_en');
        });
    }

    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn([
                'otp_codigo',
                'otp_expira_en',
                'otp_verificado_en',
                'otp_intentos',
                'otp_canal',
                'otp_enviado_a',
                'disclaimer_aceptado_en',
                'disclaimer_ip',
                'foto_inicio_path',
                'foto_inicio_en',
                'foto_fin_path',
                'foto_fin_en',
                'evidencia_metadata',
            ]);
        });
    }
};
