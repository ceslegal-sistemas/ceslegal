{{--
    Verificación fotográfica del TRABAJADOR en la diligencia de descargos.

    Antes existían dos copias casi idénticas de este bloque (~330 líneas cada
    una) dentro de formulario-descargos.blade.php, una para la foto de inicio y
    otra para la de fin: solo cambiaban los textos, el id de la máscara del
    óvalo y el argumento 'inicio'/'fin'. Cualquier mejora había que aplicarla
    dos veces - por eso se unifican aquí.

    Incluye, por pedido explícito del usuario (2026-08-30):
      - Autorización de tratamiento de datos personales (Ley 1581 de 2012)
        OBLIGATORIA antes de CADA selfie: sin aceptar no se enciende la cámara,
        y sin selfie no se puede continuar el proceso.
      - Prueba de vida por parpadeo, con captura automática en ese instante
        (mismo criterio que webcam-autorizador.blade.php).

    Parámetros:
      $fase            'inicio' | 'fin'  (define el argumento de validarFotoConIA)
      $titulo          encabezado de la etapa
      $subtitulo       texto de apoyo
      $textoConfirmar  etiqueta del botón de confirmación
      $disclaimerTexto texto legal a aceptar
--}}
@php
    $fase            = $fase ?? 'inicio';
    $titulo          = $titulo ?? 'Verificación fotográfica';
    $subtitulo       = $subtitulo ?? 'Tome una foto de su rostro para registrar el inicio de la diligencia.';
    $textoConfirmar  = $textoConfirmar ?? 'Confirmar foto';
    $disclaimerTexto = $disclaimerTexto ?? 'AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES: Esta diligencia de descargos se realizará a través de medios digitales, electrónicos y/o virtuales, por lo cual autorizo que mi dirección IP, la fecha y hora exactas de cada acción, el canal de verificación utilizado, las fotografías tomadas en el desarrollo de la diligencia y en general el tratamiento de mis datos personales sean tratados conforme a la Ley 1581 de 2012 y demás normas que la adicionen, modifiquen y/o complementen.';
@endphp

