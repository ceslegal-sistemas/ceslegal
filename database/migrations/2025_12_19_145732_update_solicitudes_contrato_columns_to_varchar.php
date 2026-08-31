<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN tipo_contrato VARCHAR(100) DEFAULT 'Contrato de Obra o Labor'");
            DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN estado VARCHAR(50) DEFAULT 'pendiente'");
        } else {
            // sqlite (usado por phpunit.xml en tests) nunca corrió el ALTER de
            // arriba - el enum original (2025_12_18_021013_create_solicitudes_contrato_table.php)
            // se traduce ahí como un CHECK constraint que solo permite los 6
            // valores viejos ('solicitado'/'en_analisis'/etc.), rechazando
            // CUALQUIER estado real usado hoy ('borrador'/'aprobado'/
            // 'rechazado') con "CHECK constraint failed: estado". Se replica
            // la misma conversión a VARCHAR vía Schema::change() (requiere
            // doctrine/dbal, ya instalado) para que la suite de tests use el
            // mismo tipo de columna que producción.
            Schema::table('solicitudes_contrato', function (Blueprint $table) {
                $table->string('tipo_contrato', 100)->default('Contrato de Obra o Labor')->change();
                $table->string('estado', 50)->default('pendiente')->change();
            });
        }
        // Actualizar valores antiguos al nuevo formato
        DB::table('solicitudes_contrato')->where('estado', 'solicitado')->update(['estado' => 'pendiente']);
        DB::table('solicitudes_contrato')->where('estado', 'cerrado')->update(['estado' => 'finalizado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN tipo_contrato ENUM('labor_obra') DEFAULT 'labor_obra'");
            DB::statement("ALTER TABLE solicitudes_contrato MODIFY COLUMN estado ENUM('solicitado', 'en_analisis', 'revision_objeto', 'contrato_generado', 'enviado_rrhh', 'cerrado') DEFAULT 'solicitado'");
        }
    }
};
