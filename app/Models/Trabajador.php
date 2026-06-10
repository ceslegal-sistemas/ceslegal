<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trabajador extends Model
{
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

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }
}
