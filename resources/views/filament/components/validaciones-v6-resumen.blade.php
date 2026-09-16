{{--
    Resumen de la revisión de calidad adicional que corre en segundo plano
    (EjecutarValidacionesV6Job) después de generar la recomendación de sanción.
    Sin jerga interna ("motores V6") - solo lo que le interesa a Recursos
    Humanos: ¿esta recomendación se sostiene, o hay algo que revisar antes de
    confirmar? Variables esperadas: $estado (string|null), $resultados
    (array|null), $en (\Carbon\Carbon|null), $puntosClave (array|null - lista
    ya consolidada/deduplicada entre motores, ver consolidarHallazgosV6()).

    Mientras el job sigue en curso, este panel se refresca solo cada pocos
    segundos (wire:poll) - no hace falta cerrar y reabrir el modal. El botón
    "Continuar" del modal está bloqueado en el servidor mientras el estado es
    pendiente/procesando (ver ->action() de la acción emitir_sancion en
    ProcesoDisciplinarioResource.php) para que nunca se pueda confirmar la
    recomendación antes de que una posible corrección automática se aplique.
--}}
@php
    $estado      = $estado ?? null;
    $resultados  = is_array($resultados ?? null) ? $resultados : [];
    // Lista ya consolidada/deduplicada por IAAnalisisSancionService::consolidarHallazgosV6()
    // (calculada por el job, no aquí - una llamada más a Gemini no puede hacerse en
    // cada render del modal). Si viene vacía pero SÍ hay hallazgos por motor, es que
    // la consolidación falló o nunca se ejecutó - se cae al detalle por motor sin
    // perder información.
    $puntosClave = is_array($puntosClave ?? null) ? array_values(array_filter($puntosClave)) : [];

    // La clasificación bien/mal por motor y la limpieza de texto viven en
    // IAAnalisisSancionService::evaluarMotoresV6() - única fuente de verdad
    // compartida con EjecutarValidacionesV6Job (que la usa para decidir si
    // corrige la recomendación). Aquí solo se decide cómo mostrarlo.
    $maxHallazgosV6 = 3;

    $filasCrudas = app(\App\Services\IAAnalisisSancionService::class)->evaluarMotoresV6($resultados);

    $filas = [];
    foreach ($filasCrudas as $fila) {
        $totalHallazgos = count($fila['hallazgos']);
        $filas[] = [
            'titulo'    => $fila['titulo'],
            'icon'      => $fila['icon'],
            'estado'    => $fila['estado'],
            'hallazgos' => array_slice($fila['hallazgos'], 0, $maxHallazgosV6),
            'ocultos'   => max(0, $totalHallazgos - $maxHallazgosV6),
        ];
    }
    // Mismo criterio que usa cada fila para decidir si muestra "Sin
    // observaciones" o el detalle desplegable (!$tieneDetalle más abajo, en
    // el foreach) - así el resumen de arriba nunca puede contradecir lo que
    // el cliente ve fila por fila. Antes se contaba por severidad (estado
    // ok vs atencion/riesgo), lo que podía decir "N con observaciones"
    // aunque varias de esas filas mostraran "Sin observaciones" al no tener
    // texto - la severidad igual se ve en el color/ícono de cada fila.
    $numRevisables = collect($filas)->filter(fn($f) => !empty($f['hallazgos']))->count();
    // No cuenta las filas 'na' (motor sin dato disponible - muestran "no
    // disponible", no "Sin observaciones"): si se sumaran aquí, el badge
    // verde diría "sin observaciones" de algo que en realidad no se pudo
    // evaluar.
    $numOk = collect($filas)->filter(fn($f) => empty($f['hallazgos']) && $f['estado'] !== 'na')->count();
@endphp

@if($estado)
{{-- @include('filament.components.lupe-hero-styles') YA está incluido por
     emitir-sancion-analisis.blade.php, que siempre precede a este parcial en
     el Paso 1 del wizard - no se repite aquí para no duplicar el <style>. --}}
