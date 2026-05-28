<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CorreoEnviado extends Model
{
    protected $table = 'correos_enviados';

    protected $fillable = [
        'token',
        'enviado_por',
        'trabajador_id',
        'proceso_id',
        'empresa_id',
        'destinatario_nombre',
        'email_destinatario',
        'email_cc',
        'asunto',
        'cuerpo',
        'adjuntos',
        'prioridad',
        'enviado_en',
        'abierto_en',
        'veces_abierto',
        'ip_apertura',
        'user_agent',
        'estado',
    ];

    protected $casts = [
        'email_cc'      => 'array',
        'adjuntos'      => 'array',
        'enviado_en'    => 'datetime',
        'abierto_en'    => 'datetime',
        'veces_abierto' => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function enviador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
    }

    // ── Helpers de token ─────────────────────────────────────────────────────

    public static function generarToken(): string
    {
        return Str::random(64);
    }

    // ── Lógica de tracking ────────────────────────────────────────────────────

    /**
     * Registra un ping del pixel de tracking.
     * veces_abierto == 1: precarga del servidor de correo → 'entregado'
     * veces_abierto >= 2: apertura real del usuario → 'leido'
     */
    public function registrarApertura(?string $ip = null, ?string $userAgent = null): void
    {
        $this->veces_abierto += 1;

        if ($ip) {
            $this->ip_apertura = $ip;
        }

        if ($userAgent) {
            $this->user_agent = $userAgent;
        }

        if ($this->veces_abierto === 1) {
            $this->estado = 'entregado';
        } elseif ($this->veces_abierto === 2) {
            $this->estado     = 'leido';
            $this->abierto_en = Carbon::now('America/Bogota');
        }

        $this->save();
    }

    public function fueLeido(): bool
    {
        return $this->veces_abierto >= 2;
    }

    public function fueEntregado(): bool
    {
        return $this->veces_abierto >= 1;
    }

    /**
     * Veces que el usuario realmente abrió el correo (descontando precarga del servidor).
     */
    public function vecesLeidoReal(): int
    {
        return max(0, $this->veces_abierto - 1);
    }

    public function getColorEstado(): string
    {
        return match ($this->estado) {
            'leido'     => 'success',
            'entregado' => 'warning',
            default     => 'gray',
        };
    }

    public function getLabelEstado(): string
    {
        return match ($this->estado) {
            'leido'     => 'Leído (' . $this->vecesLeidoReal() . ')',
            'entregado' => 'Entregado',
            default     => 'Pendiente',
        };
    }

    /**
     * Tiempo legible entre envío y primera apertura real.
     */
    public function getTiempoHastaAperturaAttribute(): ?string
    {
        if (!$this->abierto_en || !$this->enviado_en) {
            return null;
        }

        return $this->enviado_en->diffForHumans($this->abierto_en, true);
    }

    /**
     * Parsea el user_agent para extraer cliente, OS y tipo de dispositivo.
     *
     * @return array{cliente: string, os: string, dispositivo: string}
     */
    public function parsearUserAgent(): array
    {
        $ua = $this->user_agent ?? '';

        // Cliente de correo
        $cliente = 'Desconocido';
        if (str_contains($ua, 'Googlebot') || str_contains($ua, 'Google Image Proxy') || str_contains($ua, 'GoogleImageProxy')) {
            $cliente = 'Gmail';
        } elseif (str_contains($ua, 'Outlook')) {
            $cliente = 'Outlook';
        } elseif (str_contains($ua, 'Thunderbird')) {
            $cliente = 'Thunderbird';
        } elseif (str_contains($ua, 'Apple-Mail') || str_contains($ua, 'iPhone Mail')) {
            $cliente = 'Apple Mail';
        } elseif (str_contains($ua, 'YahooMailProxy')) {
            $cliente = 'Yahoo Mail';
        } elseif (str_contains($ua, 'curl') || str_contains($ua, 'Wget')) {
            $cliente = 'Bot / Herramienta';
        } elseif (str_contains($ua, 'Chrome')) {
            $cliente = 'Chrome / Webmail';
        } elseif (str_contains($ua, 'Firefox')) {
            $cliente = 'Firefox / Webmail';
        } elseif (str_contains($ua, 'Safari')) {
            $cliente = 'Safari / Webmail';
        }

        // Sistema operativo
        $os = 'Desconocido';
        if (str_contains($ua, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        }

        // Tipo de dispositivo
        $dispositivo = 'Escritorio';
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android')) {
            $dispositivo = 'Móvil';
        } elseif (str_contains($ua, 'iPad')) {
            $dispositivo = 'Tablet';
        }

        return compact('cliente', 'os', 'dispositivo');
    }
}
