<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminoLegal extends Model
{
    protected $table = 'terminos_legales';

    protected $fillable = [
        'proceso_tipo',
        'proceso_id',
        'termino_tipo',
        'fecha_inicio',
        'dias_habiles',
        'fecha_vencimiento',
        'dias_transcurridos',
        'estado',
        'fecha_cierre',
        'notificacion_enviada',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'dias_habiles' => 'integer',
        'dias_transcurridos' => 'integer',
        'fecha_cierre' => 'datetime',
        'notificacion_enviada' => 'boolean',
    ];

    public function proceso()
    {
        if ($this->proceso_tipo === 'proceso_disciplinario') {
            return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
        } else {
            return $this->belongsTo(SolicitudContrato::class, 'proceso_id');
        }
    }
}
