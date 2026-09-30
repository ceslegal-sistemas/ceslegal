<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglamentoInterno extends Model
{
    use ScopedToBufeteOrEmpresa;
    protected $table = 'reglamentos_internos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'texto_completo',
        'ruta_docx',
        'activo',
        'fecha_publicacion_socializacion',
        'respuestas_cuestionario',
        'fuente',
        'dias_laborales',
        'dias_habiles',
        'estado_generacion',
        'mensaje_error_ia',
        'sanciones_extraidas',
        'conductas_sancionables',
        'organigrama',
        'version',
        'auditoria_origen_id',
        'reglamento_origen_id',
        'ruta_pdf',
        'video_didactico_path',
        'video_didactico_capitulos',
        'video_didactico_estado',
        'video_didactico_error',
        'video_didactico_generado_en',
        'progreso_generacion',
        'tipos_contrato',
        'temas_texto_hash',
        'temas_clasificados_en',
        'resumen_simple_texto_hash',
    ];

    protected $casts = [
        'activo'                 => 'boolean',
        'fecha_publicacion_socializacion' => 'date',
        'respuestas_cuestionario' => 'array',
        'sanciones_extraidas'    => 'array',
        'conductas_sancionables' => 'array',
        'organigrama'            => 'array',
        'tipos_contrato'         => 'array',
        'dias_habiles'           => 'array',
        'video_didactico_capitulos' => 'array',
        'temas_clasificados_en'  => 'datetime',
        'video_didactico_generado_en' => 'datetime',
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

    public function temasNormativos(): BelongsToMany
    {
        return $this->belongsToMany(TemaNormativo::class, 'reglamento_interno_tema')
            ->withPivot('resumen_simple', 'pregunta_vf', 'respuesta_correcta');
    }

    public function esMejorado(): bool
    {
        return $this->version > 1;
    }

    public function estaGenerando(): bool
    {
        return $this->estado_generacion === 'generando';
    }

    public function tieneErrorGeneracion(): bool
    {
        return $this->estado_generacion === 'error';
    }

    public function generandoVideoDidactico(): bool
    {
        return $this->video_didactico_estado === 'generando';
    }

    public function tieneErrorVideoDidactico(): bool
    {
        return $this->video_didactico_estado === 'error';
    }

    public function tieneVideoDidactico(): bool
    {
        return !empty($this->video_didactico_capitulos) || !empty($this->video_didactico_path);
    }

    /**
     * Capítulos del video didáctico (un clip independiente por tema o
     * cambio - rediseño 2026-09-30, ver RitVideoDidacticoService). Los
     * videos generados ANTES de ese cambio solo tienen `video_didactico_path`
     * (un solo archivo) - se envuelve como un capítulo único para que el
     * resto del código (reproductor admin y público) no necesite distinguir
     * entre el formato viejo y el nuevo.
     *
     * @return array<int, array{titulo: string, path: string}>
     */
    public function capitulosVideoDidactico(): array
    {
        if (!empty($this->video_didactico_capitulos)) {
            return $this->video_didactico_capitulos;
        }

        if (!empty($this->video_didactico_path)) {
            return [['titulo' => 'Reglamento Interno', 'path' => $this->video_didactico_path]];
        }

        return [];
    }

    /**
     * Único punto de entrada para "reemplazar el RIT activo de una
     * empresa" (construir uno nuevo, subir uno manual, adoptar una mejora
     * de auditoría). Desactiva los RIT activos Y cierra cualquier
     * SugerenciaActualizacionRit pendiente que quedaría apuntando a un RIT
     * ya reemplazado - aprobarla no tendría ningún efecto visible, porque
     * RitActualizacionAutomaticaService::aplicarSugerencia() modifica el
     * texto del RIT al que la sugerencia apunta, no el RIT activo actual
     * de la empresa. Bug real reportado por el usuario: el Dashboard
     * seguía contando la sugerencia (cuenta por empresa_id) mientras "Mi
     * Reglamento Interno" dejaba de mostrarla (filtra por el RIT vigente),
     * sin ningún lugar donde gestionarla.
     */
    public static function desactivarActivosDe(int $empresaId): void
    {
        $idsActivos = static::where('empresa_id', $empresaId)->where('activo', true)->pluck('id');

        if ($idsActivos->isEmpty()) {
            return;
        }

        static::whereIn('id', $idsActivos)->update(['activo' => false]);

        SugerenciaActualizacionRit::whereIn('reglamento_interno_id', $idsActivos)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'rechazada', 'resuelto_por' => null, 'resuelto_en' => now()]);
    }

    /**
     * Fecha límite (15 días hábiles desde la fecha de publicación declarada
     * por el cliente) para que un trabajador objete el Reglamento - pedido
     * del equipo en la reunión (2026-09-29): la empresa puede tardar días en
     * publicar físicamente el Reglamento (carteleras), así que el conteo
     * legal arranca desde ESA fecha, no desde que el sistema lo generó.
     * Reusa TerminoLegalService::calcularFechaVencimiento() (mismo cálculo
     * de días hábiles ya usado para el plazo de impugnación de sanciones),
     * pero solo como cálculo en vivo - no crea un registro de TerminoLegal
     * (ese sistema es para procesos disciplinarios/contratos, no aplica aquí).
     */
    public function fechaLimiteObjecion(): ?\Carbon\Carbon
    {
        if (! $this->fecha_publicacion_socializacion) {
            return null;
        }

        return app(\App\Services\TerminoLegalService::class)->calcularFechaVencimiento(
            \Carbon\Carbon::parse($this->fecha_publicacion_socializacion),
            15,
            $this->empresa ? $this->empresa->diasHabilesSet() : false,
        );
    }
}
