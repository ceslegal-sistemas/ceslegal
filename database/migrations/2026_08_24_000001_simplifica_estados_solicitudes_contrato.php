<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catch-all, no una lista explícita de los 3 valores conocidos hoy:
        // el ENUM original de esta tabla también tenía 'revision_objeto' y
        // 'enviado_rrhh' como valores legítimos en algún momento (ver el
        // down() de la migración 2025_12_19_145732_update_solicitudes_contrato_columns_to_varchar.php).
        // Una lista explícita dejaría huérfana cualquier fila que aún tenga
        // uno de esos valores viejos - invisible a las 3 Table Actions
        // nuevas, que solo reconocen 'borrador'/'aprobado'/'rechazado'.
        DB::table('solicitudes_contrato')
            ->where('estado', 'finalizado')
            ->update(['estado' => 'aprobado']);

        DB::table('solicitudes_contrato')
            ->whereNotIn('estado', ['aprobado', 'rechazado'])
            ->update(['estado' => 'borrador']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitudes_contrato ALTER COLUMN estado SET DEFAULT 'borrador'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitudes_contrato ALTER COLUMN estado SET DEFAULT 'pendiente'");
        }

        // No hay reversión fiel posible (no se puede saber si 'borrador'
        // venía de 'pendiente', 'en_analisis' o 'contrato_generado') - se
        // deja en el valor más neutro.
        DB::table('solicitudes_contrato')
            ->where('estado', 'borrador')
            ->update(['estado' => 'pendiente']);

        DB::table('solicitudes_contrato')
            ->where('estado', 'aprobado')
            ->update(['estado' => 'finalizado']);
    }
};
