@php
    $pasos = $fase === 'publicacion'
        ? ['documento' => 1, 'datos' => 2, 'presentacion_rit' => 3, 'aceptacion' => 4]
        : ['documento' => 1, 'datos' => 2, 'foto' => 3, 'presentacion_rit' => 4, 'quiz' => 5, 'foto_aceptacion' => 6, 'aceptacion' => 7];
    $pasoActual = $pasos[$etapa] ?? null;
@endphp

<div class="min-h-screen bg-gray-50 sm:bg-gray-100 sm:py-8 sm:px-4">
    @include('filament.components.lupe-hero-styles')
    <div class="sm:max-w-xl lg:max-w-3xl sm:mx-auto">
        <div class="bg-white sm:rounded-2xl sm:shadow-lg sm:border sm:border-gray-200 min-h-screen sm:min-h-0 sm:overflow-hidden">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-10 sm:static sm:rounded-t-2xl">
                <div class="px-4 sm:px-6 py-4">
                    <h1 class="text-base font-semibold text-gray-900">Reglamento Interno de Trabajo</h1>
                    <p class="text-xs text-gray-500">{{ $empresa->razon_social }}</p>
                </div>
                @if ($pasoActual)
                    <div class="px-4 sm:px-6 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300"
                                    style="width: {{ ($pasoActual / count($pasos)) * 100 }}%">
                                </div>
                            </div>
                            <span class="text-xs font-medium text-gray-600 tabular-nums">{{ $pasoActual }}/{{ count($pasos) }}</span>
                        </div>
                    </div>
                @endif
            </header>

            <main class="px-4 sm:px-6 py-6">
                @if ($etapa === 'sin_rit')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-none">Sin Reglamento</span>
                            <h1 class="rit-title">Esta empresa aún no tiene un Reglamento Interno para socializar</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'ya_acepto')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Ya registrado
                            </span>
                            <h1 class="rit-title">Ya aceptaste el Reglamento Interno vigente</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'ya_informado')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Ya confirmado
                            </span>
                            <h1 class="rit-title">Ya confirmaste la publicación del Reglamento</h1>
                            <p class="rit-sub">
                                Vuelve a este mismo link después del
                                {{ $this->resolverRitActivo()?->fechaLimiteObjecion()?->format('d/m/Y') }}
                                para completar la socialización.
                            </p>
                        </div>
                    </div>
                @elseif ($etapa === 'documento')
                    <div class="space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 mb-1">Identifícate</h2>
                            <p class="text-sm text-gray-500">Ingresa tu documento de identidad para comenzar.</p>
                        </div>

                        <div>
                            <select id="rit-tipo-documento" wire:model="tipoDocumento" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="TI">Tarjeta de Identidad</option>
                                <option value="PASS">Pasaporte</option>
                            </select>
                            @error('tipoDocumento') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            {{-- Pedido explícito del usuario (2026-09-22): el campo dejaba escribir
                                 letras y sin límite de longitud. Pasaporte SÍ puede ser alfanumérico,
                                 por eso el filtro solo aplica a CC/CE/TI. --}}
                            <input type="text" id="rit-numero-documento" wire:model="numeroDocumento" placeholder="Número de documento"
                                inputmode="numeric" maxlength="15" autocomplete="off"
                                oninput="var t=document.getElementById('rit-tipo-documento'); if (t && t.value !== 'PASS') { this.value = this.value.replace(/[^0-9]/g, ''); }"
                                class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                            @error('numeroDocumento') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            {{-- Pedido explícito del usuario (2026-09-22): confirmar el documento
                                 escribiéndolo de nuevo (sin pegar) reduce el riesgo de un typo que
                                 mande la foto biométrica de referencia al registro equivocado. --}}
                            <input type="text" wire:model="numeroDocumentoConfirmacion" placeholder="Confirma tu número de documento"
                                inputmode="numeric" maxlength="15" autocomplete="off" onpaste="return false"
                                oninput="var t=document.getElementById('rit-tipo-documento'); if (t && t.value !== 'PASS') { this.value = this.value.replace(/[^0-9]/g, ''); }"
                                class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                            @error('numeroDocumentoConfirmacion') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <button wire:click="buscarTrabajador" wire:loading.attr="disabled" wire:target="buscarTrabajador" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg wire:loading wire:target="buscarTrabajador" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span wire:loading.remove wire:target="buscarTrabajador">Continuar</span>
                            <span wire:loading wire:target="buscarTrabajador">Buscando...</span>
                        </button>
                    </div>
                @elseif ($etapa === 'datos')
                    <div class="space-y-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 mb-1">Tus datos</h2>
                            <p class="text-sm text-gray-500">Completa la información que falte para continuar.</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <input type="text" wire:model="nombres" placeholder="Nombres" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('nombres') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="apellidos" placeholder="Apellidos" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('apellidos') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <select wire:model="genero" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                    <option value="">Género</option>
                                    <option value="masculino">Masculino</option>
                                    <option value="femenino">Femenino</option>
                                    <option value="otro">Otro</option>
                                </select>
                                @error('genero') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                {{-- Mismo listado que usa Solicitud de Contrato: cargos reales
                                     del organigrama del RIT, con "Otro" para personalizar. --}}
                                <select wire:model.live="cargo" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                    <option value="">Selecciona tu cargo</option>
                                    @foreach($cargosDisponibles as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                    <option value="__otro__">--- Otro (personalizado) ---</option>
                                </select>
                                @error('cargo') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            @if($cargo === '__otro__')
                                <div>
                                    <input type="text" wire:model="cargoPersonalizado" placeholder="Escribe tu cargo" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                    @error('cargoPersonalizado') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <input type="email" wire:model="email" placeholder="Correo electrónico (opcional)" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('email') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                {{-- Pedido explícito del usuario (2026-09-22): confirmar el correo
                                     escribiéndolo de nuevo (sin pegar), a este correo llega el
                                     comprobante de aceptación del Reglamento. --}}
                                <input type="email" wire:model="emailConfirmacion" placeholder="Confirma tu correo electrónico (opcional)"
                                    onpaste="return false"
                                    class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('emailConfirmacion') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="telefono" placeholder="Teléfono (opcional)" class="w-full text-base border border-gray-300 rounded-xl px-3 py-2.5 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none">
                                @error('telefono') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <button wire:click="guardarDatos" wire:loading.attr="disabled" wire:target="guardarDatos" type="button"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            <svg wire:loading wire:target="guardarDatos" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <span wire:loading.remove wire:target="guardarDatos">Continuar</span>
                            <span wire:loading wire:target="guardarDatos">Guardando...</span>
                        </button>
                    </div>
                @elseif ($etapa === 'foto')
                    @include('livewire.partials.foto-simple-captura')
                @elseif ($etapa === 'presentacion_rit')
                    {{-- x-data vive en ESTE wrapper (no en el div del video mas abajo) a
                         proposito: el boton "Continuar" necesita leer "todosVistos" para
                         bloquearse, y esta fuera del div del video - Alpine no comparte
                         estado entre x-data hermanos, solo entre padre/hijo. --}}
                    @php
                        // En Fase 1 (publicación) el bloque de video ni siquiera se
                        // renderiza (ver @if($fase === 'socializacion') más abajo) - si
                        // esto se calculara igual iria sin token (vacio en Fase 1,
                        // ver $pasos/$token arriba) y route() revienta con "Missing
                        // parameter". Tambien evita que "Continuar" se bloquee
                        // esperando ver un video que el trabajador nunca llega a ver.
                        $videoBaseUrl = $fase === 'socializacion' ? route('rit.socializar.video', ['token' => $token]) : '';
                    @endphp
                    <div class="space-y-4"
                        x-data="{
                            capitulos: @js($fase === 'socializacion' ? $capitulosVideoDidactico : []),
                            actual: 0,
                            vistos: @js($fase === 'socializacion' ? array_fill(0, count($capitulosVideoDidactico), false) : []),
                            maxTiempo: {},
                            baseUrl: {{ \Illuminate\Support\Js::from($videoBaseUrl) }},
                            get todosVistos() { return this.capitulos.length === 0 || this.vistos.every(v => v); },
                            cargar(indice) {
                                this.actual = indice;
                                this.$refs.video.src = this.baseUrl + '?capitulo=' + indice;
                                this.$refs.video.load();
                                this.$refs.video.play().catch(() => {});
                            },
                            siguiente() {
                                this.vistos[this.actual] = true;
                                if (this.actual < this.capitulos.length - 1) { this.cargar(this.actual + 1); }
                            },
                            actualizarProgreso() {
                                const v = this.$refs.video;
                                this.maxTiempo[this.actual] = Math.max(this.maxTiempo[this.actual] || 0, v.currentTime);
                            },
                            evitarAdelantar() {
                                // Pedido explícito de Andrés Sarmiento (reunión 2026-10-03):
                                // el trabajador NO puede adelantar el video arrastrando la
                                // barra - si intenta saltar más allá de lo que ya vio, se
                                // regresa al punto máximo alcanzado. Retroceder sí se permite
                                // (puede repasar), solo se bloquea adelantar.
                                const v = this.$refs.video;
                                const max = this.maxTiempo[this.actual] || 0;
                                if (v.currentTime > max + 0.5) { v.currentTime = max; }
                            }
                        }"
                        x-init="if (capitulos.length > 0) { $refs.video.src = baseUrl + '?capitulo=0' }">
                        @if ($esPrimeraAceptacion)
                            <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                                <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                                <div style="position:relative;z-index:2">
                                    <span class="rit-badge rit-badge-ia">Reglamento Interno</span>
                                    <h1 class="rit-title">Conoce el Reglamento Interno de {{ $empresa->razon_social }}</h1>
                                    <p class="rit-sub">Esto es lo que regula, explicado sencillo.</p>
                                </div>
                            </div>

                            {{-- Legal Design: nadie lee un reglamento completo desde el celular -
                                 se muestran los temas que cubre en lenguaje simple (taxonomía ya
                                 clasificada, sin IA nueva), el texto completo queda disponible
                                 aparte para quien de verdad quiera leerlo entero. --}}
                            @if(count($temasRit) > 0)
                                <div class="space-y-2.5 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-3">
                                    @foreach($temasRit as $tema)
                                        <div class="flex items-start gap-3 p-3.5 bg-gray-50 rounded-xl">
                                            <div class="w-2 h-2 rounded-full bg-primary-500 mt-1.5 flex-shrink-0"></div>
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 m-0">{{ $tema['nombre'] }}</p>
                                                <p class="text-xs text-gray-500 m-0 mt-0.5">{{ $tema['descripcion'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <details class="text-sm">
                                <summary class="cursor-pointer text-primary-600 font-medium">Ver el Reglamento completo</summary>
                                <div class="prose max-w-none text-sm border border-gray-200 rounded-xl p-4 mt-2 max-h-96 overflow-y-auto">
                                    {!! preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<strong>$1</strong>', nl2br(e($ritActivoTextoCompleto))) !!}
                                </div>
                            </details>
                        @else
                            <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                                <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                                <div style="position:relative;z-index:2">
                                    <span class="rit-badge rit-badge-warning">Actualización</span>
                                    <h1 class="rit-title">Esto cambió en el Reglamento Interno</h1>
                                </div>
                            </div>
                            @include('filament.components.rit-redline', ['cambios' => $cambiosRit])
                            {{-- Pedido explicito del spec (seccion 6): opcion de ver el
                                 texto completo, no solo el diff, si el trabajador lo prefiere. --}}
                            <details class="text-sm">
                                <summary class="cursor-pointer text-primary-600 font-medium">Ver el Reglamento completo</summary>
                                <div class="prose max-w-none border border-gray-200 rounded-xl p-4 mt-2 max-h-96 overflow-y-auto">
                                    {!! preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<strong>$1</strong>', nl2br(e($ritActivoTextoCompleto))) !!}
                                </div>
                            </details>
                        @endif

                        @if($fase === 'socializacion')
                        {{-- Botón de descarga real del RIT (pedido de Andrés Sarmiento en la
                             reunión, 2026-09-29: el "Ver el Reglamento completo" expandible no
                             es suficiente, el trabajador debe poder bajarlo). Ruta pública
                             propia (rit.socializar.descargar) autorizada por el mismo token de
                             esta página, nunca por sesión - mismo patrón que el video. Exclusivo
                             de Fase 2 (Socialización) - en Fase 1 (Publicación) no aplica. --}}
                        <a href="{{ route('rit.socializar.descargar', ['token' => $token]) }}" class="rit-btn rit-btn-secondary" style="width:100%;justify-content:center">
                            <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Descargar Reglamento Interno
                        </a>

                        {{-- Video didáctico "segunda socialización" (pedido del equipo,
                             2026-09-28) - generado UNA sola vez por el admin desde "Mi
                             Reglamento Interno" (App\Services\RitVideoDidacticoService),
                             nunca por trabajador. Se sirve por una ruta pública propia
                             (rit.socializar.video) autorizada por el mismo token de esta
                             página, nunca por sesión. Opcional: si el admin no lo generó
                             todavía, simplemente no aparece.

                             REDISEÑO 2026-09-30: el video es una serie de capítulos
                             independientes (uno por tema/cambio, sin tope artificial -
                             ver RitVideoDidacticoService), no un solo archivo. Reproducción
                             automática en secuencia (pedido explícito del usuario): al
                             terminar un capítulo, pasa solo al siguiente. Lista de
                             capítulos abajo para saltar a uno específico. --}}
                        @if(count($capitulosVideoDidactico) > 0)
                            <div class="rounded-xl overflow-hidden border border-gray-200 bg-black">
                                <video x-ref="video" controls controlsList="nofullscreen noremoteplayback" preload="metadata"
                                    x-on:ended="siguiente()" x-on:timeupdate="actualizarProgreso()" x-on:seeking="evitarAdelantar()"
                                    style="width:100%;display:block"></video>
                                @if(count($capitulosVideoDidactico) > 1)
                                    <div style="padding:.75rem;background:#fff">
                                        <p style="font-size:.7rem;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.05em;margin:0 0 .5rem">Capítulos</p>
                                        <div style="display:flex;flex-direction:column;gap:.25rem;max-height:180px;overflow-y:auto">
                                            <template x-for="(cap, i) in capitulos" :key="i">
                                                <button type="button" x-on:click="cargar(i)"
                                                    style="text-align:left;font-size:.8125rem;padding:.4rem .6rem;border-radius:.5rem;border:none;cursor:pointer;background:transparent;display:flex;align-items:center;gap:.35rem"
                                                    x-bind:style="actual === i ? 'background:#fecdd3;color:#be123c;font-weight:600' : 'color:#57534e'">
                                                    <span x-text="vistos[i] ? '✓' : (i + 1) + '.'"></span>
                                                    <span x-text="cap"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <p x-show="!todosVistos" x-cloak style="font-size:.75rem;color:#854d0e;background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.5rem .75rem;margin:0">
                                Debes ver completo cada video antes de poder continuar.
                            </p>
                        @endif
                        @endif

                        <button type="button" wire:click="iniciarQuiz" wire:loading.attr="disabled" wire:target="iniciarQuiz"
                            x-bind:disabled="!todosVistos"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                            Continuar
                        </button>
                    </div>
                @elseif ($etapa === 'quiz')
                    @php
                        $preguntaActual = $quizPreguntas[$quizIndiceActual] ?? null;
                    @endphp
                    <div class="space-y-4">
                        <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                            <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                            <div style="position:relative;z-index:2">
                                <span class="rit-badge rit-badge-ia">
                                    Pregunta {{ $quizIndiceActual + 1 }} de {{ count($quizPreguntas) }}
                                </span>
                                <h1 class="rit-title">¿Entendiste bien el Reglamento?</h1>
                                <p class="rit-sub">Confirma que leíste con atención respondiendo esta pregunta.</p>
                            </div>
                        </div>

                        @if ($preguntaActual)
                            <div class="p-4 rounded-xl border-2 border-gray-200 bg-gray-50">
                                <p class="text-sm font-semibold text-gray-900 m-0">{{ $preguntaActual['pregunta'] }}</p>
                            </div>

                            @if ($quizRespuestaIncorrecta)
                                <div class="p-3.5 rounded-xl bg-danger-50 border border-danger-200">
                                    <p class="text-xs font-semibold text-danger-700 m-0">Esa respuesta no es correcta.</p>
                                    <p class="text-xs text-danger-600 m-0 mt-1">{{ $preguntaActual['explicacion'] }}</p>
                                </div>
                            @endif

                            @if(($preguntaActual['tipo'] ?? 'vf') === 'multiple')
                                {{-- Selección múltiple (pedido de Andrés Sarmiento, 2026-10-03):
                                     4 opciones en columna, una sola correcta. --}}
                                <div class="space-y-2.5">
                                    @foreach($preguntaActual['opciones'] as $indiceOpcion => $opcion)
                                        <button type="button" wire:click="responderQuizMultiple({{ $indiceOpcion }})" wire:loading.attr="disabled" wire:target="responderQuizMultiple"
                                            class="w-full flex items-center gap-3 px-4 py-3 bg-white border-2 border-gray-200 hover:border-primary-400 active:bg-gray-50 text-gray-900 text-sm font-medium rounded-xl transition-colors text-left">
                                            <span class="flex-shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs font-bold text-gray-500">{{ chr(65 + $indiceOpcion) }}</span>
                                            {{ $opcion }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="grid grid-cols-2 gap-3">
                                    <button type="button" wire:click="responderQuiz(true)" wire:loading.attr="disabled" wire:target="responderQuiz"
                                        class="flex items-center justify-center gap-2 px-5 py-3.5 bg-white border-2 border-gray-200 hover:border-primary-400 active:bg-gray-50 text-gray-900 font-semibold rounded-xl transition-colors">
                                        Sí
                                    </button>
                                    <button type="button" wire:click="responderQuiz(false)" wire:loading.attr="disabled" wire:target="responderQuiz"
                                        class="flex items-center justify-center gap-2 px-5 py-3.5 bg-white border-2 border-gray-200 hover:border-primary-400 active:bg-gray-50 text-gray-900 font-semibold rounded-xl transition-colors">
                                        No
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                @elseif ($etapa === 'foto_aceptacion')
                    @include('livewire.partials.foto-simple-captura', [
                        'wireKeyFoto' => 'foto-aceptacion-captura',
                        'metodoValidarFoto' => 'validarFotoAceptacionConIA',
                        'propiedadErrorFoto' => 'errorValidacionFotoAceptacion',
                        'tituloFoto' => 'Una última foto para confirmar que eres tú',
                        'subtituloFoto' => 'Esta foto queda como evidencia de que fuiste tú quien aceptó el Reglamento hoy.',
                    ])
                @elseif ($etapa === 'aceptacion')
                    <div class="space-y-5">
                        <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                            <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                            <div style="position:relative;z-index:2">
                                <span class="rit-badge rit-badge-ia">Último paso</span>
                                @if($fase === 'publicacion')
                                    <h1 class="rit-title">Confirma que viste la publicación</h1>
                                    <p class="rit-sub">Con esto quedas registrado(a) como trabajador(a) de {{ $empresa->razon_social }} que fue informado(a) de la publicación del Reglamento Interno.</p>
                                @else
                                    <h1 class="rit-title">Confirma que entendiste el Reglamento</h1>
                                    <p class="rit-sub">Con esto quedas registrado(a) como trabajador(a) de {{ $empresa->razon_social }} que conoce el Reglamento Interno.</p>
                                @endif
                            </div>
                        </div>

                        <label class="flex items-start gap-3 p-4 rounded-xl border-2 border-gray-200 bg-gray-50 cursor-pointer">
                            <input type="checkbox" wire:model="declaracionAceptada" class="mt-0.5 w-5 h-5 rounded border border-gray-300 text-primary-600 focus:ring-2 focus:ring-primary-500 flex-shrink-0">
                            @php
                                // Centralizado 2026-10-03 (auditoría de disclaimers): antes
                                // este texto vivía hardcodeado aquí - ahora es editable desde
                                // ConfiguracionTextoResource igual que disclaimer_descargos.
                                // Marcador **negrita** = mismo convenio del texto del RIT.
                                $claveDisclaimerRit = $fase === 'publicacion' ? 'disclaimer_rit_publicacion' : 'disclaimer_rit_socializacion';
                                $textoDeclaracion = str_replace(
                                    ':empresa',
                                    $empresa->razon_social,
                                    \App\Models\ConfiguracionTexto::obtener($claveDisclaimerRit, config("ces.{$claveDisclaimerRit}", ''))
                                );
                            @endphp
                            <span class="text-sm text-gray-700">
                                {!! preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<span class="font-semibold text-gray-900">$1</span>', e($textoDeclaracion)) !!}
                            </span>
                        </label>
                        @error('declaracionAceptada') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

                        {{-- Dos botones completos (no uno con wire:target interpolado) a
                             propósito: SocializacionRitParidadVisualDescargosTest busca el
                             string LITERAL wire:target="aceptarReglamento" en el archivo
                             fuente. --}}
                        @if($fase === 'publicacion')
                            <button wire:click="confirmarPublicacion" wire:loading.attr="disabled" wire:target="confirmarPublicacion" type="button"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                                <svg wire:loading wire:target="confirmarPublicacion" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span wire:loading.remove wire:target="confirmarPublicacion">Aceptar</span>
                                <span wire:loading wire:target="confirmarPublicacion">Guardando...</span>
                            </button>
                        @else
                            <button wire:click="aceptarReglamento" wire:loading.attr="disabled" wire:target="aceptarReglamento" type="button"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold rounded-xl shadow-sm transition-colors">
                                <svg wire:loading wire:target="aceptarReglamento" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span wire:loading.remove wire:target="aceptarReglamento">Aceptar</span>
                                <span wire:loading wire:target="aceptarReglamento">Guardando...</span>
                            </button>
                        @endif
                    </div>
                @elseif ($etapa === 'completado')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Registro exitoso
                            </span>
                            <h1 class="rit-title">¡Gracias!</h1>
                            <p class="rit-sub">Tu registro y aceptación del Reglamento Interno quedaron guardados.</p>
                        </div>
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- Loading: mismo patron que formulario-descargos.blade.php --}}
    <div wire:loading.delay wire:target="buscarTrabajador, guardarDatos, iniciarQuiz, guardarFotoSimple, responderQuiz, responderQuizMultiple, validarFotoAceptacionConIA, aceptarReglamento, confirmarPublicacion"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl p-5 flex items-center gap-4 mx-4">
            <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="font-medium text-gray-700">Procesando...</span>
        </div>
    </div>
</div>
