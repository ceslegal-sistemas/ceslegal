<?php

namespace App\Models;

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
     * Nombre completo de la empresa: razón social + tipo societario.
     * Úsese en prompts de IA y encabezados de documentos.
     */
    public function getNombreCompletoAttribute(): string
    {
        $tipo = $this->tipo_societario ? " {$this->tipo_societario}" : '';
        return trim($this->razon_social . $tipo);
    }

    protected $casts = [
        'active' => 'boolean',
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

    public function puedeUsarTodasLasSanciones(): bool
    {
        $suscripcion = $this->suscripcion;
        if (!$suscripcion || !$suscripcion->incluyeRIT()) {
            return false;
        }
        return $this->reglamentoInterno !== null;
    }
}
