<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcesoDisciplinario extends Model
{
    use SoftDeletes;

    protected $table = 'procesos_disciplinarios';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'trabajador_id',
        'abogado_id',
        'estado',
        'hechos',
        'fecha_ocurrencia',
        'fechas_ocurrencia_adicionales',
        'normas_incumplidas',
        'articulos_legales_ids',
        'sanciones_laborales_ids',
        'otro_motivo_descargos',
        'pruebas_iniciales',
        'fecha_solicitud',
        'fecha_apertura',
        'fecha_descargos_programada',
        'hora_descargos_programada',
        'modalidad_descargos',
        'fecha_descargos_realizada',
        'fecha_analisis',
        'decision_sancion',
        'motivo_archivo',
        'tipo_sancion',
        'dias_suspension',
        'fecha_notificacion',
        'fecha_limite_impugnacion',
        'impugnado',
        'fecha_impugnacion',
        'fecha_cierre',
    ];

    protected $casts = [
        'fecha_ocurrencia' => 'date',
        'fechas_ocurrencia_adicionales' => 'array',
        'fecha_solicitud' => 'datetime',
        'fecha_apertura' => 'datetime',
        'fecha_descargos_programada' => 'datetime',
        'fecha_descargos_realizada' => 'datetime',
        'fecha_analisis' => 'datetime',
        'decision_sancion' => 'boolean',
        'fecha_notificacion' => 'datetime',
        'fecha_limite_impugnacion' => 'datetime',
        'impugnado' => 'boolean',
        'fecha_impugnacion' => 'datetime',
        'fecha_cierre' => 'datetime',
        'articulos_legales_ids' => 'array',
        'sanciones_laborales_ids' => 'array',
    ];

    /**
     * Obtener todas las fechas de ocurrencia (principal + adicionales)
     */
    public function getTodasLasFechasOcurrenciaAttribute(): array
    {
        $fechas = [];

        if ($this->fecha_ocurrencia) {
            $fechas[] = $this->fecha_ocurrencia;
        }

        if (!empty($this->fechas_ocurrencia_adicionales)) {
            foreach ($this->fechas_ocurrencia_adicionales as $item) {
                if (isset($item['fecha'])) {
                    $fechas[] = \Carbon\Carbon::parse($item['fecha']);
                }
            }
        }

        // Ordenar las fechas
        usort($fechas, fn($a, $b) => $a->timestamp <=> $b->timestamp);

        return $fechas;
    }

    /**
     * Obtener las fechas de ocurrencia formateadas como texto
     */
    public function getFechasOcurrenciaTextoAttribute(): string
    {
        $fechas = $this->todasLasFechasOcurrencia;

        if (empty($fechas)) {
            return 'No especificada';
        }

        if (count($fechas) === 1) {
            return $fechas[0]->format('d/m/Y');
        }

        // Múltiples fechas
        return collect($fechas)
            ->map(fn($f) => $f->format('d/m/Y'))
            ->join(', ', ' y ');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    public function diligenciaDescargo(): HasOne
    {
        return $this->hasOne(DiligenciaDescargo::class, 'proceso_id');
    }

    public function analisisJuridicos(): HasMany
    {
        return $this->hasMany(AnalisisJuridico::class, 'proceso_id');
    }

    public function sancion(): HasOne
    {
        return $this->hasOne(Sancion::class, 'proceso_id');
    }

    public function impugnacion(): HasOne
    {
        return $this->hasOne(Impugnacion::class, 'proceso_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class, 'proceso_id')
            ->where('proceso_tipo', 'proceso_disciplinario')
            ->orderBy('created_at', 'desc');
    }

    public function terminosLegales(): HasMany
    {
        return $this->hasMany(TerminoLegal::class, 'proceso_id')
            ->where('proceso_tipo', 'proceso_disciplinario');
    }

    /**
     * Relación con los trackings de email
     */
    public function emailTrackings(): HasMany
    {
        return $this->hasMany(EmailTracking::class, 'proceso_id');
    }

    /**
     * Verificar si la citación fue leída por el trabajador
     */
    public function citacionFueLeida(): bool
    {
        return $this->emailTrackings()
            ->where('tipo_correo', 'citacion')
            ->whereNotNull('abierto_en')
            ->exists();
    }

    /**
     * Verificar si la sanción fue leída por el trabajador
     */
    public function sancionFueLeida(): bool
    {
        return $this->emailTrackings()
            ->where('tipo_correo', 'sancion')
            ->whereNotNull('abierto_en')
            ->exists();
    }

    /**
     * Obtener el último tracking de citación
     */
    public function getUltimoTrackingCitacionAttribute(): ?EmailTracking
    {
        return $this->emailTrackings()
            ->where('tipo_correo', 'citacion')
            ->latest('enviado_en')
            ->first();
    }

    /**
     * Obtener el último tracking de sanción
     */
    public function getUltimoTrackingSancionAttribute(): ?EmailTracking
    {
        return $this->emailTrackings()
            ->where('tipo_correo', 'sancion')
            ->latest('enviado_en')
            ->first();
    }

    /**
     * Obtener los artículos legales seleccionados para este proceso
     */
    public function getArticulosLegalesAttribute()
    {
        if (empty($this->articulos_legales_ids)) {
            return collect();
        }

        return ArticuloLegal::whereIn('id', $this->articulos_legales_ids)
            ->ordenado()
            ->get();
    }

    /**
     * Obtener el texto de los artículos legales para la citación
     * Formato completo con código, título y descripción
     */
    public function getArticulosLegalesTextoAttribute(): string
    {
        if (empty($this->articulos_legales_ids)) {
            return 'No especificado';
        }

        $articulos = $this->articulosLegales;

        if ($articulos->isEmpty()) {
            return 'No especificado';
        }

        // Formato completo: Código - Título + Descripción (separados por párrafos)
        $textoCompleto = [];

        foreach ($articulos as $articulo) {
            $textoArticulo = '';

            // Código y título en una línea
            $textoArticulo .= $articulo->codigo;
            if (!empty($articulo->titulo)) {
                $textoArticulo .= ' - ' . $articulo->titulo;
            }

            // Descripción en la siguiente línea
            if (!empty($articulo->descripcion)) {
                $textoArticulo .= "\n" . $articulo->descripcion;
            }

            $textoCompleto[] = $textoArticulo;
        }

        // Unir todos los artículos con doble salto de línea (párrafo)
        return implode("\n\n", $textoCompleto);
    }

    /**
     * Obtener solo los códigos de los artículos (versión corta)
     */
    public function getArticulosLegalesCodigosAttribute(): string
    {
        if (empty($this->articulos_legales_ids)) {
            return 'No especificado';
        }

        $articulos = $this->articulosLegales;

        if ($articulos->isEmpty()) {
            return 'No especificado';
        }

        // Formato corto: "Art. 58, Art. 60 Num. 1, Art. 60 Num. 3"
        return $articulos->pluck('codigo')->join(', ');
    }

    /**
     * ==================== SANCIONES LABORALES ====================
     */

    /**
     * Obtener las sanciones laborales relacionadas
     */
    public function getSancionesLaboralesAttribute()
    {
        if (empty($this->sanciones_laborales_ids)) {
            return collect([]);
        }

        return SancionLaboral::whereIn('id', $this->sanciones_laborales_ids)
            ->ordenado()
            ->get();
    }

    /**
     * Obtener el texto de las sanciones laborales para la citación
     * Formato completo con nombre claro y descripción
     */
    public function getSancionesLaboralesTextoAttribute(): string
    {
        if (empty($this->sanciones_laborales_ids)) {
            return 'No especificado';
        }

        $sanciones = $this->sancionesLaborales;

        if ($sanciones->isEmpty()) {
            return 'No especificado';
        }

        // Formato: Nombre claro + Descripción (separados por párrafos)
        $textoCompleto = [];

        foreach ($sanciones as $sancion) {
            $textoSancion = '';

            // Nombre claro con emoji de tipo de falta
            $emoji = $sancion->tipo_falta === 'leve' ? '🟢' : '🔴';
            $textoSancion .= "{$emoji} {$sancion->nombre_claro}";

            // Descripción en la siguiente línea
            if (!empty($sancion->descripcion)) {
                $textoSancion .= "\n" . $sancion->descripcion;
            }

            $textoCompleto[] = $textoSancion;
        }

        // Unir todas las sanciones con doble salto de línea (párrafo)
        return implode("\n\n", $textoCompleto);
    }

    /**
     * Obtener solo los nombres claros de las sanciones (versión corta)
     */
    public function getSancionesLaboralesNombresAttribute(): string
    {
        if (empty($this->sanciones_laborales_ids)) {
            return 'No especificado';
        }

        $sanciones = $this->sancionesLaborales;

        if ($sanciones->isEmpty()) {
            return 'No especificado';
        }

        // Formato corto: "Retardo de 15 minutos (1ra vez), Falta de respeto leve"
        return $sanciones->pluck('nombre_claro')->join(', ');
    }

    /**
     * Métodos para manejo de estados
     */

    public function cambiarEstado(string $nuevoEstado, ?string $observacion = null): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        return $estadoService->cambiarEstado($this, $nuevoEstado, $observacion);
    }

    public function getProximosEstadosValidos(): array
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        return $estadoService->getProximosEstadosValidos($this->estado);
    }

    public function getDescripcionEstado(): string
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        return $estadoService->getDescripcionEstado($this->estado);
    }

    public function puedeAvanzarA(string $nuevoEstado): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        return $estadoService->esTransicionValida($this->estado, $nuevoEstado);
    }

    // Métodos de conveniencia para transiciones comunes

    public function marcarCitado(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alEnviarCitacion($this);
        return true;
    }

    public function marcarDescargosCompletados(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alCompletarDescargos($this);
        return true;
    }

    public function marcarEnAnalisis(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alCrearAnalisisJuridico($this);
        return true;
    }

    public function marcarSancionDefinida(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alDefinirSancion($this);
        return true;
    }

    public function marcarNotificado(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alNotificarTrabajador($this);
        return true;
    }

    public function marcarImpugnado(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alImpugnar($this);
        return true;
    }

    public function cerrarProceso(): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alCerrarProceso($this);
        return true;
    }

    public function archivarProceso(string $motivo): bool
    {
        $estadoService = app(\App\Services\EstadoProcesoService::class);
        $estadoService->alArchivarProceso($this, $motivo);
        return true;
    }
}
