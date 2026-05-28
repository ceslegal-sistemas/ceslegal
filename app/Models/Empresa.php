<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empresa extends Model
{
    protected $fillable = [
        'razon_social',
        'tipo_societario',
        'nit',
        'direccion',
        'telefono',
        'email_contacto',
        'ciudad',
        'departamento',
        'representante_legal',
        'active',
        'dias_laborales',
        'actividad_economica_id',
        'google_oauth_email',
        'google_oauth_tokens',
    ];

    /** Tipos societarios reconocidos en Colombia */
    public const TIPOS_SOCIETARIOS = [
        'S.A.S.'         => 'S.A.S. — Sociedad por Acciones Simplificada',
        'S.A.'           => 'S.A. — Sociedad Anónima',
        'Ltda.'          => 'Ltda. — Sociedad de Responsabilidad Limitada',
        'S.C.A.'         => 'S.C.A. — Sociedad en Comandita por Acciones',
        'S.C.S.'         => 'S.C.S. — Sociedad en Comandita Simple',
        'E.U.'           => 'E.U. — Empresa Unipersonal',
        'E.S.P.'         => 'E.S.P. — Empresa de Servicios Públicos',
        'S.B.I.C.'       => 'S.B.I.C. — Sociedad de Beneficio e Interés Colectivo',
        'Persona Natural' => 'Persona Natural',
    ];

    /**
     * Patrón regex para detectar tipos societarios escritos de distintas formas
     * al final de una cadena (con o sin puntos, mayúsculas/minúsculas).
     * Orden: más largo primero para evitar coincidencias parciales (S.A.S. antes de S.A.).
     */
    private const TIPO_SOCIETARIO_PATRON = '/\s+(?:PERSONA\s+NATURAL|S\.?B\.?I\.?C\.?|S\.?C\.?A\.?|S\.?C\.?S\.?|E\.?S\.?P\.?|S\.?A\.?S\.?|S\.?A\.?|LTDA\.?|E\.?U\.?)\s*$/iu';

    /** Almacena la razón social en mayúsculas y sin tipo societario al final */
    protected function razonSocial(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $val = mb_strtoupper(trim($value));
                // Quitar cualquier tipo societario que el usuario haya escrito al final
                $val = preg_replace(self::TIPO_SOCIETARIO_PATRON, '', $val);
                return trim($val);
            },
        );
    }

    /**
     * Nombre completo: razón social + tipo societario.
     * La capa de deduplicación aquí protege datos legacy que ya tengan
     * el tipo societario dentro de la razón social.
     */
    public function getNombreCompletoAttribute(): string
    {
        if (!$this->tipo_societario) {
            return $this->razon_social ?? '';
        }
        // Si razón social ya termina con el tipo societario, no duplicar
        $razon = mb_strtoupper(trim($this->razon_social ?? ''));
        $tipo  = mb_strtoupper(trim($this->tipo_societario));
        if (str_ends_with($razon, $tipo)) {
            return trim($this->razon_social);
        }
        return trim($this->razon_social . ' ' . $this->tipo_societario);
    }

    protected $casts = [
        'active' => 'boolean',
        'google_oauth_tokens' => 'encrypted',
    ];

    /**
     * Verifica si la empresa trabaja los sábados
     */
    public function trabajaSabados(): bool
    {
        return $this->dias_laborales === 'lunes_sabado';
    }

    /**
     * Obtiene el texto de los días laborales
     */
    public function getDiasLaboralesTextoAttribute(): string
    {
        return match ($this->dias_laborales) {
            'lunes_sabado' => 'Lunes a Sábado',
            default => 'Lunes a Viernes',
        };
    }

    public function actividadEconomica(): BelongsTo
    {
        return $this->belongsTo(ActividadEconomica::class);
    }

    public function actividadesSecundarias(): BelongsToMany
    {
        return $this->belongsToMany(ActividadEconomica::class, 'empresa_actividades_secundarias');
    }

    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }

    public function procesosDisciplinarios(): HasMany
    {
        return $this->hasMany(ProcesoDisciplinario::class);
    }

    public function solicitudesContrato(): HasMany
    {
        return $this->hasMany(SolicitudContrato::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function informesJuridicos(): HasMany
    {
        return $this->hasMany(InformeJuridico::class);
    }

    public function reglamentoInterno(): HasOne
    {
        return $this->hasOne(ReglamentoInterno::class)->where('activo', true)->latest();
    }

    public function suscripcion(): HasOne
    {
        return $this->hasOne(Suscripcion::class)->latest();
    }

    public function tieneSuscripcionActiva(): bool
    {
        return $this->suscripcion?->estaActiva() ?? false;
    }

    public function tieneGmailConectado(): bool
    {
        return !empty($this->google_oauth_tokens);
    }

    public function correos(): HasMany
    {
        return $this->hasMany(\App\Models\CorreoEnviado::class);
    }

    public function puedeUsarTodasLasSanciones(): bool
    {
        $suscripcion = $this->suscripcion;
        if (!$suscripcion || !$suscripcion->incluyeRIT()) {
            return false;
        }
        return $this->reglamentoInterno !== null;
    }
}
