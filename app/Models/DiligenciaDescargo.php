<?php

namespace App\Models;

use App\Mail\OtpDescargos;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;

class DiligenciaDescargo extends Model
{
    protected $table = 'diligencias_descargos';

    protected $fillable = [
        'proceso_id',
        'fecha_diligencia',
        'lugar_diligencia',
        'lugar_especifico',
        'link_reunion',
        'trabajador_asistio',
        'motivo_inasistencia',
        'acompanante_nombre',
        'acompanante_cargo',
        'preguntas_formuladas',
        'respuestas',
        'pruebas_aportadas',
        'descripcion_pruebas',
        'observaciones',
        'acta_generada',
        'ruta_acta',
        'archivos_evidencia',
        'token_acceso',
        'token_expira_en',
        'acceso_habilitado',
        'fecha_acceso_permitida',
        'trabajador_accedio_en',
        'primer_acceso_en',
        'preguntas_completadas_en',
        'tiempo_limite',
        'tiempo_expirado',
        'ip_acceso',
        'otp_codigo',
        'otp_expira_en',
        'otp_verificado_en',
        'otp_intentos',
        'otp_canal',
        'otp_enviado_a',
        'disclaimer_aceptado_en',
        'disclaimer_ip',
        'foto_inicio_path',
        'foto_inicio_en',
        'foto_fin_path',
        'foto_fin_en',
        'evidencia_metadata',
    ];

    protected $casts = [
        'fecha_diligencia' => 'datetime',
        'trabajador_asistio' => 'boolean',
        'preguntas_formuladas' => 'array',
        'respuestas' => 'array',
        'archivos_evidencia' => 'array',
        'pruebas_aportadas' => 'boolean',
        'acta_generada' => 'boolean',
        'token_expira_en' => 'datetime',
        'acceso_habilitado' => 'boolean',
        'fecha_acceso_permitida' => 'date',
        'trabajador_accedio_en' => 'datetime',
        'primer_acceso_en' => 'datetime',
        'preguntas_completadas_en' => 'datetime',
        'tiempo_limite' => 'datetime',
        'tiempo_expirado' => 'boolean',
        'otp_expira_en' => 'datetime',
        'otp_verificado_en' => 'datetime',
        'otp_intentos' => 'integer',
        'disclaimer_aceptado_en' => 'datetime',
        'foto_inicio_en' => 'datetime',
        'foto_fin_en' => 'datetime',
        'evidencia_metadata' => 'array',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function preguntas()
    {
        return $this->hasMany(PreguntaDescargo::class, 'diligencia_descargo_id')->ordenadas();
    }

    public function trazabilidadIA()
    {
        return $this->hasMany(TrazabilidadIADescargo::class, 'diligencia_descargo_id');
    }

    public function generarTokenAcceso(): string
    {
        $this->token_acceso = bin2hex(random_bytes(32));

        // Expira al final del día permitido. Si no hay fecha, 30 días de margen.
        if ($this->fecha_acceso_permitida) {
            $this->token_expira_en = Carbon::parse($this->fecha_acceso_permitida)->endOfDay();
        } else {
            $this->token_expira_en = now()->addDays(30);
        }

        $this->save();

        return $this->token_acceso;
    }

    public function tokenEsValido(): bool
    {
        if (!$this->token_acceso || !$this->token_expira_en) {
            return false;
        }

        if (now()->greaterThan($this->token_expira_en)) {
            return false;
        }

        if (!$this->acceso_habilitado) {
            return false;
        }

        return true;
    }

    public function puedeAccederHoy(): bool
    {
        if (!$this->fecha_acceso_permitida) {
            return false;
        }

        return now()->toDateString() === $this->fecha_acceso_permitida->toDateString();
    }

    // Métodos de acceso por día
    public function iniciarTimer()
    {
        if (!$this->primer_acceso_en) {
            $this->update([
                'primer_acceso_en' => Carbon::now('America/Bogota'),
                'tiempo_limite'    => Carbon::parse($this->fecha_acceso_permitida)->endOfDay(),
                'tiempo_expirado'  => false,
            ]);
        }
    }

    public function tiempoHaExpirado(): bool
    {
        if (!$this->fecha_acceso_permitida) {
            return false;
        }

        // Expirado si hoy (Bogotá) es un día posterior a la fecha permitida
        return Carbon::now('America/Bogota')->startOfDay()->gt(
            Carbon::parse($this->fecha_acceso_permitida)->startOfDay()
        );
    }

    public function marcarTiempoExpirado()
    {
        $this->update(['tiempo_expirado' => true]);
    }

    // ─── OTP / Autenticación ───────────────────────────────────────────────

    public function emailTrabajador(): ?string
    {
        return $this->proceso?->trabajador?->email ?: null;
    }

    public function enviarOtp(): bool
    {
        $email = $this->emailTrabajador();
        if (!$email) {
            return false;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'otp_codigo'    => $codigo,
            'otp_expira_en' => now()->addMinutes(10),
            'otp_intentos'  => 0,
            'otp_canal'     => 'email',
            'otp_enviado_a' => $this->enmascararEmail($email),
        ]);

        try {
            Mail::to($email)->send(new OtpDescargos(
                $codigo,
                $this->proceso->trabajador->nombre_completo,
                $this->proceso->codigo,
            ));
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al enviar OTP descargos', [
                'diligencia_id' => $this->id,
                'error'         => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function verificarOtp(string $codigo): bool
    {
        if ($this->otpBloqueado() || !$this->otpEsValido()) {
            return false;
        }

        if ($this->otp_codigo !== $codigo) {
            $this->increment('otp_intentos');
            return false;
        }

        $this->update(['otp_verificado_en' => now()]);
        return true;
    }

    public function otpBloqueado(): bool
    {
        return $this->otp_intentos >= 3;
    }

    public function otpEsValido(): bool
    {
        if (!$this->otp_expira_en || !$this->otp_codigo) {
            return false;
        }
        return now()->lessThan($this->otp_expira_en);
    }

    public function puedeReenviar(): bool
    {
        // Usuario bloqueado no puede reenviar (evita resetear intentos)
        if ($this->otpBloqueado()) {
            return false;
        }

        if (!$this->otp_expira_en) {
            return true;
        }

        // Cooldown de 60 segundos: el código se envió hace más de 60s
        // IMPORTANTE: usar copy() para no mutar el objeto Carbon del atributo
        $enviadoEn  = $this->otp_expira_en->copy()->subMinutes(10);
        $enviadoHace = now()->diffInSeconds($enviadoEn, false);
        return $enviadoHace >= 60;
    }

    private function enmascararEmail(string $email): string
    {
        [$usuario, $dominio] = explode('@', $email, 2);
        $visible = substr($usuario, 0, min(3, strlen($usuario)));
        return $visible . '***@' . $dominio;
    }
}