<div class="rit-hero v6chk-wrap" style="padding:1.15rem 1.5rem;margin-top:6px;" @if(in_array($estado, ['pendiente', 'procesando'], true)) wire:poll.4000ms @endif>
    <div class="rit-orb-b"></div>
    <div class="rit-orb-g"></div>
    <div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">
        <span class="rit-badge rit-badge-ia">Revisión de calidad de la recomendación</span>
        <p class="rit-sub" style="margin-top:.5rem;">
            Chequeo automático aparte, no reemplaza los puntos anteriores.
        </p>

        @if(in_array($estado, ['pendiente', 'procesando'], true))
            <div style="display:flex;align-items:center;gap:8px;margin:8px 0 0;">
                <span class="v6chk-spinner" aria-hidden="true"></span>
                <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:0;">
                    Estamos revisando la recomendación con más detalle (coherencia, pruebas, redacción...).
                    Suele tardar menos de un minuto - esta ventana se actualiza sola. <strong style="color:var(--esa-text);">Mientras tanto no se puede confirmar la sanción.</strong>
                </p>
            </div>
        @elseif($estado === 'error')
            <p style="font-size:12.5px;color:var(--esa-muted);line-height:1.6;margin:6px 0 0;">
                No se pudo completar esta revisión adicional. Esto no bloquea la emisión de la sanción -
                la recomendación principal de arriba sigue siendo válida.
            </p>
        @elseif($estado === 'completado')
            @php
                $filasConHallazgos = collect($filas)->filter(fn($f) => !empty($f['hallazgos']));
                // No accionables: sin hallazgos, ya sea porque el motor calificó
                // bien ('ok') o porque no hubo dato para evaluarlo ('na') - ambos
                // casos se colapsan juntos (ninguno requiere revisar nada), pero
                // los 'na' se anotan como "no disponible" dentro de la misma lista
                // para no perder esa distinción.
                $filasSinHallazgos = collect($filas)->reject(fn($f) => !empty($f['hallazgos']));
            @endphp

            @if($en)
                <p style="font-size:11px;color:var(--esa-muted);opacity:.75;margin:8px 0 10px;text-align:right;">{{ $en->diffForHumans() }}</p>
            @endif

            @if($filasConHallazgos->isNotEmpty() || !empty($puntosClave))
                <div style="border-radius:10px;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);padding:12px 14px;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <lord-icon src="https://cdn.lordicon.com/lltgvngb.json" trigger="loop" delay="500" colors="primary:#f87171,secondary:#fca5a5" style="width:22px;height:22px;flex-shrink:0;"></lord-icon>
                        <p style="font-size:11px;font-weight:700;color:#f87171;margin:0;text-transform:uppercase;letter-spacing:.05em;">
                            Requiere su atención ({{ !empty($puntosClave) ? count($puntosClave) : $filasConHallazgos->count() }})
                        </p>
                    </div>
                    <div style="font-size:12.5px;color:var(--esa-text);line-height:1.7;padding-left:4px;">
                        @if(!empty($puntosClave))
                            @foreach($puntosClave as $punto)
                                <div>› {{ $punto }}</div>
                            @endforeach
                        @else
                            @foreach($filasConHallazgos as $fila)
                                @foreach($fila['hallazgos'] as $h)
                                    <div>› {{ $h }}</div>
                                @endforeach
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            @if($filasSinHallazgos->isNotEmpty())
                <details @if(empty($puntosClave) && $filasConHallazgos->isEmpty()) open @endif style="border-radius:10px;background:rgba(0,0,0,.02);padding:9px 14px;">
                    <summary style="cursor:pointer;font-size:11.5px;color:var(--esa-muted);list-style:none;display:flex;align-items:center;gap:6px;">
                        <lord-icon src="https://cdn.lordicon.com/lvrxlmju.json" trigger="hover" colors="primary:#16a34a,secondary:#4ade80" style="width:16px;height:16px;flex-shrink:0;"></lord-icon>
                        {{ $filasSinHallazgos->count() }} chequeo{{ $filasSinHallazgos->count() > 1 ? 's' : '' }} sin observaciones
                    </summary>
                    <div style="margin-top:8px;font-size:12px;color:var(--esa-muted);line-height:1.8;padding-left:22px;">
                        {{ $filasSinHallazgos->map(fn($f) => $f['estado'] === 'na' ? "{$f['titulo']} (no disponible)" : $f['titulo'])->implode(' · ') }}
                    </div>
                </details>
            @endif

            {{-- Fila de riesgo con auditoría obligatoria (acknowledgeRisk): si tiene
                 hallazgos ya quedó dentro del bloque "Requiere su atención" de arriba,
                 pero el x-on:toggle/wire:ignore.self debe seguir presente en algún
                 <details> real - se renderiza aparte, oculto tras un <details> propio
                 dentro del bloque rojo si aplica. --}}
            @foreach($filas as $fila)
                @php
                    $esMotorRiesgo = ($onRiskOpen ?? false) && $fila['titulo'] === 'Resistencia ante una revisión judicial';
                @endphp
                @if($esMotorRiesgo && !empty($fila['hallazgos']))
                    <details wire:ignore.self x-on:toggle="if ($event.target.open) { $wire.acknowledgeRisk() }" style="margin-top:8px;border-radius:10px;background:rgba(220,38,38,.05);padding:9px 14px;">
                        <summary style="cursor:pointer;font-size:11.5px;color:var(--esa-muted);">Ver detalle: {{ $fila['titulo'] }}</summary>
                        <div style="margin-top:8px;font-size:12px;color:var(--esa-text);line-height:1.7;">
                            @foreach($fila['hallazgos'] as $h)
                                <div>› {{ $h }}</div>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        @endif
    </div>
</div>

<style>
.v6chk-item{border-left:3px solid;border-radius:.5rem;background:rgba(0,0,0,.02);}
html.dark .v6chk-item{background:rgba(255,255,255,.03);}
.v6chk-head{padding:8px 10px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;}
div.v6chk-head{cursor:default;}
.v6chk-item summary::-webkit-details-marker{display:none;}
.v6chk-chevron{transition:transform .15s ease;}
.v6chk-item[open] .v6chk-chevron{transform:rotate(90deg);}
.v6chk-body{padding:0 10px 9px 34px;}
.v6chk-li{font-size:12px;color:var(--esa-text);line-height:1.55;display:flex;gap:6px;margin:3px 0 0;}
.v6chk-spinner{flex-shrink:0;width:14px;height:14px;border-radius:50%;border:2px solid rgba(0,0,0,.12);border-top-color:#2563eb;animation:v6chkspin .8s linear infinite;}
html.dark .v6chk-spinner{border-color:rgba(255,255,255,.15);border-top-color:#60a5fa;}
@keyframes v6chkspin{to{transform:rotate(360deg);}}
</style>
@endif
