<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            // Inicio del período de contrato VIGENTE (no el original) - se llena
            // con fecha_inicio_propuesta al crear, y se actualiza en cada
            // prórroga (manual u automática) para poder calcular "el mismo
            // período" que exige el Art. 46 CST.
            $table->date('fecha_inicio_periodo_actual')->nullable()->after('fecha_inicio_propuesta');
            // Cuenta prórrogas ya aplicadas (manuales + automáticas), para la
            // regla del Art. 46: tras la 4a prórroga, mínimo 1 año.
            $table->unsignedTinyInteger('veces_prorrogado')->default(0)->after('fecha_inicio_periodo_actual');
            $table->timestamp('decision_no_renovacion_en')->nullable()->after('veces_prorrogado');
            $table->string('ruta_preaviso')->nullable()->after('decision_no_renovacion_en');
            // Para que el cliente entienda por qué cambió la fecha si no
            // alcanzó a decidir a tiempo.
            $table->timestamp('renovado_automaticamente_en')->nullable()->after('ruta_preaviso');
            // Se activa cuando la renovación automática superaría el tope de
            // 4 años del Art. 46 - no se aplica sola, se alerta para revisión
            // manual (caso raro, de alto riesgo legal).
            $table->boolean('requiere_revision_manual_renovacion')->default(false)->after('renovado_automaticamente_en');
            // Evita notificar el mismo aviso de "por vencer" todos los días
            // mientras dure la ventana de 45 días - se resetea a null en
            // cada renovación (nuevo período, nueva ventana de alerta).
            $table->timestamp('notificado_vencimiento_en')->nullable()->after('requiere_revision_manual_renovacion');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_contrato', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_inicio_periodo_actual',
                'veces_prorrogado',
                'decision_no_renovacion_en',
                'ruta_preaviso',
                'renovado_automaticamente_en',
                'requiere_revision_manual_renovacion',
                'notificado_vencimiento_en',
            ]);
        });
    }
};
