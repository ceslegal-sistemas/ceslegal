<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de RIT unificada + gate en el registro de empresa.
 *
 * - empresa_id pasa a nullable: permite auditar un RIT dentro del wizard de creación
 *   de empresa ANTES de que la empresa exista (auditoría temporal que luego se enlaza).
 * - Snapshot de razón social para cuando aún no hay empresa.
 * - Datos del responsable que autoriza actualizar el RIT con las sugerencias (audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. empresa_id → nullable (requiere soltar la FK antes de cambiar el tipo).
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
        });

        // 2. Campos nuevos.
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->string('razon_social_snapshot')->nullable()->after('texto_auditado');
            $table->foreignId('iniciado_por_user_id')->nullable()->after('razon_social_snapshot');

            // Aceptación con autoridad (Bloque 6 del spec).
            $table->string('responsable_nombre')->nullable();
            $table->string('responsable_documento')->nullable();
            $table->string('responsable_cargo')->nullable();
            $table->boolean('autoridad_declarada')->default(false);
            $table->timestamp('autoridad_declarada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropColumn([
                'razon_social_snapshot',
                'iniciado_por_user_id',
                'responsable_nombre',
                'responsable_documento',
                'responsable_cargo',
                'autoridad_declarada',
                'autoridad_declarada_at',
            ]);
        });

        // Revertir empresa_id a NOT NULL (las filas temporales deben limpiarse antes).
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
            $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
        });
    }
};
