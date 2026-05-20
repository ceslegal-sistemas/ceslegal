<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglamentoInterno extends Model
{
    protected $table = 'reglamentos_internos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'texto_completo',
        'ruta_docx',
        'activo',
        'respuestas_cuestionario',
        'fuente',
        'sanciones_extraidas',
        'version',
        'auditoria_origen_id',
        'reglamento_origen_id',
        'ruta_pdf',
    ];

    protected $casts = [
        'activo'                 => 'boolean',
        'respuestas_cuestionario' => 'array',
        'sanciones_extraidas'    => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fragmentos(): HasMany
    {
        return $this->hasMany(FragmentoReglamento::class, 'reglamento_interno_id')->orderBy('orden');
    }

    public function auditoriaOrigen(): BelongsTo
    {
        return $this->belongsTo(AuditoriaRIT::class, 'auditoria_origen_id');
    }

    public function reglamentoOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reglamento_origen_id');
    }

    public function esMejorado(): bool
    {
        return $this->version > 1;
    }
}
