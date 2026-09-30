{{--
    Video didáctico de "segunda socialización" (pedido del equipo,
    2026-09-28) - se muestra solo si ya hay un video generado o si la
    generación anterior falló. Reusa .rit-viewer/.rit-viewer-label/.rit-btn
    ya establecidos en esta misma página, sin inventar clases nuevas.

    REDISEÑO 2026-09-30: cada tema/cambio genera un capítulo independiente
    (ver RitVideoDidacticoService) - la variable `$capitulos` reemplaza al
    antiguo archivo único. `ReglamentoInterno::capitulosVideoDidactico()`
    envuelve los videos generados ANTES de este cambio como un capítulo
    único, así que esta vista funciona igual para ambos casos.

    Variable esperada: $reglamento (App\Models\ReglamentoInterno)
--}}
@php
    $capitulos = $reglamento?->capitulosVideoDidactico() ?? [];
@endphp
@if(count($capitulos) > 0 || $reglamento?->tieneErrorVideoDidactico())
    <div class="rit-viewer" style="margin-top:1.25rem">
        <div class="rit-viewer-header">
            <span class="rit-viewer-label">Video didáctico del Reglamento</span>
            @if($reglamento->video_didactico_generado_en)
                <span style="font-size:.75rem;color:#64748b">
                    Generado {{ $reglamento->video_didactico_generado_en->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm A') }}
                </span>
            @endif
        </div>
        <div class="rit-viewer-body" style="padding:1.25rem 1.5rem">
            @if($reglamento->tieneErrorVideoDidactico())
                <p style="margin:0;font-size:.8125rem;color:#b91c1c">
                    No se pudo generar el video: {{ $reglamento->video_didactico_error }}
                </p>
            @elseif(count($capitulos) > 0)
                <div style="max-width:520px"
                    x-data="{
                        capitulos: @js(array_column($capitulos, 'titulo')),
                        actual: 0,
                        baseUrl: {{ \Illuminate\Support\Js::from(route('rit.video-didactico', ['reglamento' => $reglamento->id])) }},
                        cargar(indice) {
                            this.actual = indice;
                            this.$refs.video.src = this.baseUrl + '?capitulo=' + indice;
                            this.$refs.video.load();
                            this.$refs.video.play().catch(() => {});
                        },
                        siguiente() {
                            if (this.actual < this.capitulos.length - 1) { this.cargar(this.actual + 1); }
                        }
                    }"
                    x-init="$refs.video.src = baseUrl + '?capitulo=0'">
                    <video x-ref="video" controls preload="metadata" x-on:ended="siguiente()" style="width:100%;border-radius:.75rem;display:block"></video>

                    @if(count($capitulos) > 1)
                        <div style="margin-top:.75rem;display:flex;flex-direction:column;gap:.25rem;max-height:200px;overflow-y:auto">
                            <template x-for="(cap, i) in capitulos" :key="i">
                                <button type="button" x-on:click="cargar(i)"
                                    style="text-align:left;font-size:.8125rem;padding:.4rem .6rem;border-radius:.5rem;border:none;cursor:pointer;background:transparent;color:#64748b"
                                    x-bind:style="actual === i ? 'background:rgba(251,113,133,.13);color:#fb7185;font-weight:600' : ''"
                                    x-text="(i + 1) + '. ' + cap"></button>
                            </template>
                        </div>
                    @endif

                    <div style="margin-top:.75rem">
                        <a x-bind:href="baseUrl + '?capitulo=' + actual" download class="rit-btn rit-btn-secondary">
                            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            <span x-text="'Descargar capítulo ' + (actual + 1)"></span>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
