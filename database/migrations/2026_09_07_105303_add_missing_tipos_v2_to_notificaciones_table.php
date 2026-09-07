<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug real encontrado (2026-09-07) al escribir un test para el fix de
 * notificaciones huérfanas de sugerencias de RIT: NotificacionService::crear()
 * usa 6 valores de 'tipo' que el ENUM real de esta tabla NUNCA incluyó -
 * 'sugerencia_actualizacion_rit', 'contrato_por_vencer',
 * 'contrato_renovado_automaticamente', 'contrato_requiere_revision_manual',
 * 'descargos_no_realizados', 'solicitud_cambio_empresa'. En MySQL sin modo
 * estricto un valor de ENUM inválido se trunca en silencio a '' en vez de
 * fallar - exactamente el mismo tipo de corrupción silenciosa ya
 * documentada en 2026_09_02_151002_add_plazo_to_modificaciones_contractuales_
 * tipo_enum.php para otro ENUM de este proyecto. Filas ya insertadas en
 * producción con estos tipos pueden tener 'tipo' vacío o corrupto - esta
 * migración no las repara (no hay forma de recuperar el valor original de
 * un ENUM ya truncado), solo evita que seguidan corrompiéndose desde ahora.
 */
return new class extends Migration
{
    private const TIPOS = [
        'apertura',
        'descargos_pendientes',
        'descargos_realizados',
        'descargos_no_realizados',
        'termino_vencido',
        'sancion_emitida',
        'impugnacion_realizada',
        'cerrado',
        'contrato_generado',
        'contrato_por_vencer',
        'contrato_renovado_automaticamente',
        'contrato_requiere_revision_manual',
        'sugerencia_actualizacion_rit',
        'solicitud_cambio_empresa',
    ];

    private const TIPOS_ANTERIORES = [
        'apertura',
        'descargos_pendientes',
        'descargos_realizados',
        'termino_vencido',
        'sancion_emitida',
        'impugnacion_realizada',
        'cerrado',
        'contrato_generado',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('notificaciones', function (Blueprint $table) {
                $table->enum('tipo', self::TIPOS)->change();
            });
            return;
        }

        $lista = "'" . implode("', '", self::TIPOS) . "'";
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM({$lista}) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('notificaciones', function (Blueprint $table) {
                $table->enum('tipo', self::TIPOS_ANTERIORES)->change();
            });
            return;
        }

        $lista = "'" . implode("', '", self::TIPOS_ANTERIORES) . "'";
        DB::statement("ALTER TABLE notificaciones MODIFY COLUMN tipo ENUM({$lista}) NOT NULL");
    }
};
