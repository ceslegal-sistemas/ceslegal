<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmailTracking extends Model
{
    protected $fillable = [
        'token',
        'tipo_correo',
        'proceso_id',
        'trabajador_id',
        'email_destinatario',
        'enviado_en',
        'abierto_en',
        'veces_abierto',
        'ip_apertura',
        'user_agent',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
        'abierto_en' => 'datetime',
        'veces_abierto' => 'integer',
    ];

    /**
     * Generar un token único para el tracking
     */
    public static function generarToken(): string
    {
        return Str::random(64);
    }

    /**
     * Relación con el proceso disciplinario
     */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    /**
     * Relación con el trabajador
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    /**
     * Verificar si el correo fue realmente abierto por el usuario
     * La primera apertura se ignora (es la precarga automática del cliente de correo)
     * Solo se considera leído a partir de la segunda apertura
     */
    public function fueAbierto(): bool
    {
        return $this->veces_abierto >= 2;
    }

    /**
     * Registrar apertura del correo
     * La fecha de apertura se registra en la segunda vez (ignorando la precarga)
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

        // Registrar la fecha en la segunda apertura (la real, no la precarga)
        if ($this->veces_abierto == 2) {
            $this->abierto_en = Carbon::now('America/Bogota');
        }

        $this->save();
    }

    /**
     * Scope para filtrar por tipo de correo
     */
    public function scopeTipoCorreo($query, string $tipo)
    {
        return $query->where('tipo_correo', $tipo);
    }

    /**
     * Scope para correos abiertos
     */
    public function scopeAbiertos($query)
    {
        return $query->whereNotNull('abierto_en');
    }

    /**
     * Scope para correos no abiertos
     */
    public function scopeNoAbiertos($query)
    {
        return $query->whereNull('abierto_en');
    }

    /**
     * Obtener tiempo transcurrido desde el envío hasta la apertura
     */
    public function getTiempoHastaAperturaAttribute(): ?string
    {
        if (!$this->abierto_en || !$this->enviado_en) {
            return null;
        }

        return $this->enviado_en->diffForHumans($this->abierto_en, true);
    }

    /**
     * Obtener el nombre legible del tipo de correo
     */
    public function getTipoCorreoLegibleAttribute(): string
    {
        return match ($this->tipo_correo) {
            'citacion' => 'Citación a Descargos',
            'sancion' => 'Notificación de Sanción',
            default => ucfirst($this->tipo_correo),
        };
    }
}
