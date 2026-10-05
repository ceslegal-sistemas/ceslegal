<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trabajador extends Model
{
    use ScopedToBufeteOrEmpresa;

    protected $table = 'trabajadores';

    /**
     * Tipos de fuero / estabilidad laboral reforzada en Colombia.
     * Se almacena la conclusión jurídica, no el dato médico (Ley 1581 de 2012).
     */
    public const TIPOS_FUERO = [
        'maternidad'     => 'Fuero de maternidad o lactancia',
        'sindical'       => 'Fuero sindical',
        'salud'          => 'Estabilidad reforzada por salud o discapacidad',
        'prepensionado'  => 'Prepensionado (próximo a pensión)',
        'acoso_ley_1010' => 'Víctima de acoso laboral (Ley 1010 de 2006)',
    ];

    protected $fillable = [
        'empresa_id',
        'tipo_documento',
        'numero_documento',
        'genero',
        'nombres',
        'apellidos',
        'departamento_nacimiento',
        'ciudad_nacimiento',
        'cargo',
        'area',
        'tipos_fuero',
        'fuero_nota',
        'fecha_ingreso',
        'email',
        'telefono',
        'direccion',
        'active',
        'foto_referencia_path',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'active' => 'boolean',
        'tipos_fuero' => 'array',
    ];

    /** ¿El trabajador tiene algún fuero/estabilidad reforzada registrado? */
    public function tieneFuero(): bool
    {
        return !empty($this->tipos_fuero);
    }

    /** Etiquetas legibles de los fueros registrados (para prompts y UI). */
    public function tiposFueroLabels(): array
    {
        return collect($this->tipos_fuero ?? [])
            ->map(fn ($k) => self::TIPOS_FUERO[$k] ?? $k)
            ->values()
            ->all();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function procesosDisciplinarios(): HasMany
    {
        return $this->hasMany(ProcesoDisciplinario::class);
    }

    public function aceptacionesReglamentoInterno(): HasMany
    {
        return $this->hasMany(AceptacionReglamentoInterno::class);
    }

    /**
     * ¿Ya aceptó la versión del RIT que está activa AHORA MISMO en su
     * empresa? Si el RIT cambió desde su última aceptación (aunque haya
     * aceptado una versión anterior), devuelve false - debe volver a
     * aceptar la nueva.
     *
     * withoutGlobalScope: este método se usa tanto desde el panel admin
     * (TrabajadorResource) como desde el flujo público de socialización del
     * RIT (sin sesión, o con sesión de OTRO bufete/empresa activa en el
     * mismo navegador) - siempre debe resolver el RIT activo de la empresa
     * REAL de este trabajador ($this->empresa_id), nunca filtrado por la
     * empresa/bufete de quien esté preguntando.
     */
    /**
     * Compara por HASH del texto, no por reglamento_interno_id (bug real de
     * integridad probatoria corregido 2026-09-28: el Plan B puede mutar
     * texto_completo del MISMO registro de RIT sin crear uno nuevo - con la
     * comparación anterior, un trabajador que aceptó ANTES de esa mutación
     * seguía marcado como "al día" para siempre, aunque el texto real ya
     * fuera otro). Si el RIT nunca cambió de texto, el hash coincide y no
     * hace falta volver a aceptar - solo un cambio de contenido real exige
     * una nueva aceptación.
     */
    public function aceptoRitVigente(): bool
    {
        $ritActivo = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        if (!$ritActivo || blank($ritActivo->texto_completo)) {
            return false;
        }

        $hashVigente = hash('sha256', $ritActivo->texto_completo);

        return $this->aceptacionesReglamentoInterno()
            ->where('texto_rit_hash', $hashVigente)
            ->exists();
    }

    /**
     * Resuelve qué aceptación del RIT rige a este trabajador para efectos de
     * un proceso disciplinario: la vigente (RIT activo actual) si existe, o
     * si no, la más reciente de cualquier versión ANTERIOR que sí aceptó.
     * Null si nunca aceptó ninguna versión.
     *
     * Regla legal confirmada por William (abogado, reunión 2026-09-29): no
     * se puede aplicar el RIT activo a un trabajador que no lo ha aceptado -
     * el proceso debe regirse por la última versión que SÍ aceptó. Ver
     * [[backlog-rit-anterior-si-no-acepto-actualizacion]].
     */
    public function aceptacionRitAplicable(): ?AceptacionReglamentoInterno
    {
        $ritActivo = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        if ($ritActivo && filled($ritActivo->texto_completo)) {
            $hashVigente = hash('sha256', $ritActivo->texto_completo);
            $vigente = $this->aceptacionesReglamentoInterno()
                ->where('texto_rit_hash', $hashVigente)
                ->latest('aceptado_en')
                ->first();
            if ($vigente) {
                return $vigente;
            }
        }

        return $this->aceptacionesReglamentoInterno()->latest('aceptado_en')->first();
    }

    public function publicacionesReglamentoInterno(): HasMany
    {
        return $this->hasMany(PublicacionReglamentoInterno::class);
    }

    /**
     * Mismo criterio exacto que aceptoRitVigente() (comparación por HASH,
     * no por reglamento_interno_id) pero para la evidencia LIGERA de la
     * Fase 1 (Publicación) - ver PublicacionReglamentoInterno.
     */
    public function confirmoPublicacionVigente(): bool
    {
        $ritActivo = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        if (!$ritActivo || blank($ritActivo->texto_completo)) {
            return false;
        }

        $hashVigente = hash('sha256', $ritActivo->texto_completo);

        return $this->publicacionesReglamentoInterno()
            ->where('texto_rit_hash', $hashVigente)
            ->exists();
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
