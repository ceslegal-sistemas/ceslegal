<?php

namespace App\Services;

use App\Models\DocumentoLegal;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\SolicitudContrato;
use App\Models\SugerenciaActualizacionRit;
use App\Models\Trabajador;
use App\Models\User;

class AsistentePanelService
{
    public function resolverContexto(User $usuario, ?Empresa $empresa): array
    {
        if (! $empresa) {
            return [];
        }

        return [
            'empresa_nombre' => $empresa->nombre_completo,
            'rit' => $this->resolverContextoRit($empresa),
            'procesos_disciplinarios_abiertos' => ProcesoDisciplinario::where('empresa_id', $empresa->id)
                ->whereNotIn('estado', ['cerrado', 'archivado'])
                ->count(),
            // 'numero_empleados' es un campo declarado por el cliente al
            // construir el RIT (para categorizar la empresa por tamaño) -
            // puede quedar vacío aunque ya haya trabajadores reales
            // cargados. 'trabajadores_registrados' es el conteo real de
            // registros de la tabla Trabajador - bug real reportado por el
            // usuario: preguntó "¿cuántos trabajadores tengo registrados?" y
            // el asistente dijo "no tengo esa información" para una empresa
            // con trabajadores reales, porque solo se consultaba el campo
            // declarado (vacío), nunca el conteo real.
            'numero_empleados' => $empresa->numero_empleados,
            'trabajadores_registrados' => Trabajador::where('empresa_id', $empresa->id)
                ->where('active', true)
                ->count(),
            // Mismo chequeo de permiso que dashboard.blade.php:37-39 - sin esto,
            // un cliente al que se le quitó "Gestión de Contratos" seguiría
            // recibiendo este número aunque el Dashboard ya no se lo muestre.
            'contratos_por_vencer' => $usuario->can('view_any_solicitud::contrato')
                ? SolicitudContrato::where('empresa_id', $empresa->id)
                    ->where('tipo_contrato', 'Contrato a Término Fijo')
                    ->whereNull('decision_no_renovacion_en')
                    ->where('requiere_revision_manual_renovacion', false)
                    ->whereBetween('fecha_fin_contrato', [now()->startOfDay(), now()->addDays(45)->endOfDay()])
                    ->count()
                : null,
        ];
    }

    private function resolverContextoRit(Empresa $empresa): array
    {
        $rit = $empresa->reglamentoInterno;

        if (! $rit) {
            return ['tiene_rit' => false];
        }

        return [
            'tiene_rit' => true,
            'nombre' => $rit->nombre,
            'actualizado_en' => $rit->updated_at->toDateString(),
            'sugerencias_pendientes' => SugerenciaActualizacionRit::where('reglamento_interno_id', $rit->id)
                ->where('estado', 'pendiente')
                ->whereHas('documentoLegal', fn($q) => $q->where('activo', true))
                ->count(),
        ];
    }
}
