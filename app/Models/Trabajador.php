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
    public function aceptoRitVigente(): bool
    {
        $ritActivo = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        if (!$ritActivo) {
            return false;
        }

        return $this->aceptacionesReglamentoInterno()
            ->where('reglamento_interno_id', $ritActivo->id)
            ->exists();
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
