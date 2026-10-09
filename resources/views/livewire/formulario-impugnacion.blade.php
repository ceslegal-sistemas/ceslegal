@php
    $tipoSancion = match ($proceso?->tipo_sancion) {
        'llamado_atencion' => 'Llamado de atención',
        'suspension' => 'Suspensión laboral' . ($proceso?->dias_suspension ? " de {$proceso->dias_suspension} día(s)" : ''),
        'multa' => 'Multa',
        'terminacion' => 'Terminación de contrato',
        default => 'Decisión disciplinaria',
    };
    $limiteTxt = $limite?->copy()->timezone('America/Bogota')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    $pasoActual = match ($etapa) { 'otp' => 1, 'formulario' => 2, 'confirmado' => 3, default => null };
@endphp

<div class="min-h-screen bg-gray-50 sm:bg-gray-100 sm:py-8 sm:px-4">
    @include('filament.components.lupe-hero-styles')
    <style>
        .imp-card { border: 1px solid rgba(225,29,72,.14); background: #fff7f8; border-radius: 1rem; padding: 1rem 1.15rem; }
        .imp-dato { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #be123c; margin: 0; }
        .imp-valor { font-size: .95rem; font-weight: 600; color: #1c1917; margin: .1rem 0 0; }
        .imp-chip { display: flex; align-items: flex-start; gap: .65rem; width: 100%; text-align: left; padding: .8rem .95rem; border-radius: .85rem;
            border: 1px solid rgba(0,0,0,.12); background: #fff; font-size: .875rem; color: #374151; cursor: pointer; transition: border-color .15s, background-color .15s; }
        .imp-chip:hover { border-color: rgba(225,29,72,.35); }
        .imp-chip-on { border-color: #e11d48; background: #fff1f2; color: #881337; font-weight: 600; }
        .imp-check { flex-shrink: 0; width: 1.15rem; height: 1.15rem; margin-top: .1rem; border-radius: .3rem; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; background: #fff; }
        .imp-chip-on .imp-check { background: #e11d48; border-color: #e11d48; }
        .imp-label { display: block; font-size: .8rem; font-weight: 700; color: #1c1917; margin-bottom: .4rem; }
        .imp-ayuda { font-size: .78rem; color: #6b7280; margin: .25rem 0 0; }
        .imp-error { font-size: .8rem; color: #be123c; margin: .35rem 0 0; }
        .imp-file { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem .85rem; border-radius: .7rem; background: #f9fafb; border: 1px solid #e5e7eb; font-size: .82rem; color: #374151; }
        .imp-drop { display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: 1.1rem; border: 2px dashed rgba(225,29,72,.3); border-radius: 1rem; background: #fff7f8; text-align: center; cursor: pointer; font-size: .85rem; color: #881337; }
        .imp-input { display: block; width: 100%; border: 1px solid #d1d5db; border-radius: .85rem; padding: .75rem .9rem; font-size: 16px; color: #111827; background: #fff; }
        .imp-input:focus { outline: none; border-color: #e11d48; box-shadow: 0 0 0 3px rgba(225,29,72,.15); }
        .imp-cta { width: 100%; justify-content: center; border: 0; cursor: pointer; }
        .imp-cta:disabled { opacity: .6; cursor: default; }
        .imp-btn-sec { width: 100%; justify-content: center; }
    </style>

    <div class="sm:max-w-xl lg:max-w-2xl sm:mx-auto">
        <div class="bg-white sm:rounded-2xl sm:shadow-lg sm:border sm:border-gray-200 min-h-screen sm:min-h-0 sm:overflow-hidden">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-10 sm:static sm:rounded-t-2xl">
                <div class="px-4 sm:px-6 py-4">
                    <h1 class="text-base font-semibold text-gray-900">Impugnar la decisión</h1>
                    <p class="text-xs text-gray-500">{{ $empresa?->razon_social ?? 'LUPE Legal' }}</p>
                </div>
                @if ($pasoActual)
                    <div class="px-4 sm:px-6 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: {{ ($pasoActual / 3) * 100 }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-600 tabular-nums">{{ $pasoActual }}/3</span>
                        </div>
                    </div>
                @endif
            </header>

            <main class="px-4 sm:px-6 py-6 space-y-5">

                {{-- ═════════ Enlace no válido ═════════ --}}
                @if ($etapa === 'no_disponible')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-none">Enlace no válido</span>
                            <h1 class="rit-title">No encontramos esta impugnación</h1>
                            <p class="rit-sub">El enlace está incompleto o ya no existe. Use el botón del correo que recibió con la notificación, o comuníquese con el área de Recursos Humanos de su empresa.</p>
                        </div>
                    </div>

                {{-- ═════════ Ya presentada ═════════ --}}
                @elseif ($etapa === 'ya_impugnada')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#16a34a,secondary:#16a34a" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Ya presentada
                            </span>
                            <h1 class="rit-title">Su impugnación ya fue recibida</h1>
                            <p class="rit-sub">
                                Radicado <strong>{{ $impugnacion?->radicado() }}</strong>
                                @if ($impugnacion?->fecha_impugnacion) · {{ $impugnacion->fecha_impugnacion->timezone('America/Bogota')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a') }} @endif.
                                Su empresa la analizará y recibirá la decisión final por correo electrónico.
                            </p>
                        </div>
                    </div>

                {{-- ═════════ Plazo vencido / proceso ya cerrado ═════════ --}}
                @elseif ($etapa === 'vencida')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-warning">Plazo finalizado</span>
                            <h1 class="rit-title">Ya no es posible presentar la impugnación</h1>
                            <p class="rit-sub">
                                @if ($limiteTxt) El plazo para impugnar terminó el <strong>{{ $limiteTxt }}</strong>. @else El plazo para impugnar esta decisión ya terminó o el proceso fue cerrado. @endif
                                Si cree que se trata de un error, comuníquese con el área de Recursos Humanos de {{ $empresa?->razon_social ?? 'su empresa' }}.
                            </p>
                        </div>
                    </div>

                {{-- ═════════ Constancia ═════════ --}}
                @elseif ($etapa === 'confirmado')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#16a34a,secondary:#16a34a" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Impugnación recibida
                            </span>
                            <h1 class="rit-title">Listo, recibimos su impugnación</h1>
                            <p class="rit-sub">Quedó radicada con el número <strong>{{ $radicado }}</strong>. @if ($emailEnmascarado) Le enviamos la constancia a <strong>{{ $emailEnmascarado }}</strong>. @endif</p>
                        </div>
                    </div>

                    <div class="imp-card">
                        <p class="imp-dato">Qué sigue</p>
                        <p class="imp-valor" style="font-weight:500;font-size:.9rem;line-height:1.55;">
                            Su empresa analizará su impugnación y recibirá la decisión final por correo electrónico. No necesita hacer nada más; puede cerrar esta página.
                        </p>
                    </div>

                {{-- ═════════ Paso 1 y 2 ═════════ --}}
                @else
                    {{-- Resumen de la decisión + plazo (visible en ambos pasos) --}}
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-ia">{{ $etapa === 'otp' ? 'Paso 1 · Verifique su identidad' : 'Paso 2 · Cuéntenos sus motivos' }}</span>
                            <h1 class="rit-title">
                                {{ $etapa === 'otp' ? 'Presente su impugnación de forma segura' : 'Explique por qué no está de acuerdo' }}
                            </h1>
                            <p class="rit-sub">
                                {{ $trabajador?->nombre_completo }}, usted tiene derecho a impugnar la decisión tomada en el proceso
                                <strong>{{ $proceso?->codigo }}</strong>. @if ($limiteTxt) Puede hacerlo hasta el <strong>{{ $limiteTxt }}</strong>. @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="imp-card"><p class="imp-dato">Decisión</p><p class="imp-valor">{{ $tipoSancion }}</p></div>
                        <div class="imp-card"><p class="imp-dato">Notificada</p><p class="imp-valor">{{ $proceso?->fecha_notificacion?->timezone('America/Bogota')->locale('es')->isoFormat('D MMM YYYY') ?? '—' }}</p></div>
                    </div>

                    {{-- ───── Paso 1: código al correo ───── --}}
                    @if ($etapa === 'otp')
                        <div class="space-y-4">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 mb-1">Verificación de identidad</h2>
                                <p class="text-sm text-gray-500">Para proteger su derecho, le enviaremos un código de 6 dígitos al correo registrado en su empresa.</p>
                            </div>

                            @if ($otpError === 'sin_email')
                                <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 text-sm text-danger-800">
                                    <p class="font-semibold">No hay un correo registrado</p>
                                    <p class="mt-1">No podemos enviar el código porque su empresa no tiene un correo electrónico suyo. Comuníquese con Recursos Humanos para actualizarlo; su plazo para impugnar sigue corriendo.</p>
                                </div>
                            @else
                                @if (!$otpEnviado)
                                    <button wire:click="enviarOtp" wire:loading.attr="disabled" wire:target="enviarOtp" type="button" class="rit-btn rit-btn-cta imp-cta">
                                        <span wire:loading.remove wire:target="enviarOtp">Enviar código a {{ $emailEnmascarado ?? 'mi correo' }}</span>
                                        <span wire:loading wire:target="enviarOtp">Enviando...</span>
                                    </button>
                                @else
                                    <div class="bg-success-50 border border-success-200 rounded-xl p-3 text-sm text-success-800">
                                        Enviamos el código a <strong>{{ $emailEnmascarado }}</strong>. Revise también la carpeta de spam.
                                    </div>

                                    <div>
                                        <label class="imp-label">Ingrese el código de 6 dígitos</label>
                                        <input wire:model="otpCodigo" wire:keydown.enter="verificarOtp" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                                            autocomplete="one-time-code" placeholder="000000" autofocus
                                            class="imp-input" style="text-align:center;font-family:monospace;letter-spacing:.5em;font-size:24px !important;">
                                        @if ($otpError === 'incorrecto') <p class="imp-error">Código incorrecto. Revise e intente de nuevo.</p>
                                        @elseif ($otpError === 'expirado') <p class="imp-error">El código venció. Solicite uno nuevo.</p>
                                        @elseif ($otpError === 'bloqueado') <p class="imp-error">Superó el número de intentos. Por seguridad podrá pedir un código nuevo en 15 minutos.</p>
                                        @elseif ($otpError === 'fallo_envio') <p class="imp-error">No pudimos enviar el código. Intente de nuevo en un momento.</p>
                                        @endif
                                    </div>

                                    <button wire:click="verificarOtp" wire:loading.attr="disabled" wire:target="verificarOtp" type="button"
                                        class="rit-btn rit-btn-cta imp-cta">
                                        <span wire:loading.remove wire:target="verificarOtp">Verificar y continuar</span>
                                        <span wire:loading wire:target="verificarOtp">Verificando...</span>
                                    </button>

                                    <div class="text-center">
                                        <button wire:click="enviarOtp" wire:loading.attr="disabled" type="button" class="text-sm text-gray-500 underline">
                                            ¿No le llegó? Enviar un código nuevo
                                        </button>
                                    </div>
                                @endif
                                @if ($otpError === 'bloqueado' && !$otpEnviado)
                                    <p class="imp-error">Por seguridad, espere 15 minutos antes de pedir un código nuevo.</p>
                                @endif
                                @if ($otpError === 'fallo_envio' && !$otpEnviado)
                                    <p class="imp-error">No pudimos enviar el código. Intente de nuevo en un momento.</p>
                                @endif
                            @endif
                        </div>

                    {{-- ───── Paso 2: motivos y pruebas ───── --}}
                    @else
                        <form wire:submit.prevent="presentar" class="space-y-6">
                            <div>
                                <span class="imp-label">¿Por qué no está de acuerdo? <span style="font-weight:400;color:#6b7280">(marque lo que aplique)</span></span>
                                <div class="space-y-2">
                                    @foreach ($motivosDisponibles as $clave => $texto)
                                        @php $activo = in_array($clave, $motivos, true); @endphp
                                        <button type="button"
                                            wire:click="alternarMotivo('{{ $clave }}')"
                                            class="imp-chip {{ $activo ? 'imp-chip-on' : '' }}" aria-pressed="{{ $activo ? 'true' : 'false' }}">
                                            <span class="imp-check">
                                                @if ($activo)
                                                    <svg width="12" height="12" viewBox="0 0 20 20" fill="#fff"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                                @endif
                                            </span>
                                            <span>{{ $texto }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label for="imp-detalle" class="imp-label">Cuéntenos con sus palabras</label>
                                <textarea id="imp-detalle" wire:model="detalle" rows="6" maxlength="5000" class="imp-input"
                                    placeholder="Explique qué pasó, por qué considera que la decisión no es correcta y qué solicita."></textarea>
                                <p class="imp-ayuda">Mientras más claro sea, mejor podremos analizar su caso.</p>
                                @error('detalle') <p class="imp-error">{{ $message }}</p> @enderror
                            </div>

                            <div x-data="{ subiendo: false, progreso: 0 }"
                                 x-on:livewire-upload-start="subiendo = true"
                                 x-on:livewire-upload-finish="subiendo = false"
                                 x-on:livewire-upload-error="subiendo = false"
                                 x-on:livewire-upload-progress="progreso = $event.detail.progress">
                                <span class="imp-label">Pruebas <span style="font-weight:400;color:#6b7280">(opcional)</span></span>

                                <label class="imp-drop">
                                    <lord-icon src="https://cdn.lordicon.com/ternnbni.json" trigger="hover" colors="primary:#e11d48,secondary:#fb923c" style="width:34px;height:34px"></lord-icon>
                                    <span><strong>Toque para adjuntar</strong> documentos, fotos, audios o videos</span>
                                    <span style="font-size:.72rem;color:#9f1239;">PDF, imágenes, Word, Excel, audio o video · hasta 5 archivos de 10 MB</span>
                                    <input type="file" wire:model="archivos" multiple style="display:none"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.mp4,.mov,.mp3,.wav,.m4a">
                                </label>

                                <div x-show="subiendo" style="display:none" class="mt-2">
                                    <div class="bg-gray-200 rounded-full h-2"><div class="bg-primary-600 h-2 rounded-full" :style="'width:' + progreso + '%'"></div></div>
                                    <p class="imp-ayuda">Subiendo archivo...</p>
                                </div>

                                @if (!empty($archivos))
                                    <div class="space-y-2 mt-3">
                                        @foreach ($archivos as $i => $archivo)
                                            <div class="imp-file">
                                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $archivo->getClientOriginalName() }}</span>
                                                <button type="button" wire:click="quitarArchivo({{ $i }})" style="color:#be123c;font-weight:600;flex-shrink:0;">Quitar</button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('archivos') <p class="imp-error">{{ $message }}</p> @enderror
                                @error('archivos.*') <p class="imp-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="imp-card">
                                <label class="flex items-start gap-3 text-sm text-gray-700" style="cursor:pointer;">
                                    <input type="checkbox" wire:model="declaracion" class="mt-1 shrink-0" style="width:1.1rem;height:1.1rem;accent-color:#e11d48;">
                                    <span>
                                        Declaro que soy <strong>{{ $trabajador?->nombre_completo }}</strong>, que la información que presento es verdadera
                                        y que autorizo que quede registrada junto con la fecha, la hora y mi dirección IP como constancia de esta impugnación.
                                    </span>
                                </label>
                                @error('declaracion') <p class="imp-error">{{ $message }}</p> @enderror
                            </div>

                            @if ($errorEnvio)
                                <div class="bg-danger-50 border border-danger-200 rounded-xl p-3 text-sm text-danger-800">{{ $errorEnvio }}</div>
                            @endif

                            <button type="submit" wire:loading.attr="disabled" wire:target="presentar,archivos" class="rit-btn rit-btn-cta imp-cta">
                                <span wire:loading.remove wire:target="presentar">Presentar mi impugnación</span>
                                <span wire:loading wire:target="presentar">Enviando...</span>
                            </button>
                            <p class="imp-ayuda text-center">Al presentarla le enviaremos una constancia a su correo.</p>
                        </form>
                    @endif
                @endif
            </main>
        </div>
    </div>
</div>
