{{--
    Captura de la foto de referencia del trabajador durante la socialización
    del RIT - usa face-api.js (encuadre + prueba de vida por parpadeo) y
    detección de accesorios, MISMO mecanismo real que
    verificacion-facial-descargos.blade.php (descargos), simplificado:
      - Sin disclaimer de datos personales aparte (la aceptación del RIT ya
        cubre el consentimiento de este flujo).
      - Sin MediaPipe: en descargos esta ahi pero DESACTIVADO (ver nota en
        verificacion-facial-descargos.blade.php) - se usa directo el
        respaldo EAR, que es lo que de verdad corre hoy en produccion.
      - Sin parametro $fase: aqui solo hay una foto, no inicio/fin.

    Por que importa la misma calidad que en descargos: esta foto se guarda
    como foto_referencia_path del trabajador, y luego un proceso REAL de
    descargos usa AWS Rekognition para comparar contra ella y confirmar que
    es la misma persona - una foto con accesorios/borrosa aqui arruina esa
    verificacion despues.

    wire:key en el elemento raiz: este partial se incluye desde una rama
    @elseif($etapa === 'foto') que cambia a otras etapas - sin wire:key,
    morphdom puede dejar el scope de Alpine sin inicializar al volver a esta
    etapa (mismo bug real ya ocurrido en emitir-sancion-pasos.blade.php y
    verificacion-facial-descargos.blade.php).
--}}
<div wire:key="foto-simple-captura" class="space-y-4"
    x-data="{
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
        earBase: null,
        mostrarFallbackManual: false,
        timerFallback: null,

        async iniciarCamara() {
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
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'),
                    faceapi.nets.faceLandmark68TinyNet.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'),
                ]);
                this.modelsCargados = true;
                this.iniciarDeteccion();
                this.iniciarDeteccionAccesorios();
                this.iniciarTimerFallback();
            } catch (e) {
                this.modelsCargados = true;
                this.estadoRostro = 'sin_modelo';
                this.iniciarDeteccionAccesorios();
            }
        },

        iniciarTimerFallback() {
            if (this.timerFallback) clearTimeout(this.timerFallback);
            this.timerFallback = setTimeout(() => {
                if (!this.fotoCapturada) this.mostrarFallbackManual = true;
            }, 5000);
        },

        calcularEAR(ojo) {
            const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
            const horizontal = dist(ojo[0], ojo[3]);
            if (!horizontal) return 1;
            return (dist(ojo[1], ojo[5]) + dist(ojo[2], ojo[4])) / (2 * horizontal);
        },

        confirmarParpadeo() {
            this.parpadeoDetectado = true;
            this.estadoRostro = 'ok';
            if (!this.alertaAccesorios && !this.revisandoAccesorios && !this.fotoCapturada) {
                this.tomarFoto();
            }
        },

        iniciarDeteccion() {
            if (this.intervaloDeteccion) clearInterval(this.intervaloDeteccion);
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

                    const lEye = detection.landmarks.getLeftEye();
                    const rEye = detection.landmarks.getRightEye();
                    const eyeSep = Math.abs(rEye[0].x - lEye[0].x);
                    if (eyeSep < box.width * 0.18) { this.estadoRostro = 'sin_rostro'; return; }

                    if (!this.parpadeoDetectado) {
                        const ear = (this.calcularEAR(lEye) + this.calcularEAR(rEye)) / 2;
                        if (!this.ojosCerradosPrevio) {
                            this.earBase = this.earBase === null ? ear : Math.max(ear, this.earBase * 0.98);
                        }
                        const base           = this.earBase ?? ear;
                        const umbralCierre   = Math.max(0.15, base * 0.75);
                        const umbralApertura = base * 0.90;

                        if (ear < umbralCierre) {
                            this.ojosCerradosPrevio = true;
                        } else if (ear > umbralApertura && this.ojosCerradosPrevio) {
                            this.confirmarParpadeo();
                        }
                        if (!this.parpadeoDetectado) { this.estadoRostro = 'falta_parpadeo'; }
                        return;
                    }

                    this.estadoRostro = 'ok';
                } catch (e) { /* ignorar errores de deteccion */ }
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
            }, 5000);
        },

        async tomarFoto() {
            try {
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
                await Promise.race([
                    $wire.verificarAccesorios(foto),
                    new Promise(resolve => setTimeout(resolve, 10000)),
                ]);
                this.revisandoAccesorios = false;
                if ($wire.alertaAccesorios) {
                    this.alertaAccesorios = $wire.alertaAccesorios;
                    this.parpadeoDetectado  = false;
                    this.ojosCerradosPrevio = false;
                    this.earBase            = null;
                    this.iniciarDeteccion();
                    this.iniciarDeteccionAccesorios();
                    this.iniciarTimerFallback();
                } else {
                    this.alertaAccesorios = '';
                    this.fotoCapturada    = foto;
                    this.errorValidacion  = '';
                }
            } catch (e) {
                this.revisandoAccesorios = false;
                this.parpadeoDetectado  = false;
                this.ojosCerradosPrevio = false;
                this.earBase            = null;
                this.iniciarDeteccion();
                this.iniciarDeteccionAccesorios();
                this.iniciarTimerFallback();
            }
        },

        volverATomarFoto() {
            this.fotoCapturada       = null;
            this.errorValidacion     = '';
            this.alertaAccesorios    = '';
            this.estadoRostro        = 'esperando';
            this.parpadeoDetectado   = false;
            this.ojosCerradosPrevio  = false;
            this.earBase             = null;
            this.mostrarFallbackManual = false;
            this.iniciarDeteccion();
            this.iniciarDeteccionAccesorios();
            this.iniciarTimerFallback();
        },

        detenerCamara() {
            this.detenerDeteccion();
            if (this.stream) this.stream.getTracks().forEach(t => t.stop());
        },

        detenerDeteccion() {
            if (this.intervaloDeteccion) { clearInterval(this.intervaloDeteccion); this.intervaloDeteccion = null; }
            if (this.intervaloAccesorios) { clearInterval(this.intervaloAccesorios); this.intervaloAccesorios = null; }
            if (this.timerFallback) { clearTimeout(this.timerFallback); this.timerFallback = null; }
        }
     }"
     x-init="
        iniciarCamara();
        $wire.$watch('errorValidacionFoto', value => {
            if (value) { validando = false; errorValidacion = value; }
        });
        $wire.$watch('alertaAccesorios', value => { alertaAccesorios = value; });
     "
     x-on:beforeunload.window="detenerCamara()">

    <div>
        <h2 class="text-base font-semibold text-gray-900 mb-1">Tómate una foto</h2>
        <p class="text-sm text-gray-500">La usaremos para confirmar que eres tú si más adelante te llaman a un proceso de descargos.</p>
    </div>

    <template x-if="errorCamara">
        <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 text-sm text-danger-800">
            <p class="font-semibold mb-1">No se puede acceder a la cámara</p>
            <p>Para continuar debe permitir el acceso a la cámara en su navegador.</p>
        </div>
    </template>

    <div x-show="!errorCamara" style="display:none" class="space-y-4">
        <div x-show="!fotoCapturada" style="display:none" class="space-y-3">
            <div class="relative rounded-xl overflow-hidden bg-black" style="aspect-ratio: 4 / 3; width: 100%;">
                <video x-ref="video" autoplay playsinline muted
                    class="w-full h-full object-cover"
                    style="transform: scaleX(-1);"></video>

                <svg class="absolute inset-0 w-full h-full pointer-events-none"
                    viewBox="0 0 100 75" preserveAspectRatio="none">
                    <defs>
                        <mask id="encuadre-rit">
                            <rect width="100" height="75" fill="white"/>
                            <ellipse cx="50" cy="36" rx="23" ry="29" fill="black"/>
                        </mask>
                    </defs>
                    <rect width="100" height="75" fill="rgba(0,0,0,0.48)" mask="url(#encuadre-rit)"/>
                    <ellipse cx="50" cy="36" rx="23" ry="29" fill="none"
                        :stroke="colorEncuadre" stroke-width="0.9" stroke-dasharray="3,1.5"/>
                </svg>

                <div class="absolute bottom-2 left-0 right-0 flex justify-center px-2">
                    <template x-if="!modelsCargados">
                        <span class="bg-black/70 text-white text-xs px-3 py-1 rounded-full">Cargando cámara...</span>
                    </template>
                    <template x-if="modelsCargados && estadoRostro === 'sin_rostro'">
                        <span class="bg-red-600/85 text-white text-xs px-3 py-1 rounded-full">Coloque su rostro frente a la cámara</span>
                    </template>
                    <template x-if="modelsCargados && estadoRostro === 'muy_lejos'">
                        <span class="bg-yellow-600/85 text-white text-xs px-3 py-1 rounded-full">Acérquese más a la cámara</span>
                    </template>
                    <span x-show="modelsCargados && estadoRostro === 'falta_parpadeo'" style="display:none"
                        class="bg-primary-600/90 text-white text-xs px-3 py-1 rounded-full">
                        Parpadee - la foto se tomará sola
                    </span>
                    <span x-show="modelsCargados && estadoRostro === 'ok' && !alertaAccesorios" style="display:none"
                        class="bg-green-600/85 text-white text-xs px-3 py-1 rounded-full">
                        Parpadeo confirmado - capturando
                    </span>
                    <span x-show="modelsCargados && estadoRostro === 'ok' && alertaAccesorios" x-text="alertaAccesorios" style="display:none"
                        class="bg-orange-600/90 text-white text-xs px-3 py-1.5 rounded-full text-center leading-snug max-w-xs mx-2"></span>
                </div>
            </div>

            <template x-if="alertaAccesorios">
                <div class="flex items-start gap-2 bg-orange-50 border border-orange-300 rounded-xl px-4 py-3 text-sm text-orange-900">
                    <p x-text="alertaAccesorios"></p>
                </div>
            </template>

            <p x-show="estadoRostro !== 'sin_modelo' && !mostrarFallbackManual" style="display:none"
               class="text-center text-sm text-gray-500">
                La foto se toma sola cuando parpadees. No hay que presionar nada.
            </p>

            <button type="button" @click="tomarFoto()"
                x-show="estadoRostro === 'sin_modelo' || mostrarFallbackManual" style="display:none"
                :disabled="revisandoAccesorios || !!alertaAccesorios"
                :class="(!revisandoAccesorios && !alertaAccesorios) ? 'bg-primary-600 hover:bg-primary-700 cursor-pointer' : 'bg-gray-300 cursor-not-allowed'"
                class="w-full flex items-center justify-center gap-2 px-5 py-3.5 text-white font-semibold rounded-xl shadow-sm transition-colors">
                <span x-show="!revisandoAccesorios">Tomar foto</span>
                <span x-show="revisandoAccesorios">Verificando...</span>
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

            <div class="flex gap-3">
                <button type="button" @click="volverATomarFoto()" :disabled="validando"
                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors disabled:opacity-50">
                    Repetir
                </button>
                <button type="button"
                    @click="validando = true; errorValidacion = ''; $wire.validarFotoConIA(fotoCapturada)"
                    :disabled="validando"
                    class="flex-1 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-75">
                    <span x-show="!validando">Continuar</span>
                    <span x-show="validando">Verificando...</span>
                </button>
            </div>
        </div>

        <canvas x-ref="canvas" class="hidden"></canvas>
    </div>
</div>
