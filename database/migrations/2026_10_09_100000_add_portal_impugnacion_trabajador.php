<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portal público de impugnación: el trabajador ya no depende de que la empresa
 * "registre" su impugnación en el panel (no tiene acceso), la presenta él mismo
 * con un enlace personal (token), verificación por código al correo (OTP) y
 * constancia. Aquí van el token + el estado del OTP en el proceso, y la
 * evidencia (IP, navegador, verificación) en la propia impugnación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->string('impugnacion_token', 64)->nullable()->unique()->after('fecha_limite_impugnacion');
            $table->string('impugnacion_otp_hash', 64)->nullable()->after('impugnacion_token');
            $table->dateTime('impugnacion_otp_enviado_en')->nullable()->after('impugnacion_otp_hash');
            $table->dateTime('impugnacion_otp_expira_en')->nullable()->after('impugnacion_otp_enviado_en');
            $table->unsignedTinyInteger('impugnacion_otp_intentos')->default(0)->after('impugnacion_otp_expira_en');
            $table->dateTime('impugnacion_otp_verificado_en')->nullable()->after('impugnacion_otp_intentos');
        });

        Schema::table('impugnaciones', function (Blueprint $table) {
            // 'trabajador' = la presentó él mismo en el portal; 'empresa' = la registró
            // la empresa a nombre del trabajador (carta física, verbal, correo...).
            $table->string('registrada_por', 20)->default('empresa')->after('medio_recepcion');
            $table->string('ip_registro', 45)->nullable()->after('registrada_por');
            $table->text('user_agent')->nullable()->after('ip_registro');
            $table->dateTime('otp_verificado_en')->nullable()->after('user_agent');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN medio_recepcion ENUM('correo_electronico','carta_fisica','verbal','otro','portal_web') NULL");
        } else {
            // SQLite (tests: phpunit.xml usa DB_CONNECTION=sqlite en memoria) no
            // soporta ALTER ... MODIFY COLUMN - Laravel reconstruye la tabla por
            // debajo con doctrine/dbal al usar ->change(). Sin esta rama, el CHECK
            // constraint original (solo 4 valores, sin 'portal_web') se quedaba
            // vigente en cualquier base de datos sqlite, y cualquier impugnación
            // real del portal (ImpugnacionPortalService::class, que sí guarda
            // 'portal_web') rompía con "CHECK constraint failed: medio_recepcion"
            // - bug real detectado en ImpugnacionPortalTrabajadorTest, 2026-10-14.
            Schema::table('impugnaciones', function (Blueprint $table) {
                $table->enum('medio_recepcion', ['correo_electronico', 'carta_fisica', 'verbal', 'otro', 'portal_web'])
                    ->nullable()
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE impugnaciones SET medio_recepcion = 'otro' WHERE medio_recepcion = 'portal_web'");
            DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN medio_recepcion ENUM('correo_electronico','carta_fisica','verbal','otro') NULL");
        } else {
            DB::table('impugnaciones')->where('medio_recepcion', 'portal_web')->update(['medio_recepcion' => 'otro']);
            Schema::table('impugnaciones', function (Blueprint $table) {
                $table->enum('medio_recepcion', ['correo_electronico', 'carta_fisica', 'verbal', 'otro'])
                    ->nullable()
                    ->change();
            });
        }

        Schema::table('impugnaciones', function (Blueprint $table) {
            $table->dropColumn(['registrada_por', 'ip_registro', 'user_agent', 'otp_verificado_en']);
        });

        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropUnique(['impugnacion_token']);
            $table->dropColumn([
                'impugnacion_token', 'impugnacion_otp_hash', 'impugnacion_otp_enviado_en',
                'impugnacion_otp_expira_en', 'impugnacion_otp_intentos', 'impugnacion_otp_verificado_en',
            ]);
        });
    }
};