<div class="space-y-5"
    x-data="{
        disclaimerAceptado: false,
        disclaimerMarcado: false,
        stream: null,
        fotoCapturada: null,
        errorCamara: false,
        modelsCargados: false,
        estadoRostro: 'esperando',
        intervaloDeteccion: null,
        validando: false,
        errorValidacion: '',
        revisandoAccesorios: false,
        alertaAccesorios: '',
        intervaloAccesorios: null,
        verificandoAccesoriosVivo: false,
        parpadeoDetectado: false,
        ojosCerradosPrevio: false,

        aceptarDisclaimer() {
            if (!this.disclaimerMarcado) return;
            this.disclaimerAceptado = true;
            this.iniciarCamara();
        },

        async iniciarCamara() {
            // Guarda dura: sin consentimiento aceptado no se pide siquiera el
            // permiso de cámara al navegador.
            if (!this.disclaimerAceptado) return;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
                });
                this.$refs.video.srcObject = this.stream;
                this.errorCamara = false;
                await this.cargarModelos();
            } catch (e) {
                this.errorCamara = true;
            }
        },

        get colorEncuadre() {
            if (this.alertaAccesorios) return '#f97316';
            if (this.estadoRostro === 'ok') return '#4ade80';
            if (this.estadoRostro === 'falta_parpadeo') return '#e11d48';
            if (this.estadoRostro === 'muy_lejos') return '#fbbf24';
            if (this.estadoRostro === 'esperando') return 'rgba(255,255,255,0.45)';
            return '#f87171';
        },

        async cargarModelos() {
            try {
                // Mismo origen CDN que ya usaba esta vista pública (el layout
                // descargos/formulario.blade.php carga face-api desde jsdelivr).
                // No se cambia a los modelos locales del panel: sería un cambio
                // de infraestructura aparte, con su propia verificación.
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'),
                    faceapi.nets.faceLandmark68TinyNet.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'),
                ]);
                this.modelsCargados = true;
                this.iniciarDeteccion();
                this.iniciarDeteccionAccesorios();
            } catch (e) {
                this.modelsCargados = true;
                this.estadoRostro = 'sin_modelo';
                this.iniciarDeteccionAccesorios();
            }
        },

        /**
         * Eye Aspect Ratio: promedio de las 2 distancias verticales del ojo
         * dividido por la horizontal. Alto = ojo abierto, bajo = cerrado.
         */
        calcularEAR(ojo) {
            const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
            const horizontal = dist(ojo[0], ojo[3]);
            if (!horizontal) return 1;
            return (dist(ojo[1], ojo[5]) + dist(ojo[2], ojo[4])) / (2 * horizontal);
        },

        iniciarDeteccion() {
            if (this.intervaloDeteccion) clearInterval(this.intervaloDeteccion);
            // 250ms: un parpadeo dura ~100-400ms; a 500ms se perdía la fase de
            // ojos cerrados y la prueba de vida casi nunca se disparaba.
            this.intervaloDeteccion = setInterval(async () => {
                const video = this.$refs.video;
                if (!video || video.readyState < 2 || !video.videoWidth || this.fotoCapturada) return;
                try {
                    const detection = await faceapi
                        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.65 }))
                        .withFaceLandmarks(true);

                    if (!detection) { this.estadoRostro = 'sin_rostro'; return; }

                    const box = detection.detection.box;
                    const ratio = (box.width * box.height) / (video.videoWidth * video.videoHeight);
                    if (ratio < 0.08) { this.estadoRostro = 'muy_lejos'; return; }

                    // Ojos visibles y separados (una mano tapando el rostro da
                    // una separación ocular anormalmente pequeña).
                    const lEye = detection.landmarks.getLeftEye();
                    const rEye = detection.landmarks.getRightEye();
                    const eyeSep = Math.abs(rEye[0].x - lEye[0].x);
                    if (eyeSep < box.width * 0.18) { this.estadoRostro = 'sin_rostro'; return; }

                    // Prueba de vida: exigir un parpadeo real. Una fotografía
                    // impresa o una pantalla no parpadean.
                    if (!this.parpadeoDetectado) {
                        const ear = (this.calcularEAR(lEye) + this.calcularEAR(rEye)) / 2;
                        if (ear < 0.21) {
                            this.ojosCerradosPrevio = true;
                        } else if (ear > 0.28 && this.ojosCerradosPrevio) {
                            this.parpadeoDetectado = true;
                            this.estadoRostro = 'ok';
                            // Captura en el instante del parpadeo: la foto queda
                            // tomada en el mismo momento en que se probó que hay
                            // una persona viva frente a la cámara.
                            if (!this.alertaAccesorios && !this.revisandoAccesorios && !this.fotoCapturada) {
                                this.tomarFoto();
                            }
                            return;
                        }
                        if (!this.parpadeoDetectado) { this.estadoRostro = 'falta_parpadeo'; return; }
                    }

                    this.estadoRostro = 'ok';
                } catch (e) { /* ignorar errores de detección */ }
            }, 250);
        },

        iniciarDeteccionAccesorios() {
            if (this.intervaloAccesorios) clearInterval(this.intervaloAccesorios);
            this.intervaloAccesorios = setInterval(async () => {
                if (this.fotoCapturada || this.revisandoAccesorios || this.verificandoAccesoriosVivo) return;
                if (this.estadoRostro !== 'ok' && this.estadoRostro !== 'falta_parpadeo') return;
                const video = this.$refs.video;
                if (!video || video.readyState < 2 || !video.videoWidth) return;
                const escala = Math.min(1, 640 / video.videoWidth);
                const tmp = document.createElement('canvas');
                tmp.width  = Math.round(video.videoWidth  * escala);
                tmp.height = Math.round(video.videoHeight * escala);
                const ctx = tmp.getContext('2d');
                ctx.translate(tmp.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, tmp.width, tmp.height);
                const foto = tmp.toDataURL('image/jpeg', 0.70);
                this.verificandoAccesoriosVivo = true;
                try {
                    await $wire.verificarAccesorios(foto);
                    this.alertaAccesorios = $wire.alertaAccesorios;
                } catch (e) {}
                this.verificandoAccesoriosVivo = false;
            }, 4000);
        },

        async tomarFoto() {
            const canvas = this.$refs.canvas;
            const video  = this.$refs.video;
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);
            const foto = canvas.toDataURL('image/jpeg', 0.80);
            this.revisandoAccesorios = true;
            this.alertaAccesorios    = '';
            this.detenerDeteccion();
            await $wire.verificarAccesorios(foto);
            this.revisandoAccesorios = false;
            if ($wire.alertaAccesorios) {
                this.alertaAccesorios = $wire.alertaAccesorios;
                // Se vuelve a exigir el parpadeo: como la captura es automática y
                // no hay botón manual, sin este reset el usuario quedaría con el
                // rostro 'ok' y ninguna forma de reintentar la foto.
                this.parpadeoDetectado  = false;
                this.ojosCerradosPrevio = false;
                this.iniciarDeteccion();
                this.iniciarDeteccionAccesorios();
            } else {
                this.alertaAccesorios = '';
                this.fotoCapturada    = foto;
                this.errorValidacion  = '';
            }
        },

        volverATomarFoto() {
            this.fotoCapturada       = null;
            this.errorValidacion     = '';
            this.alertaAccesorios    = '';
            this.estadoRostro        = 'esperando';
            this.parpadeoDetectado   = false;
            this.ojosCerradosPrevio  = false;
            this.iniciarDeteccion();
            this.iniciarDeteccionAccesorios();
        },

        detenerCamara() {
            this.detenerDeteccion();
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        },

        detenerDeteccion() {
            if (this.intervaloDeteccion) { clearInterval(this.intervaloDeteccion); this.intervaloDeteccion = null; }
            if (this.intervaloAccesorios) { clearInterval(this.intervaloAccesorios); this.intervaloAccesorios = null; }
        }
    }"
    x-init="
        $wire.$watch('errorValidacionFoto', value => {
            if (value) { validando = false; errorValidacion = value; }
        });
        $wire.$watch('alertaAccesorios', value => { alertaAccesorios = value; });
    "
    @descargosFinalizados.window="detenerCamara()">

    <div>
        <h2 class="text-base font-semibold text-gray-900 mb-1">{{ $titulo }}</h2>
        <p class="text-sm text-gray-500">{{ $subtitulo }}</p>
    </div>

    {{-- ══ Autorización de datos personales (Ley 1581 de 2012) - obligatoria ══ --}}
    <div x-show="!disclaimerAceptado">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-primary-50/60 px-4 py-2.5 border-b border-gray-200 flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs font-bold uppercase tracking-wider text-primary-700 m-0">
                    Autorización de tratamiento de datos personales
                </p>
            </div>
            <div class="p-4 max-h-56 overflow-y-auto text-sm text-gray-700 leading-relaxed">
                {{ $disclaimerTexto }}
            </div>
        </div>

        <label class="flex items-start gap-3 mt-3 p-4 border rounded-xl cursor-pointer transition-colors"
               :class="disclaimerMarcado ? 'border-primary-400 bg-primary-50' : 'border-gray-200 hover:bg-gray-50'">
            <input type="checkbox" x-model="disclaimerMarcado"
                   class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 flex-shrink-0" />
            <span class="text-sm text-gray-700">
                He leído y acepto la autorización de tratamiento de mis datos personales.
            </span>
        </label>

        <button type="button"
                x-bind:disabled="!disclaimerMarcado"
                x-bind:class="disclaimerMarcado ? 'bg-primary-600 hover:bg-primary-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                @click="aceptarDisclaimer()"
                class="w-full mt-3 flex items-center justify-center gap-2 px-5 py-3.5 font-semibold rounded-xl shadow-sm transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Aceptar y activar la cámara
        </button>
    </div>

    <template x-if="disclaimerAceptado && errorCamara">
        <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 text-sm text-danger-800">
            <p class="font-semibold mb-1">No se puede acceder a la cámara</p>
            <p>Para continuar debe permitir el acceso a la cámara en su navegador.</p>
            <ul class="mt-2 space-y-1 text-danger-700 list-disc list-inside">
                <li>Busque el ícono de cámara bloqueada en la barra de direcciones</li>
                <li>Haga clic y seleccione "Permitir"</li>
                <li>Recargue la página</li>
            </ul>
        </div>
    </template>

    <div x-show="disclaimerAceptado && !errorCamara" style="display:none" class="space-y-4">
        <div x-show="!fotoCapturada" style="display:none" class="space-y-3">
            {{-- Video con encuadre guía oval --}}
            <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
                <video x-ref="video" autoplay playsinline muted
                    class="w-full h-full object-cover"
                    style="transform: scaleX(-1);"></video>

                <svg class="absolute inset-0 w-full h-full pointer-events-none"
                    viewBox="0 0 100 75" preserveAspectRatio="none">
                    <defs>
                        <mask id="encuadre-{{ $fase }}">
                            <rect width="100" height="75" fill="white"/>
                            <ellipse cx="50" cy="36" rx="23" ry="29" fill="black"/>
                        </mask>
                    </defs>
                    <rect width="100" height="75" fill="rgba(0,0,0,0.48)" mask="url(#encuadre-{{ $fase }})"/>
                    <ellipse cx="50" cy="36" rx="23" ry="29" fill="none"
                        :stroke="colorEncuadre" stroke-width="0.9" stroke-dasharray="3,1.5"/>
                    <text x="50" y="71" text-anchor="middle" fill="rgba(255,255,255,0.9)" font-size="3.8"
                        x-show="modelsCargados && estadoRostro !== 'ok' && estadoRostro !== 'muy_lejos' && estadoRostro !== 'falta_parpadeo'">
                        Centre su rostro aquí
                    </text>
                </svg>

                {{-- Mensaje de estado superpuesto --}}
                <div class="absolute bottom-2 left-0 right-0 flex justify-center px-2">
                    <template x-if="!modelsCargados">
                        <span class="bg-black/70 text-white text-xs px-3 py-1 rounded-full">
                            Cargando sistema de verificación...
                        </span>
                    </template>
                    <template x-if="modelsCargados && estadoRostro === 'sin_rostro'">
                        <span class="bg-red-600/85 text-white text-xs px-3 py-1 rounded-full">
                            Coloque su rostro frente a la cámara
                        </span>
                    </template>
                    <template x-if="modelsCargados && estadoRostro === 'muy_lejos'">
                        <span class="bg-yellow-600/85 text-white text-xs px-3 py-1 rounded-full">
                            Acérquese más a la cámara
                        </span>
                    </template>
                    <span x-show="modelsCargados && estadoRostro === 'falta_parpadeo'"
                        style="display:none"
                        class="bg-primary-600/90 text-white text-xs px-3 py-1 rounded-full">
                        Parpadee - la foto se tomará sola
                    </span>
                    <span x-show="modelsCargados && estadoRostro === 'ok' && !alertaAccesorios"
                        style="display:none"
                        class="bg-green-600/85 text-white text-xs px-3 py-1 rounded-full">
                        Parpadeo confirmado - capturando
                    </span>
                    <span x-show="modelsCargados && estadoRostro === 'ok' && alertaAccesorios"
                        x-text="alertaAccesorios"
                        style="display:none"
                        class="bg-orange-600/90 text-white text-xs px-3 py-1.5 rounded-full text-center leading-snug max-w-xs mx-2"></span>
                </div>
            </div>

            {{-- Alerta de accesorios detectados --}}
            <template x-if="alertaAccesorios">
                <div class="flex items-start gap-2 bg-orange-50 border border-orange-300 rounded-xl px-4 py-3 text-sm text-orange-900">
                    <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p x-text="alertaAccesorios"></p>
                </div>
            </template>

            {{-- Con detección activa la captura es automática al parpadear; un
                 botón manual permitiría saltarse la prueba de vida, así que solo
                 aparece si face-api no pudo cargar. --}}
            <p x-show="estadoRostro !== 'sin_modelo'" style="display:none"
               class="text-center text-sm text-gray-500">
                La foto se toma automáticamente al detectar su parpadeo. No hay que presionar nada.
            </p>

            <button type="button" @click="tomarFoto()"
                x-show="estadoRostro === 'sin_modelo'" style="display:none"
                :disabled="revisandoAccesorios || !!alertaAccesorios"
                :class="(!revisandoAccesorios && !alertaAccesorios)
                    ? 'bg-primary-600 hover:bg-primary-700 cursor-pointer'
                    : 'bg-gray-300 cursor-not-allowed'"
                class="w-full flex items-center justify-center gap-2 px-5 py-3.5 text-white font-semibold rounded-xl shadow-sm transition-colors">
                <template x-if="!revisandoAccesorios">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Tomar foto
                    </span>
                </template>
                <template x-if="revisandoAccesorios">
                    <span class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Verificando...
                    </span>
                </template>
            </button>
        </div>

        <div x-show="fotoCapturada" style="display:none" class="space-y-3">
            <div class="rounded-xl overflow-hidden border-2 border-success-400">
                <img :src="fotoCapturada" class="w-full object-cover" alt="Vista previa" />
            </div>

            <template x-if="errorValidacion">
                <div class="bg-danger-50 border border-danger-200 rounded-xl p-3 text-sm text-danger-800">
                    <p class="font-semibold mb-0.5">Foto rechazada</p>
                    <p x-text="errorValidacion"></p>
                </div>
            </template>

            <p class="text-center text-sm text-gray-600">¿La foto es clara y muestra su rostro?</p>
            <div class="flex gap-3">
                <button type="button" @click="volverATomarFoto()" :disabled="validando"
                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-50">
                    Volver a tomar
                </button>
                <button type="button"
                    @click="validando = true; errorValidacion = ''; $wire.validarFotoConIA(fotoCapturada, '{{ $fase }}')"
                    :disabled="validando"
                    class="flex-1 px-4 py-2.5 bg-success-600 hover:bg-success-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-75">
                    <span x-show="!validando">{{ $textoConfirmar }}</span>
                    <span x-show="validando" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Verificando identidad...
                    </span>
                </button>
            </div>
        </div>

        {{-- Canvas oculto para captura --}}
        <canvas x-ref="canvas" class="hidden"></canvas>
    </div>
</div>
