<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoLegal extends Model
{
    protected $table = 'documentos_legales';

    protected $fillable = [
        'titulo',
        'tipo',
        'referencia',
        'descripcion',
        'archivo_path',
        'archivo_nombre_original',
        'estado',
        'error_mensaje',
        'total_fragmentos',
        'total_palabras',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'total_fragmentos' => 'integer',
        'total_palabras'   => 'integer',
    ];

    public static array $tiposLabels = [
        'sentencia_cc'        => 'Sentencia — Corte Constitucional',
        'sentencia_csj'       => 'Sentencia — Corte Suprema de Justicia',
        'sentencia_ce'        => 'Sentencia — Consejo de Estado',
        'cst'                 => 'Código Sustantivo del Trabajo',
        'ley'                 => 'Ley',
        'concepto_ministerio' => 'Concepto del Ministerio de Trabajo',
        'doctrina'            => 'Doctrina / Libro',
        'rit_referencia'      => 'Reglamento Interno de Trabajo (referencia)',
        'otro'                => 'Otro',
    ];

    public function fragmentos(): HasMany
    {
        return $this->hasMany(FragmentoDocumento::class)->orderBy('orden');
    }

    public function getTipoLabelAttribute(): string
    {
        return static::$tiposLabels[$this->tipo] ?? $this->tipo;
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeProcesados($query)
    {
        return $query->where('estado', 'procesado');
    }

    public function estaProcesado(): bool
    {
        return $this->estado === 'procesado';
    }

    public function getCitaAttribute(): string
    {
        return $this->referencia
            ? "{$this->titulo} ({$this->referencia})"
            : $this->titulo;
    }
}
