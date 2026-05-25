<?php

namespace App\Http\Controllers;

use App\Models\DiligenciaDescargo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DescargoPublicoController extends Controller
{
    /**
     * Muestra la página de acceso a descargos
     */
    public function mostrarAcceso(string $token)
    {
        $diligencia = DiligenciaDescargo::where('token_acceso', $token)->first();

        if (!$diligencia) {
            return view('descargos.acceso-invalido', [
                'mensaje' => 'El enlace de acceso no es válido.'
            ]);
        }

        if (!$diligencia->tokenEsValido()) {
            return view('descargos.acceso-invalido', [
                'mensaje' => 'El enlace de acceso ha expirado o no está habilitado.'
            ]);
        }

        if (!$diligencia->puedeAccederHoy()) {
            $fechaPermitida = $diligencia->fecha_acceso_permitida;
            $esTemprano     = $diligencia->esDemaisadoTemprano();
            $hora           = $diligencia->proceso?->hora_descargos_programada;
            $horaFormateada = $hora
                ? \Carbon\Carbon::createFromFormat('H:i:s', $hora, 'America/Bogota')->format('H:i')
                : null;

            if ($esTemprano) {
                $mensaje = "La diligencia está programada para las {$horaFormateada}. Podrá acceder a partir de esa hora.";
            } else {
                $mensaje = "Este enlace solo estará disponible el día {$fechaPermitida->format('d/m/Y')}.";
            }

            return view('descargos.acceso-denegado', [
                'mensaje'        => $mensaje,
                'fechaPermitida' => $fechaPermitida,
                'horaPermitida'  => $horaFormateada,
                'esTemprano'     => $esTemprano,
            ]);
        }

        if (!$diligencia->trabajador_accedio_en) {
            $diligencia->trabajador_accedio_en = now();
            $diligencia->ip_acceso = request()->ip();
            $diligencia->save();

            Log::info('Trabajador accedió a descargos', [
                'diligencia_id' => $diligencia->id,
                'proceso_codigo' => $diligencia->proceso->codigo,
                'trabajador' => $diligencia->proceso->trabajador->nombre_completo,
                'ip' => request()->ip(),
            ]);
        }

        return view('descargos.formulario', [
            'diligencia' => $diligencia
        ]);
    }
}
