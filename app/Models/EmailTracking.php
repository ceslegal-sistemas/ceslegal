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
        'tipo_documento',
        'proceso_id',
        'trabajador_id',
        'email_destinatario',
        'canales',
        'whatsapp_message_id',
        'whatsapp_estado',
        'whatsapp_entregado_en',
        'whatsapp_leido_en',
        'enviado_en',
        'abierto_en',
        'veces_abierto',
        'ip_apertura',
        'user_agent',
    ];

    protected $casts = [
        'canales' => 'array',
        'enviado_en' => 'datetime',
        'abierto_en' => 'datetime',
        'veces_abierto' => 'integer',
        'whatsapp_entregado_en' => 'datetime',
        'whatsapp_leido_en' => 'datetime',
    ];

    /**
     * Canales por los que se envió este registro. Null = registros
     * históricos previos al selector de canal (2026-10-27) - siempre fueron
     * por correo.
     */
    public function getCanalesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: ['email'];
        }

        return ['email'];
    }

    public function incluyeCanal(string $canal): bool
    {
        return in_array($canal, $this->canales, true);
    }

    /**
     * Etiqueta legible de los canales usados, para mostrar junto al acuse.
     */
    public function getCanalesLabelAttribute(): string
    {
        $canales = $this->canales;
        $tieneEmail = in_array('email', $canales, true);
        $tieneWhatsapp = in_array('whatsapp', $canales, true);

        if ($tieneEmail && $tieneWhatsapp) {
            return 'Correo y WhatsApp';
        }

        if ($tieneWhatsapp) {
            return 'WhatsApp';
        }

        return 'Correo';
    }

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
     * Verificar si el correo fue leído por el trabajador (2+ aperturas)
     * La primera apertura es la precarga automática del servidor de correo
     */
    public function fueAbierto(): bool
    {
        return $this->veces_abierto >= 2;
    }

    /**
     * Verificar si el correo fue entregado (llegó al servidor de correo)
     */
    public function fueEntregado(): bool
    {
        return $this->veces_abierto >= 1;
    }

    /**
     * Obtener el estado de lectura del correo
     * 0 = Pendiente (no ha llegado)
     * 1 = Entregado (llegó al correo, precarga del servidor)
     * 2+ = Leído (el trabajador lo abrió)
     */
    public function getEstadoLectura(): string
    {
        // El tracking de apertura (pixel) solo existe cuando el correo
        // realmente se envió - un envío solo por WhatsApp no tiene ese
        // mecanismo, usa en cambio el acuse de lectura real de WhatsApp
        // (webhook de Meta, ver ProcesarWebhookWhatsAppJob::procesarStatus()
        // y whatsapp_estado, pedido del usuario 2026-10-27).
        if (!$this->incluyeCanal('email')) {
            return match ($this->whatsapp_estado) {
                'leido' => 'Leído por WhatsApp',
                'entregado' => 'Entregado por WhatsApp',
                'fallido' => 'Falló el envío por WhatsApp',
                default => 'Enviado por WhatsApp',
            };
        }

        if ($this->veces_abierto === 0) {
            return 'Pendiente';
        }

        if ($this->veces_abierto === 1) {
            return 'Correo Entregado';
        }

        // veces_abierto >= 2: restar 1 para no contar la precarga
        $vecesLeido = $this->veces_abierto - 1;
        return "Leído ({$vecesLeido})";
    }

    /**
     * Obtener el color del estado para badges
     */
    public function getColorEstado(): string
    {
        if (!$this->incluyeCanal('email')) {
            return match ($this->whatsapp_estado) {
                'leido' => 'success',
                'fallido' => 'danger',
                'entregado' => 'warning',
                default => 'info',
            };
        }

        if ($this->veces_abierto === 0) {
            return 'gray';
        }

        if ($this->veces_abierto === 1) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * Línea de acuse de WhatsApp lista para un tooltip (pedido del usuario,
     * 2026-10-27) - null si este registro no se envió por WhatsApp en
     * absoluto, para que el llamador decida si agregarla o no.
     */
    public function getWhatsappAcuseLineaAttribute(): ?string
    {
        if (!$this->incluyeCanal('whatsapp')) {
            return null;
        }

        $texto = match ($this->whatsapp_estado) {
            'leido' => 'Leído' . ($this->whatsapp_leido_en ? ' el ' . $this->whatsapp_leido_en->format('d/m/Y H:i') : ''),
            'entregado' => 'Entregado' . ($this->whatsapp_entregado_en ? ' el ' . $this->whatsapp_entregado_en->format('d/m/Y H:i') : ''),
            'fallido' => 'No se pudo entregar',
            'enviado' => 'Enviado, esperando confirmación de entrega',
            default => 'Enviado',
        };

        return "WhatsApp: {$texto}";
    }

    /**
     * Registrar apertura del correo por el trabajador
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

        // Registrar la fecha de la primera apertura
        if (!$this->abierto_en) {
            $this->abierto_en = Carbon::now('America/Bogota');
        }

        $this->save();
    }

    /**
     * Scope para filtrar por tipo de correo
     */
    public function scopeTipoDocumento($query, string $tipo)
    {
        return $query->where('tipo_documento', $tipo);
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
    public function getTipoDocumentoLegibleAttribute(): string
    {
        return match ($this->tipo_documento) {
            'citacion', 'citacion_descargos' => 'Citación a Descargos',
            'estado_descargos'                => 'Acta de Descargos',
            'sancion'                         => 'Notificación de Sanción',
            'recordatorio_descargos'          => 'Recordatorio de Descargos',
            'descargos_no_realizados_empleador' => 'Descargos No Realizados',
            'resolucion_impugnacion'          => 'Resolución de Impugnación',
            default                           => ucwords(str_replace('_', ' ', $this->tipo_documento ?? '')),
        };
    }
}
