{{--
    Trazabilidad completa del "equivalente funcional": cada fila es un momento en
    que alguien aceptó o autorizó algo (sanción, descargos, RIT...) con su
    evidencia (selfie, IP, dispositivo, consentimiento, huella). Espera
    $datos = EquivalenteFuncional::eventos(). Requiere documento-viewer-styles
    y lupe-hero-styles incluidos en la página.
--}}
@verbatim
<style>
.ef-filtros{display:flex;flex-wrap:wrap;gap:.65rem;align-items:flex-end;margin:0 0 1.1rem}
.ef-campo{display:flex;flex-direction:column;gap:.25rem}
.ef-campo label{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b}
.ef-input{font-size:.85rem;padding:.5rem .75rem;border-radius:.65rem;border:1px solid rgba(0,0,0,.14);background:transparent;color:inherit;min-width:9rem}
html.dark .ef-input{border-color:rgba(255,255,255,.16)}
.ef-input:focus{outline:none;border-color:rgba(225,29,72,.5);box-shadow:0 0 0 3px rgba(225,29,72,.12)}
.ef-check{display:flex;align-items:center;gap:.45rem;font-size:.85rem;padding-bottom:.55rem;cursor:pointer}
.ef-scroll{overflow-x:auto}
.ef-tabla{width:100%;border-collapse:collapse;font-size:.85rem}
.ef-tabla th{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#64748b;text-align:left;padding:.55rem .75rem;border-bottom:1px solid rgba(0,0,0,.08)}
html.dark .ef-tabla th{border-bottom-color:rgba(255,255,255,.1)}
.ef-tabla td{padding:.85rem .75rem;vertical-align:top;border-bottom:1px solid rgba(0,0,0,.06)}
html.dark .ef-tabla td{border-bottom-color:rgba(255,255,255,.07)}
.ef-tabla tr:last-child td{border-bottom:0}
.ef-fecha{white-space:nowrap;font-weight:600}
.ef-sub{display:block;font-size:.75rem;color:#78716c;margin-top:.15rem}
html.dark .ef-sub{color:#94a3b8}
.ef-nombre{font-weight:700}
.ef-enlace{background:none;border:0;padding:0;font:inherit;color:#be123c;cursor:pointer;text-align:left;text-decoration:underline;text-underline-offset:2px}
html.dark .ef-enlace{color:#fda4af}
.ef-selfie{width:46px;height:46px;border-radius:.6rem;object-fit:cover;border:1px solid rgba(0,0,0,.12);cursor:zoom-in;display:block}
.ef-sin-selfie{font-size:.75rem;color:#a8a29e}
.ef-ip{font-family:ui-monospace,monospace;font-size:.78rem}
.ef-detalle summary{cursor:pointer;font-size:.78rem;color:#be123c;font-weight:600}
html.dark .ef-detalle summary{color:#fda4af}
.ef-detalle dl{margin:.5rem 0 0;font-size:.78rem;display:grid;grid-template-columns:auto 1fr;gap:.2rem .75rem;max-width:26rem}
.ef-detalle dt{color:#78716c}
html.dark .ef-detalle dt{color:#94a3b8}
.ef-detalle dd{margin:0;word-break:break-word}
.ef-acciones{display:flex;flex-direction:column;gap:.35rem;white-space:nowrap}
.ef-pie{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1rem}
.ef-vacio{text-align:center;padding:2.5rem 1rem;color:#78716c;font-size:.9rem}
.ef-modal{position:fixed;inset:0;z-index:70;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.7);padding:1.5rem}
.ef-modal img{max-width:min(92vw,720px);max-height:86vh;border-radius:.9rem}
</style>
@endverbatim

<div class="rit-viewer" style="margin-top:0">
    <div class="rit-viewer-header" style="gap:.5rem">
        <span class="rit-viewer-label">Trazabilidad de aceptaciones y autorizaciones</span>
        <span class="rit-badge rit-badge-ia">{{ $datos['total'] }}</span>
    </div>

    <div class="rit-viewer-body" style="max-height:none">
        <div class="ef-filtros">
            <div class="ef-campo" style="flex:1 1 16rem">
                <label for="ef-buscar">Buscar</label>
                <input id="ef-buscar" type="search" class="ef-input" style="width:100%"
                    wire:model.live.debounce.400ms="buscar"
                    placeholder="Persona, documento, proceso o dirección IP">
            </div>

            <div class="ef-campo">
                <label for="ef-tipo">Tipo de evento</label>
                <select id="ef-tipo" class="ef-input" wire:model.live="tipoEvento">
                    <option value="todos">Todos</option>
                    @foreach($datos['tipos'] as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ef-campo">
                <label for="ef-desde">Desde</label>
                <input id="ef-desde" type="date" class="ef-input" wire:model.live="desde">
            </div>

            <div class="ef-campo">
                <label for="ef-hasta">Hasta</label>
                <input id="ef-hasta" type="date" class="ef-input" wire:model.live="hasta">
            </div>

            <label class="ef-check">
                <input type="checkbox" wire:model.live="soloSelfie"> Solo con selfie
            </label>

            @if($buscar !== '' || $tipoEvento !== 'todos' || $desde || $hasta || $soloSelfie)
                <button type="button" class="rit-btn rit-btn-secondary" wire:click="limpiarFiltros">Limpiar filtros</button>
            @endif
        </div>

        @if($datos['items']->isEmpty())
            <p class="ef-vacio">Aún no hay aceptaciones ni autorizaciones registradas con estos filtros.</p>
        @else
            <div class="ef-scroll">
                <table class="ef-tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Evento</th>
                            <th>Proceso</th>
                            <th>Quién</th>
                            <th>Evidencia</th>
                            <th>Detalle</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($datos['items'] as $evento)
                            <tr wire:key="ef-{{ $evento['tipo'] }}-{{ $evento['id'] }}">
                                <td class="ef-fecha">
                                    {{ $evento['fecha']?->format('d/m/Y') ?? '-' }}
                                    <span class="ef-sub">{{ $evento['fecha']?->format('H:i') }}</span>
                                </td>

                                <td>
                                    <span class="rit-badge rit-badge-ia" style="text-transform:none;letter-spacing:0;font-size:.72rem">{{ $evento['tipo_etiqueta'] }}</span>
                                </td>

                                <td>
                                    <button type="button" class="ef-enlace" wire:click="filtrarPorProceso(@js($evento['proceso_clave']))"
                                        title="Ver todos los eventos de este proceso">{{ $evento['proceso'] }}</button>
                                    @if($evento['empresa'])
                                        <span class="ef-sub">{{ $evento['empresa'] }}</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="ef-nombre">{{ $evento['actor'] ?: 'Sin registro' }}</span>
                                    <span class="ef-sub">{{ $evento['rol'] }}@if($evento['actor_detalle']) · {{ $evento['actor_detalle'] }}@endif</span>
                                    @if($evento['sujeto'] && $evento['sujeto'] !== $evento['actor'])
                                        <span class="ef-sub">Trabajador: {{ $evento['sujeto'] }}</span>
                                    @endif
                                </td>

                                <td>
                                    <div style="display:flex;gap:.75rem;align-items:flex-start">
                                        @if($evento['url_selfie'])
                                            <div x-data="{ abierto: false }">
                                                <img class="ef-selfie" src="{{ $evento['url_selfie'] }}" alt="Selfie de verificación" loading="lazy"
                                                    x-on:click="abierto = true">
                                                <div class="ef-modal" x-show="abierto" x-cloak style="display:none"
                                                    x-on:click="abierto = false" x-on:keydown.escape.window="abierto = false">
                                                    <img src="{{ $evento['url_selfie'] }}" alt="Selfie de verificación">
                                                </div>
                                            </div>
                                        @else
                                            <span class="ef-sin-selfie">Sin selfie</span>
                                        @endif

                                        <div>
                                            @if($evento['ip'])
                                                <span class="ef-ip">{{ $evento['ip'] }}</span>
                                            @else
                                                <span class="ef-sin-selfie">Sin IP</span>
                                            @endif
                                            @if($evento['dispositivo'])
                                                <span class="ef-sub" title="{{ $evento['dispositivo'] }}">{{ \Illuminate\Support\Str::limit($evento['dispositivo'], 34) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if(!empty($evento['detalle']))
                                        <details class="ef-detalle">
                                            <summary>Ver detalle</summary>
                                            <dl>
                                                @foreach($evento['detalle'] as $etiqueta => $valor)
                                                    <dt>{{ $etiqueta }}</dt>
                                                    <dd>{{ $valor }}</dd>
                                                @endforeach
                                            </dl>
                                        </details>
                                    @else
                                        <span class="ef-sin-selfie">-</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="ef-acciones">
                                        @if($evento['url_proceso'])
                                            <a class="ef-enlace" href="{{ $evento['url_proceso'] }}" target="_blank">Ver proceso</a>
                                        @endif
                                        @if($evento['url_acta'])
                                            <a class="ef-enlace" href="{{ $evento['url_acta'] }}" target="_blank">Acta PDF</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ef-pie">
                <span class="ef-sub" style="margin:0">Página {{ $datos['pagina'] }} de {{ $datos['paginas'] }}</span>
                <div style="display:flex;gap:.5rem">
                    <button type="button" class="rit-btn rit-btn-secondary" wire:click="irAPagina({{ $datos['pagina'] - 1 }})" @disabled($datos['pagina'] <= 1)>Anterior</button>
                    <button type="button" class="rit-btn rit-btn-secondary" wire:click="irAPagina({{ $datos['pagina'] + 1 }})" @disabled($datos['pagina'] >= $datos['paginas'])>Siguiente</button>
                </div>
            </div>
        @endif
    </div>
</div>
