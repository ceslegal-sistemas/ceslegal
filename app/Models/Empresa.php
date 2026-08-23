<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empresa extends Model
{
    use HasFactory, ScopedToBufeteOrEmpresa;

    protected $fillable = [
        'bufete_id',
        'razon_social',
        'tipo_societario',
        'nit',
        'direccion',
        'telefono',
        'email_contacto',
        'ciudad',
        'departamento',
        'representante_legal',
        'representante_legal_cedula',
        'active',
        'dias_laborales',
        'dias_habiles',
        'actividad_economica_id',
        'numero_empleados',
        'google_oauth_email',
        'google_oauth_tokens',
    ];

    /** Tipos societarios reconocidos en Colombia */
    public const TIPOS_SOCIETARIOS = [
        'S.A.S.'         => 'S.A.S. - Sociedad por Acciones Simplificada',
        'S.A.'           => 'S.A. - Sociedad Anónima',
        'Ltda.'          => 'Ltda. - Sociedad de Responsabilidad Limitada',
        'S.C.A.'         => 'S.C.A. - Sociedad en Comandita por Acciones',
        'S.C.S.'         => 'S.C.S. - Sociedad en Comandita Simple',
        'E.U.'           => 'E.U. - Empresa Unipersonal',
        'E.S.P.'         => 'E.S.P. - Empresa de Servicios Públicos',
        // No es una forma jurídica aparte (una Sociedad BIC sigue siendo, de
        // fondo, una S.A.S./S.A./etc.) sino una calidad adicional que la
        // empresa adopta voluntariamente al comprometerse por estatutos a
        // generar impacto social/ambiental positivo, no solo lucro (Ley 1901
        // de 2018). Se deja como opción aparte en este selector porque así
        // suele aparecer escrita en la razón social ("... S.A.S. BIC").
        'S.B.I.C.'       => 'S.B.I.C. - Sociedad de Beneficio e Interés Colectivo (empresa con compromiso social/ambiental formal, Ley 1901 de 2018)',
        'ESAL'           => 'ESAL - Entidad Sin Ánimo de Lucro (fundación, corporación o asociación)',
        'Persona Natural' => 'Persona Natural',
    ];

    /**
     * Patrón regex para detectar tipos societarios escritos de distintas formas
     * al final de una cadena (con o sin puntos, mayúsculas/minúsculas, CON o
     * SIN espacio entre cada letra - "S.A.S.", "SAS" y "S. A. S." son la
     * misma sigla escrita distinto, y las tres deben detectarse).
     * Orden: más largo primero para evitar coincidencias parciales (S.A.S. antes de S.A.).
     */
    private const TIPO_SOCIETARIO_PATRON = '/\s+(?:PERSONA\s+NATURAL|ESAL|S\.?\s*B\.?\s*I\.?\s*C\.?|S\.?\s*C\.?\s*A\.?|S\.?\s*C\.?\s*S\.?|E\.?\s*S\.?\s*P\.?|S\.?\s*A\.?\s*S\.?|S\.?\s*A\.?|LTDA\.?|E\.?\s*U\.?)\s*$/iu';

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
        $razon = trim($this->razon_social ?? '');
        if (!$this->tipo_societario) {
            return $razon;
        }
        $tipo = trim($this->tipo_societario);

        // Compara solo letras (sin espacios ni puntos) para detectar el
        // mismo sufijo societario escrito de formas distintas ("S.A.S.",
        // "S. A. S.", "SAS"...) que el usuario ya haya dejado en la razón
        // social - la comparación literal fallaba porque "S. A. S." (con
        // espacios) nunca es igual a "S.A.S." aunque digan lo mismo. Esto es
        // la red de seguridad para datos legacy; el mutator de razonSocial()
        // ya evita que se guarde así de nuevo.
        $razonNormalizada = preg_replace('/[^A-Z]/u', '', mb_strtoupper($razon));
        $tipoNormalizado  = preg_replace('/[^A-Z]/u', '', mb_strtoupper($tipo));

        if ($tipoNormalizado !== '' && str_ends_with($razonNormalizada, $tipoNormalizado)) {
            return $razon;
        }
        return trim($razon . ' ' . $tipo);
    }

    protected $casts = [
        'active' => 'boolean',
        'google_oauth_tokens' => 'encrypted',
        'dias_habiles' => 'array',
        'numero_empleados' => 'integer',
    ];

    /**
     * ¿La empresa está obligada a tener RIT? (Art. 105 CST, según nº de empleados y
     * actividad económica). Devuelve null si aún no se registró el nº de empleados.
     */
    public function requiereRit(): ?bool
    {
        return \App\Support\ObligacionRit::requiere(
            $this->numero_empleados,
            $this->actividadEconomica?->seccion,
        );
    }

    /**
     * Días laborales EFECTIVOS (valor legado 'lunes_viernes'|'lunes_sabado'|...):
     * se toman del RIT activo si los define; si no, del campo de la empresa; por
     * defecto Lunes a Viernes. Se conserva para compatibilidad con el esquema binario.
     */
    public function diasLaboralesEfectivos(): string
    {
        $ritDias = $this->reglamentoInterno?->dias_laborales;

        return $ritDias ?: ($this->dias_laborales ?: 'lunes_viernes');
    }

    /**
     * Conjunto EFECTIVO de días hábiles como días ISO (1 = lunes … 7 = domingo).
     * Prioridad: dias_habiles del RIT activo → dias_habiles de la empresa → mapeo
     * del valor legado. Soporta cualquier combinación, incluido domingo y 24/7.
     */
    public function diasHabilesSet(): array
    {
        $ritSet = $this->reglamentoInterno?->dias_habiles;
        if (is_array($ritSet) && ! empty($ritSet)) {
            return \App\Support\DiasHabiles::normalizar($ritSet);
        }

        if (is_array($this->dias_habiles) && ! empty($this->dias_habiles)) {
            return \App\Support\DiasHabiles::normalizar($this->dias_habiles);
        }

        return \App\Support\DiasHabiles::desdeLegado($this->diasLaboralesEfectivos());
    }

    /** ¿La fecha dada cae en un día hábil de la semana según la jornada de la empresa? */
    public function esDiaHabilSemana(\Carbon\Carbon $fecha): bool
    {
        return in_array($fecha->dayOfWeekIso, $this->diasHabilesSet(), true);
    }

    /**
     * Verifica si la empresa trabaja los sábados (según el conjunto efectivo).
     * Se mantiene por compatibilidad con el código que aún razona en binario.
     */
    public function trabajaSabados(): bool
    {
        return in_array(6, $this->diasHabilesSet(), true);
    }

    /** Texto legible de los días hábiles efectivos. */
    public function getDiasLaboralesTextoAttribute(): string
    {
        return \App\Support\DiasHabiles::texto($this->diasHabilesSet());
    }

    public function bufete(): BelongsTo
    {
        return $this->belongsTo(Bufete::class);
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

    /**
     * ¿La empresa tiene un Reglamento Interno vigente y con contenido?
     * Verdadero para un RIT subido o construido/mejorado por IA que ya tiene texto.
     * Un RIT en generación (sin texto) todavía NO cuenta como vigente.
     */
    public function tieneRitVigente(): bool
    {
        $rit = $this->reglamentoInterno; // activo=true, más reciente
        return $rit !== null && !empty($rit->texto_completo);
    }

    public function puedeUsarTodasLasSanciones(): bool
    {
        $suscripcion = $this->suscripcion;
        if (!$suscripcion || !$suscripcion->incluyeRIT()) {
            return false;
        }
        return $this->reglamentoInterno !== null;
    }

    /**
     * Empresas seleccionables en formularios y filtros: solo activas. Al pasar
     * $idAConservar (el empresa_id que el registro YA tiene asignado) esa empresa
     * se conserva visible aunque se haya desactivado después, para no romper la
     * edición de registros existentes ni vaciar el campo silenciosamente.
     */
    public function scopeParaAsignar(Builder $query, ?int $idAConservar = null): Builder
    {
        return $query->where(function (Builder $q) use ($idAConservar) {
            $q->where('active', true);
            if ($idAConservar) {
                $q->orWhere('id', $idAConservar);
            }
        });
    }
}
