<?php

namespace App\Http\Controllers;

use App\Models\DiligenciaDescargo;
use Illuminate\Support\Facades\Storage;

class VerificacionDocumentoController extends Controller
{
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

        // Cadena de autenticación
        $autenticaciones = [];

        if ($diligencia->otp_verificado_en) {
            $autenticaciones[] = [
                'tipo'    => 'Código OTP (One-Time Password)',
                'estado'  => 'verificado',
                'detalle' => 'Verificado el ' . $diligencia->otp_verificado_en
                                ->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->disclaimer_aceptado_en) {
            $autenticaciones[] = [
                'tipo'    => 'Declaración de participación voluntaria',
                'estado'  => 'verificado',
                'detalle' => 'Aceptada el ' . $diligencia->disclaimer_aceptado_en
                                ->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->foto_inicio_en) {
            $autenticaciones[] = [
                'tipo'    => 'Verificación facial — inicio de la diligencia',
                'estado'  => 'verificado',
                'detalle' => 'Capturada el ' . $diligencia->foto_inicio_en
                                ->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        if ($diligencia->foto_fin_en) {
            $autenticaciones[] = [
                'tipo'    => 'Verificación facial — cierre de la diligencia',
                'estado'  => 'verificado',
                'detalle' => 'Capturada el ' . $diligencia->foto_fin_en
                                ->timezone('America/Bogota')->format('d/m/Y \a \l\a\s h:i A'),
            ];
        }

        // URLs de las fotos (a través de la ruta segura por token)
        $fotoInicioUrl = $diligencia->foto_inicio_path
            ? route('verificacion.foto', ['token' => $token, 'tipo' => 'inicio'])
            : null;

        $fotoFinUrl = $diligencia->foto_fin_path
            ? route('verificacion.foto', ['token' => $token, 'tipo' => 'fin'])
            : null;

        return view('verificacion.valido', compact(
            'diligencia', 'proceso', 'trabajador', 'empresa',
            'autenticaciones', 'token', 'fotoInicioUrl', 'fotoFinUrl',
        ));
    }

    /**
     * Sirve la foto de inicio o fin via token (sin requerir autenticación admin).
     */
    public function foto(string $token, string $tipo)
    {
        $diligencia = DiligenciaDescargo::where('verificacion_token', $token)
            ->whereNotNull('verificacion_generada_en')
            ->first();

        abort_if(!$diligencia, 404);

        $campo = $tipo === 'inicio' ? 'foto_inicio_path' : 'foto_fin_path';
        $path  = $diligencia->{$campo};

        abort_if(!$path, 404);

        $absPath = Storage::path($path);
        abort_if(!file_exists($absPath), 404);

        return response()->file($absPath, ['Content-Type' => 'image/jpeg']);
    }
}
