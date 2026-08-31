<?php

namespace App\Filament\Concerns;

use App\Services\VerificacionFacialService;

/**
 * Verificación en vivo (detección de accesorios que oculten el rostro: gafas oscuras,
 * mascarilla, gorra, etc.) para cualquier flujo que use el componente Alpine/Livewire
 * `filament.components.webcam-autorizador`. El componente llama
 * `$wire.verificarAccesoriosAutorizador(fotoBase64)` y lee `$wire.alertaAccesoriosAutorizador`
 * directamente sobre CUALQUIER componente Livewire que use este trait - sin importar si es
 * una Table Action, una Page Action o un formulario de Wizard.
 */
trait HasVerificacionFotografica
{
    public string $alertaAccesoriosAutorizador = '';

    /**
     * Momento e IP en que el autorizador aceptó la autorización de tratamiento
     * de datos personales (Ley 1581 de 2012) antes de la captura fotográfica.
     * El bloqueo en el navegador no basta como prueba: se registra en el
     * servidor, con la hora del servidor (no la del cliente, manipulable).
     */
    public ?string $disclaimerDatosAceptadoEn = null;
    public ?string $disclaimerDatosIp = null;

    public function aceptarDisclaimerDatos(): void
    {
        // Idempotente: si ya se aceptó, se conserva la primera marca de tiempo.
        if ($this->disclaimerDatosAceptadoEn !== null) {
            return;
        }

        $this->disclaimerDatosAceptadoEn = now()->toIso8601String();
        $this->disclaimerDatosIp         = request()->ip();
    }

    public function verificarAccesoriosAutorizador(string $fotoBase64): void
    {
        try {
            $resultado = app(VerificacionFacialService::class)->detectarAccesorios($fotoBase64);
            $this->alertaAccesoriosAutorizador = $resultado['ok'] ? '' : ($resultado['motivo'] ?? '');
        } catch (\Throwable $e) {
            // fail-open: si la verificación falla, permitir continuar
            $this->alertaAccesoriosAutorizador = '';
        }
    }

    /**
     * Decodifica una foto base64 (data URL) y la guarda en el disco público.
     * Devuelve la ruta relativa o null si el valor venía vacío/corrupto.
     */
    protected function guardarFotoVerificacion(?string $fotoBase64, string $directorio): ?string
    {
        if (empty($fotoBase64)) {
            return null;
        }

        $base64    = preg_replace('/^data:image\/\w+;base64,/', '', $fotoBase64);
        $imageData = base64_decode($base64);
        if (! $imageData) {
            return null;
        }

        $filename = uniqid() . '.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($directorio);
        \Illuminate\Support\Facades\Storage::disk('public')->put("{$directorio}/{$filename}", $imageData);

        return "{$directorio}/{$filename}";
    }
}
