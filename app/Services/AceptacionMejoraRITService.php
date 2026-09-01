<?php

namespace App\Services;

use App\Jobs\GenerarRITMejoradoJob;
use App\Models\AuditoriaRIT;
use App\Models\ReglamentoInterno;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de aceptación de las sugerencias de la auditoría de RIT.
 *
 * Reutilizable por:
 *  - La vista unificada del cliente (Mi Reglamento Interno): registra + dispara la mejora.
 *  - El wizard de registro de empresa: solo registra la aceptación (aún no hay empresa);
 *    la mejora se dispara al crear la empresa (CreateEmpresa::afterCreate).
 *
 * Captura de autoridad + datos del responsable como audit trail antes de actualizar el RIT.
 */
class AceptacionMejoraRITService
{
    /**
     * Registra la aceptación: datos del responsable + declaración de autoridad + la
     * verificación fotográfica (equivalencia funcional de firma manuscrita, Ley 527/1999
     * Art. 7-8 y Decreto 2364/2012). Marca la decisión como 'adoptado' (la
     * generación/adopción se dispara aparte).
     *
     * @param array{responsable_nombre:string,responsable_documento:string,responsable_cargo:string,responsable_foto_path?:?string} $datos
     */
    public function registrarAceptacion(AuditoriaRIT $auditoria, array $datos): void
    {
        $auditoria->update([
            'responsable_nombre'     => $datos['responsable_nombre'] ?? null,
            'responsable_documento'  => $datos['responsable_documento'] ?? null,
            'responsable_cargo'      => $datos['responsable_cargo'] ?? null,
            'responsable_foto_path'  => $datos['responsable_foto_path'] ?? null,
            'autoridad_declarada'    => true,
            'autoridad_declarada_at' => now(),
            'decision_mejora'        => 'adoptado',
        ]);
    }

    /**
     * Dispara la actualización del RIT con las sugerencias:
     *  - Si la versión mejorada ya está generada → la adopta de inmediato.
     *  - Si no → marca 'procesando' y despacha la generación (que se auto-adopta al terminar,
     *    porque decision_mejora ya quedó en 'adoptado').
     * Requiere que la auditoría ya tenga empresa (no aplica durante el wizard).
     */
    public function dispararMejora(AuditoriaRIT $auditoria, int $userId): void
    {
        if (! $auditoria->empresa_id) {
            return; // Sin empresa aún (wizard): se dispara al crearla.
        }

        if ($auditoria->estado_mejora === 'completado' && $auditoria->reglamento_mejorado_id) {
            $this->adoptar($auditoria);
            return;
        }

        $auditoria->update(['estado_mejora' => 'procesando', 'mensaje_error' => null]);
        GenerarRITMejoradoJob::dispatch($auditoria->fresh(), $userId);
    }

    /**
     * Adopta el RIT mejorado como versión vigente y desactiva los demás de la empresa.
     */
    public function adoptar(AuditoriaRIT $auditoria): void
    {
        $mejorado = $auditoria->reglamentoMejorado;
        if (! $mejorado || ! $auditoria->empresa_id) {
            return;
        }

        // Bug real corregido: eran 2 UPDATE sueltos sin transacción - si el
        // proceso moría justo entre ambos (timeout, OOM, kill del worker),
        // la empresa quedaba SIN ningún RIT activo hasta la próxima
        // adopción, rompiendo cualquier consulta que asuma que siempre hay
        // uno (ej. SolicitudContratoIAService::completarDetallesCargo(), que
        // no tiene respaldo para ese caso).
        DB::transaction(function () use ($auditoria, $mejorado) {
            ReglamentoInterno::where('empresa_id', $auditoria->empresa_id)->update(['activo' => false]);
            $mejorado->update(['activo' => true]);
            $auditoria->update(['decision_mejora' => 'adoptado']);
        });
    }

    /**
     * El responsable decide mantener su RIT actual: la mejora queda archivada.
     */
    public function mantenerActual(AuditoriaRIT $auditoria): void
    {
        $auditoria->update(['decision_mejora' => 'rechazado']);
    }
}
