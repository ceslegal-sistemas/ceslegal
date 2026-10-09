<?php

namespace App\Services;

use App\Mail\ImpugnacionConstancia;
use App\Mail\ImpugnacionOtp;
use App\Mail\ImpugnacionRecibidaEmpresa;
use App\Models\Impugnacion;
use App\Models\ProcesoDisciplinario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lógica del portal público donde el TRABAJADOR presenta su impugnación (antes la
 * registraba la empresa en el panel y el trabajador no tenía por dónde hacerlo).
 *
 * Seguridad: el enlace (token de 64 hex) identifica el proceso, pero NO basta para
 * presentar nada - hace falta además verificar un código (OTP) enviado al correo que
 * la empresa tiene registrado del trabajador. El OTP se guarda hasheado, vence en
 * 10 min, permite 3 intentos y, al agotarlos, bloquea 15 min (no para siempre: es un
 * derecho del trabajador y tiene plazo).
 */
class ImpugnacionPortalService
{
    public const OTP_MINUTOS = 10;
    public const OTP_MAX_INTENTOS = 3;
    public const OTP_ESPERA_REENVIO_SEG = 60;
    public const OTP_BLOQUEO_MINUTOS = 15;
    /** Una vez verificado el código, durante cuánto tiempo puede presentar el formulario. */
    public const SESION_VERIFICADA_MINUTOS = 60;

    public const MOTIVOS = [
        'hechos_no_corresponden' => 'Los hechos no corresponden con la realidad',
        'debido_proceso' => 'No se respetó el debido proceso',
        'pruebas_no_tenidas' => 'Tengo pruebas que no fueron tenidas en cuenta',
        'desproporcion' => 'La sanción es desproporcionada frente a la falta',
        'inconformidad_sancion' => 'No estoy de acuerdo con la sanción impuesta',
    ];

    /**
     * Ruta pública: poseer el token es la autorización. Se quita el scope de
     * bufete/empresa (depende del usuario autenticado en el navegador, que aquí
     * puede ser cualquiera o nadie) - mismo criterio que DescargoPublicoController.
     */
    public function resolverPorToken(string $token): ?ProcesoDisciplinario
    {
        if (strlen($token) !== 64 || ! ctype_xdigit($token)) {
            return null;
        }

        return ProcesoDisciplinario::withoutGlobalScope('bufeteOrEmpresa')
            ->where('impugnacion_token', $token)
            ->with([
                'trabajador' => fn ($q) => $q->withoutGlobalScope('bufeteOrEmpresa'),
                'empresa' => fn ($q) => $q->withoutGlobalScope('bufeteOrEmpresa'),
                'impugnacion',
                'sancion',
            ])
            ->first();
    }

    // ─── Verificación por código (OTP) ───────────────────────────────────────

    public function otpBloqueado(ProcesoDisciplinario $p): bool
    {
        return $p->impugnacion_otp_intentos >= self::OTP_MAX_INTENTOS
            && $p->impugnacion_otp_enviado_en
            && $p->impugnacion_otp_enviado_en->gt(now()->subMinutes(self::OTP_BLOQUEO_MINUTOS));
    }

    public function segundosParaReenviar(ProcesoDisciplinario $p): int
    {
        if (! $p->impugnacion_otp_enviado_en) {
            return 0;
        }

        return max(0, self::OTP_ESPERA_REENVIO_SEG - (now()->timestamp - $p->impugnacion_otp_enviado_en->timestamp));
    }

