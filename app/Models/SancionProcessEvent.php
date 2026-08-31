<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SancionProcessEvent extends Model
{
    protected $fillable = [
        'proceso_id',
        'user_id',
        'event_type',
        'ip',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Tiempo mínimo de deliberación exigido entre elegir la sanción
     * (decision_selected) y poder confirmarla. No pretende "medir" cuánto
     * pensó el autorizador: sirve para descartar una confirmación automática
     * o un doble clic accidental, dejando constancia de que hubo una pausa
     * humana real entre la decisión y su firma. Un umbral largo (5+ min) no
     * probaría más - el usuario simplemente dejaría el modal abierto - y sí
     * volvería el flujo inusable.
     */
    public const SEGUNDOS_MINIMOS_DELIBERACION = 60;

    /**
     * Segundos transcurridos desde que se eligió la sanción en este proceso.
     * null si nunca se registró una elección (flujos antiguos o parciales):
     * en ese caso no se bloquea nada, para no romper procesos en curso.
     */
    public static function segundosDesdeDecision(int $procesoId): ?int
    {
        $ultima = static::where('proceso_id', $procesoId)
            ->where('event_type', 'decision_selected')
            ->latest('created_at')
            ->first();

        // (int) explícito: en Carbon 3 diffInSeconds() devuelve float, y
        // retornarlo tal cual en un ?int dispara un DEPRECATED de conversión
        // implícita con pérdida de precisión en cada llamada (PHP 8.1+).
        // abs(): defensa ante un created_at futuro por desfase de reloj, que
        // daría negativo y saltaría el bloqueo.
        return $ultima ? (int) abs($ultima->created_at->diffInSeconds(now())) : null;
    }

    /**
     * Único punto de creación de eventos de la traza: captura siempre quién,
     * cuándo, desde qué IP y con qué navegador. Antes cada llamador hacía su
     * propio ::create() y ninguno guardaba IP/user agent, así que la traza no
     * servía como prueba de que la decisión la tomó una persona real desde un
     * dispositivo concreto.
     */
    public static function registrar(int $procesoId, string $tipo, array $meta = []): self
    {
        return static::create([
            'proceso_id' => $procesoId,
            'user_id'    => auth()->id(),
            'event_type' => $tipo,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => $meta ?: null,
        ]);
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoDisciplinario::class, 'proceso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
