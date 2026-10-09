<?php

namespace App\Livewire;

use App\Models\ProcesoDisciplinario;
use App\Services\ImpugnacionPortalService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Portal público (sin sesión) donde el trabajador presenta su impugnación:
 * 1) verifica su identidad con un código enviado a su correo, 2) explica sus motivos
 * y adjunta pruebas, 3) recibe la constancia. Ver ImpugnacionPortalService.
 *
 * El token del enlace se guarda como propiedad pública (el trabajador ya lo tiene
 * en la URL); el proceso se vuelve a leer en cada petición - nada del expediente
 * viaja en el estado de Livewire.
 */
class FormularioImpugnacion extends Component
{
    use WithFileUploads;

    public string $token = '';

    /** otp | formulario | confirmado | ya_impugnada | vencida | no_disponible */
    public string $etapa = 'otp';

    public string $otpCodigo = '';
    public string $otpError = '';
    public bool $otpEnviado = false;

    /** @var array<int, string> */
    public array $motivos = [];
    public string $detalle = '';
    public array $archivos = [];
    public bool $declaracion = false;
    public string $errorEnvio = '';
    public ?string $radicado = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->etapa = $this->calcularEtapa();
    }

    private function servicio(): ImpugnacionPortalService
    {
        return app(ImpugnacionPortalService::class);
    }

    private function proceso(): ?ProcesoDisciplinario
    {
        return $this->servicio()->resolverPorToken($this->token);
    }

    private function calcularEtapa(): string
    {
        $p = $this->proceso();
        if (! $p) {
            return 'no_disponible';
        }
        if ($p->impugnacion) {
            return 'ya_impugnada';
        }
        if (! $p->puedeImpugnarse()) {
            return 'vencida';
        }

        return $this->servicio()->identidadVerificada($p) ? 'formulario' : 'otp';
    }

    // ─── Paso 1: verificación ────────────────────────────────────────────────

    public function enviarOtp(): void
    {
        $this->otpError = '';
        $p = $this->proceso();
        if (! $p || ! $p->puedeImpugnarse()) {
            $this->etapa = $this->calcularEtapa();

            return;
        }

        try {
            $this->servicio()->enviarOtp($p);
            $this->otpEnviado = true;
            $this->otpCodigo = '';
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'muy_pronto') {
                $this->otpEnviado = true; // ya hay un código vigente: se sigue con ese
            } else {
                $this->otpError = in_array($e->getMessage(), ['sin_email', 'bloqueado'], true) ? $e->getMessage() : 'fallo_envio';
            }
        } catch (\Throwable $e) {
            report($e);
            $this->otpError = 'fallo_envio';
        }
    }

    public function verificarOtp(): void
    {
        $this->otpError = '';
        $p = $this->proceso();
        if (! $p) {
            $this->etapa = 'no_disponible';

            return;
        }

        $resultado = $this->servicio()->verificarOtp($p, $this->otpCodigo);

        if ($resultado === 'ok') {
            $this->otpCodigo = '';
            $this->etapa = $this->calcularEtapa();

            return;
        }

        $this->otpError = $resultado;
    }

    // ─── Paso 2: presentar ───────────────────────────────────────────────────

    public function alternarMotivo(string $clave): void
    {
        if (! array_key_exists($clave, ImpugnacionPortalService::MOTIVOS)) {
            return;
        }

        $this->motivos = in_array($clave, $this->motivos, true)
            ? array_values(array_diff($this->motivos, [$clave]))
            : array_merge($this->motivos, [$clave]);
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    public function presentar(): void
    {
        $this->errorEnvio = '';

        $this->validate([
            'motivos' => 'array',
            'motivos.*' => 'in:' . implode(',', array_keys(ImpugnacionPortalService::MOTIVOS)),
            'detalle' => 'nullable|string|max:5000',
            'archivos' => 'array|max:5',
            'archivos.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,mp4,mov,mp3,wav,m4a',
            'declaracion' => 'accepted',
        ], [
            'archivos.max' => 'Puede adjuntar hasta 5 archivos.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
            'archivos.*.mimes' => 'Formato no permitido. Use PDF, imagen, Word, Excel, audio o video.',
            'declaracion.accepted' => 'Debe confirmar la declaración para presentar la impugnación.',
        ]);

        if (empty($this->motivos) && trim($this->detalle) === '' && empty($this->archivos)) {
            $this->errorEnvio = 'Indique al menos un motivo, escriba una explicación o adjunte una prueba.';

            return;
        }

        $p = $this->proceso();
        if (! $p) {
            $this->etapa = 'no_disponible';

            return;
        }

        try {
            $impugnacion = $this->servicio()->presentar(
                $p,
                ['motivos' => $this->motivos, 'detalle' => $this->detalle],
                $this->archivos,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (\DomainException $e) {
            match ($e->getMessage()) {
                'ya_impugnada' => $this->etapa = 'ya_impugnada',
                'plazo' => $this->etapa = 'vencida',
                'identidad' => $this->etapa = 'otp',
                default => $this->errorEnvio = 'No pudimos registrar su impugnación. Comuníquese con su empresa.',
            };

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->errorEnvio = 'Ocurrió un error al enviar su impugnación. Intente de nuevo en unos minutos.';

            return;
        }

        $this->radicado = $impugnacion->radicado();
        $this->archivos = [];
        $this->etapa = 'confirmado';
    }

    public function render()
    {
        $p = $this->proceso();
        $limite = $p?->limiteImpugnacion();

        return view('livewire.formulario-impugnacion', [
            'proceso' => $p,
            'trabajador' => $p?->trabajador,
            'empresa' => $p?->empresa,
            'limite' => $limite,
            'impugnacion' => $p?->impugnacion,
            'motivosDisponibles' => ImpugnacionPortalService::MOTIVOS,
            'emailEnmascarado' => $this->enmascarar($p?->trabajador?->email),
            'segundosReenvio' => $p ? $this->servicio()->segundosParaReenviar($p) : 0,
        ]);
    }

    private function enmascarar(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$usuario, $dominio] = explode('@', $email, 2);

        return substr($usuario, 0, min(3, strlen($usuario))) . '***@' . $dominio;
    }
}