    /** @throws \RuntimeException con 'sin_email' | 'bloqueado' | 'muy_pronto' */
    public function enviarOtp(ProcesoDisciplinario $p): void
    {
        $email = $p->trabajador?->email;
        if (! $email) {
            throw new \RuntimeException('sin_email');
        }
        if ($this->otpBloqueado($p)) {
            throw new \RuntimeException('bloqueado');
        }
        if ($this->segundosParaReenviar($p) > 0) {
            throw new \RuntimeException('muy_pronto');
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $p->forceFill([
            'impugnacion_otp_hash' => $this->hashOtp($p, $codigo),
            'impugnacion_otp_enviado_en' => now(),
            'impugnacion_otp_expira_en' => now()->addMinutes(self::OTP_MINUTOS),
            'impugnacion_otp_intentos' => 0,
            'impugnacion_otp_verificado_en' => null,
        ])->saveQuietly();

        Mail::to($email)->send(new ImpugnacionOtp($codigo, $p->trabajador->nombre_completo, $p->codigo));
    }

    /** @return 'ok'|'incorrecto'|'expirado'|'bloqueado' */
    public function verificarOtp(ProcesoDisciplinario $p, string $codigo): string
    {
        if ($this->otpBloqueado($p)) {
            return 'bloqueado';
        }

        if (! $p->impugnacion_otp_hash || ! $p->impugnacion_otp_expira_en || now()->gt($p->impugnacion_otp_expira_en)) {
            return 'expirado';
        }

        if (! hash_equals($p->impugnacion_otp_hash, $this->hashOtp($p, trim($codigo)))) {
            $p->forceFill(['impugnacion_otp_intentos' => $p->impugnacion_otp_intentos + 1])->saveQuietly();

            return $p->impugnacion_otp_intentos >= self::OTP_MAX_INTENTOS ? 'bloqueado' : 'incorrecto';
        }

        $p->forceFill([
            'impugnacion_otp_verificado_en' => now(),
            'impugnacion_otp_hash' => null,
            'impugnacion_otp_intentos' => 0,
        ])->saveQuietly();

        return 'ok';
    }

    public function identidadVerificada(ProcesoDisciplinario $p): bool
    {
        return $p->impugnacion_otp_verificado_en
            && $p->impugnacion_otp_verificado_en->gt(now()->subMinutes(self::SESION_VERIFICADA_MINUTOS));
    }

    private function hashOtp(ProcesoDisciplinario $p, string $codigo): string
    {
        return hash_hmac('sha256', $codigo, config('app.key') . '|impugnacion|' . $p->id);
    }

    // ─── Presentación ────────────────────────────────────────────────────────

    /**
     * @param  array{motivos?: array<int,string>, detalle?: string}  $datos
     * @param  array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Illuminate\Http\UploadedFile>  $archivos
     *
     * @throws \DomainException con 'plazo' | 'ya_impugnada' | 'identidad' | 'sin_sancion'
     */
    public function presentar(ProcesoDisciplinario $p, array $datos, array $archivos, ?string $ip, ?string $userAgent): Impugnacion
    {
        $impugnacion = DB::transaction(function () use ($p, $datos, $archivos, $ip, $userAgent) {
            // Bloqueo de la fila: dos envíos simultáneos (doble clic, dos pestañas) no
            // pueden crear dos impugnaciones del mismo proceso.
            $proceso = ProcesoDisciplinario::withoutGlobalScope('bufeteOrEmpresa')->lockForUpdate()->findOrFail($p->id);

            if ($proceso->impugnacion()->exists()) {
                throw new \DomainException('ya_impugnada');
            }
            if (! $proceso->puedeImpugnarse()) {
                throw new \DomainException('plazo');
            }
            if (! $this->identidadVerificada($proceso)) {
                throw new \DomainException('identidad');
            }
            $sancion = $proceso->sancion;
            if (! $sancion) {
                throw new \DomainException('sin_sancion');
            }

            $pruebas = [];
            foreach ($archivos as $archivo) {
                $pruebas[] = [
                    'path' => $archivo->store("impugnaciones/{$proceso->id}", 'public'),
                    'name' => $archivo->getClientOriginalName(),
                ];
            }

            $impugnacion = Impugnacion::create([
                'proceso_id' => $proceso->id,
                'sancion_id' => $sancion->id,
                'fecha_impugnacion' => now(),
                'medio_recepcion' => 'portal_web',
                'registrada_por' => 'trabajador',
                'ip_registro' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
                'otp_verificado_en' => $proceso->impugnacion_otp_verificado_en,
                'motivos_impugnacion' => $this->redactarMotivos($datos),
                'pruebas_adicionales' => $pruebas ?: null,
            ]);

            // El observer (estado -> impugnacion_realizada) marca impugnado, notifica en
            // la campana al abogado, al cliente y al super admin y deja el timeline.
            $proceso->impugnado = true;
            $proceso->fecha_impugnacion = $impugnacion->fecha_impugnacion;
            $proceso->estado = 'impugnacion_realizada';
            $proceso->save();

            return $impugnacion;
        });

        $impugnacion->load(['proceso.trabajador', 'proceso.empresa']);
        $this->enviarCorreos($impugnacion);

        return $impugnacion;
    }

    public function redactarMotivos(array $datos): string
    {
        $lineas = [];
        foreach ($datos['motivos'] ?? [] as $clave) {
            if (isset(self::MOTIVOS[$clave])) {
                $lineas[] = '• ' . self::MOTIVOS[$clave];
            }
        }

        $texto = 'El trabajador presentó la impugnación directamente en el portal de LUPE Legal';
        $texto .= $lineas ? ", señalando los siguientes motivos:\n" . implode("\n", $lineas) : '.';

        $detalle = trim((string) ($datos['detalle'] ?? ''));
        if ($detalle !== '') {
            $texto .= "\n\nExplicación del trabajador:\n" . $detalle;
        }

        return $texto;
    }

    private function enviarCorreos(Impugnacion $impugnacion): void
    {
        $proceso = $impugnacion->proceso;

        try {
            if ($proceso->trabajador?->email) {
                Mail::to($proceso->trabajador->email)->send(new ImpugnacionConstancia($impugnacion));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar la constancia de impugnación al trabajador', ['impugnacion_id' => $impugnacion->id, 'error' => $e->getMessage()]);
        }

        $destinatarios = User::where('active', true)
            ->where(function ($q) use ($proceso) {
                $q->where(fn ($c) => $c->where('role', 'cliente')->where('empresa_id', $proceso->empresa_id));
                if ($proceso->abogado_id) {
                    $q->orWhere('id', $proceso->abogado_id);
                }
            })
            ->whereNotNull('email')
            ->get();

        foreach ($destinatarios as $usuario) {
            try {
                $panel = $usuario->role === 'cliente' ? 'empresa' : 'admin';
                $url = \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('index', panel: $panel);
                Mail::to($usuario->email)->send(new ImpugnacionRecibidaEmpresa($impugnacion, $url));
            } catch (\Throwable $e) {
                Log::warning('No se pudo avisar de la impugnación', ['user_id' => $usuario->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
