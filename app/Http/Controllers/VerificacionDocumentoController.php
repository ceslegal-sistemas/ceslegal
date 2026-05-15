<?php

namespace App\Http\Controllers;

use App\Models\DiligenciaDescargo;
use Illuminate\Http\Request;

class VerificacionDocumentoController extends Controller
{
    /**
     * Página pública de verificación del documento.
     * Cualquier persona puede acceder con el token que viene en el QR del acta.
     */
    public function verificar(string $token)
    {
        $diligencia = DiligenciaDescargo::where('verificacion_token', $token)
            ->with(['proceso.trabajador', 'proceso.empresa'])
            ->first();

        if (!$diligencia || !$diligencia->verificacion_generada_en) {
            return view('verificacion.invalido');
        }

        $proceso    = $diligencia->proceso;
        $trabajador = $proceso->trabajador;
        $empresa    = $proceso->empresa;

        // Construir cadena de verificación para mostrar
        $autenticaciones = [];

        if ($diligencia->otp_verificado_en) {
            $autenticaciones[] = [
                'tipo'      => 'Código OTP',
                'icono'     => '🔐',
                'estado'    => 'verificado',
                'detalle'   => 'Verificado el ' . $diligencia->otp_verificado_en
                                    ->timezone('America/Bogota')
                                    ->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->disclaimer_aceptado_en) {
            $autenticaciones[] = [
                'tipo'    => 'Declaración voluntaria',
                'icono'   => '📋',
                'estado'  => 'verificado',
                'detalle' => 'Aceptada el ' . $diligencia->disclaimer_aceptado_en
                                    ->timezone('America/Bogota')
                                    ->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->foto_inicio_en) {
            $autenticaciones[] = [
                'tipo'    => 'Verificación facial — inicio',
                'icono'   => '📸',
                'estado'  => 'verificado',
                'detalle' => 'Capturada el ' . $diligencia->foto_inicio_en
                                    ->timezone('America/Bogota')
                                    ->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->foto_fin_en) {
            $autenticaciones[] = [
                'tipo'    => 'Verificación facial — cierre',
                'icono'   => '📸',
                'estado'  => 'verificado',
                'detalle' => 'Capturada el ' . $diligencia->foto_fin_en
                                    ->timezone('America/Bogota')
                                    ->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        return view('verificacion.valido', compact(
            'diligencia',
            'proceso',
            'trabajador',
            'empresa',
            'autenticaciones',
            'token',
        ));
    }
}
