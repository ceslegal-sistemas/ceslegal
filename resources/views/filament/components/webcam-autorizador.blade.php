{{--
    Webcam del Autorizador — oval face detection + Gemini accessory check
    Se usa en el modal "Emitir Sanción" de ProcesoDisciplinarioResource.
    Requiere: face-api.js (cargado aquí), Livewire con $wire.verificarAccesoriosAutorizador()
--}}

{{-- face-api.js (cargado una sola vez por página) --}}
<script>
    if (!window._faceApiAutLoaded) {
        window._faceApiAutLoaded = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/dist/face-api.js';
        document.head.appendChild(s);
    }
</script>

<div wire:ignore
     x-data="{
         stream: null,
         fotoCapturada: null,
         errorCamara: false,
         modelsCargados: false,
         estadoRostro: 'esperando',
         intervaloDeteccion: null,
         validando: false,
         revisandoAccesorios: false,
         alertaAccesorios: '',
         intervaloAccesorios: null,
         verificandoAccesoriosVivo: false,

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
             if (this.estadoRostro === 'muy_lejos') return '#fbbf24';
             if (this.estadoRostro === 'esperando') return 'rgba(255,255,255,0.45)';
             return '#f87171';
         },

         async cargarModelos() {
             try {
                 await Promise.all([
                     faceapi.nets.tinyFaceDetector.loadFromUri(
                         'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'
                     ),
                     faceapi.nets.faceLandmark68TinyNet.loadFromUri(
                         'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.14/model'
                     ),
                 ]);
                 this.modelsCargados = true;
                 this.iniciarDeteccion();
                 this.iniciarDeteccionAccesorios();
             } catch (e) {
                 this.modelsCargados = true;
                 this.estadoRostro = 'ok';
                 this.iniciarDeteccionAccesorios();
             }
         },

         iniciarDeteccion() {
             this.intervaloDeteccion = setInterval(async () => {
                 const video = this.$refs.video;
                 if (!video || video.readyState < 2 || !video.videoWidth) return;
                 try {
                     const detection = await faceapi
                         .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.65 }))
                         .withFaceLandmarks(true);

                     if (!detection) {
                         this.estadoRostro = 'sin_rostro';
                     } else {
                         const ratio = (detection.detection.box.width * detection.detection.box.height) / (video.videoWidth * video.videoHeight);
                         if (ratio < 0.08) {
                             this.estadoRostro = 'muy_lejos';
                         } else {
                             const leftEye  = detection.landmarks.getLeftEye();
                             const rightEye = detection.landmarks.getRightEye();
                             const eyeSep   = Math.abs(rightEye[0].x - leftEye[0].x);
                             const minSep   = detection.detection.box.width * 0.18;
                             this.estadoRostro = eyeSep < minSep ? 'sin_rostro' : 'ok';
                         }
                     }
                 } catch (e) { /* ignorar */ }
             }, 500);
         },

         iniciarDeteccionAccesorios() {
             this.intervaloAccesorios = setInterval(async () => {
                 if (this.fotoCapturada || this.revisandoAccesorios || this.verificandoAccesoriosVivo) return;
                 if (this.estadoRostro !== 'ok') return;
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
                     await $wire.verificarAccesoriosAutorizador(foto);
                     this.alertaAccesorios = $wire.alertaAccesoriosAutorizador;
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
             await $wire.verificarAccesoriosAutorizador(foto);
             this.revisandoAccesorios = false;
             if ($wire.alertaAccesoriosAutorizador) {
                 this.alertaAccesorios = $wire.alertaAccesoriosAutorizador;
                 this.iniciarDeteccion();
                 this.iniciarDeteccionAccesorios();
             } else {
                 this.alertaAccesorios = '';
                 this.fotoCapturada    = foto;
                 // Persistir en el campo hidden del formulario Filament
                 $wire.$set('mountedTableActionsData.0.foto_autorizador_base64', foto);
             }
         },

         volverATomarFoto() {
             this.fotoCapturada    = null;
             this.alertaAccesorios = '';
             this.estadoRostro     = 'esperando';
             $wire.$set('mountedTableActionsData.0.foto_autorizador_base64', null);
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
     x-init="iniciarCamara()"
     @modal-closed.window="detenerCamara()">

    <div class="space-y-3">

        {{-- Error de cámara --}}
        <template x-if="errorCamara">
            <div class="bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-700 rounded-xl p-4 text-sm text-danger-800 dark:text-danger-300">
                <p class="font-semibold mb-1">No se puede acceder a la cámara</p>
                <p>Para continuar debe permitir el acceso a la cámara en su navegador.</p>
                <ul class="mt-2 space-y-1 list-disc list-inside">
                    <li>Busque el ícono de cámara bloqueada en la barra de direcciones</li>
                    <li>Haga clic y seleccione "Permitir"</li>
                </ul>
            </div>
        </template>

        <template x-if="!errorCamara">
            <div class="space-y-3">

                {{-- Visor de cámara (pre-captura) --}}
                <template x-if="!fotoCapturada">
                    <div class="space-y-3">
                        <div class="relative rounded-xl overflow-hidden bg-black" style="aspect-ratio: 4/3;">
                            <video x-ref="video" autoplay playsinline muted
                                   class="w-full h-full object-cover"
                                   style="transform: scaleX(-1);"></video>

                            {{-- Overlay oval --}}
                            <svg class="absolute inset-0 w-full h-full pointer-events-none"
                                 viewBox="0 0 100 75" preserveAspectRatio="none">
                                <defs>
                                    <mask id="oval-autorizador">
                                        <rect width="100" height="75" fill="white"/>
                                        <ellipse cx="50" cy="36" rx="23" ry="29" fill="black"/>
                                    </mask>
                                </defs>
                                <rect width="100" height="75" fill="rgba(0,0,0,0.48)" mask="url(#oval-autorizador)"/>
                                <ellipse cx="50" cy="36" rx="23" ry="29" fill="none"
                                         :stroke="colorEncuadre" stroke-width="0.9" stroke-dasharray="3,1.5"/>
                                <text x="50" y="71" text-anchor="middle" fill="rgba(255,255,255,0.9)" font-size="3.8"
                                      x-show="modelsCargados && estadoRostro !== 'ok' && estadoRostro !== 'muy_lejos'">
                                    Centre su rostro aquí
                                </text>
                            </svg>

                            {{-- Estado superpuesto --}}
                            <div class="absolute bottom-2 left-0 right-0 flex justify-center px-2">
                                <template x-if="!modelsCargados">
                                    <span class="bg-black/70 text-white text-xs px-3 py-1 rounded-full">Cargando verificación...</span>
                                </template>
                                <template x-if="modelsCargados && estadoRostro === 'sin_rostro'">
                                    <span class="bg-red-600/85 text-white text-xs px-3 py-1 rounded-full">Coloque su rostro frente a la cámara</span>
                                </template>
                                <template x-if="modelsCargados && estadoRostro === 'muy_lejos'">
                                    <span class="bg-yellow-600/85 text-white text-xs px-3 py-1 rounded-full">Acérquese más a la cámara</span>
                                </template>
                                <span x-show="modelsCargados && estadoRostro === 'ok' && !alertaAccesorios"
                                      style="display:none"
                                      class="bg-green-600/85 text-white text-xs px-3 py-1 rounded-full">
                                    Listo para tomar foto
                                </span>
                                <span x-show="modelsCargados && estadoRostro === 'ok' && alertaAccesorios"
                                      x-text="alertaAccesorios"
                                      style="display:none"
                                      class="bg-orange-600/90 text-white text-xs px-3 py-1.5 rounded-full text-center leading-snug max-w-xs mx-2"></span>
                            </div>
                        </div>

                        {{-- Alerta accesorios --}}
                        <template x-if="alertaAccesorios">
                            <div class="flex items-start gap-2 bg-orange-50 dark:bg-orange-900/20 border border-orange-300 dark:border-orange-600 rounded-xl px-4 py-3 text-sm text-orange-900 dark:text-orange-300">
                                <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <p x-text="alertaAccesorios"></p>
                            </div>
                        </template>

                        {{-- Canvas oculto --}}
                        <canvas x-ref="canvas" class="hidden"></canvas>

                        {{-- Botón tomar foto --}}
                        <div class="flex justify-center">
                            <button type="button"
                                    :disabled="estadoRostro !== 'ok' || alertaAccesorios || revisandoAccesorios"
                                    :class="estadoRostro === 'ok' && !alertaAccesorios && !revisandoAccesorios
                                        ? 'bg-primary-600 hover:bg-primary-700 text-white cursor-pointer'
                                        : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed'"
                                    @click.prevent="tomarFoto()"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-colors shadow-sm">
                                <template x-if="revisandoAccesorios">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </template>
                                <template x-if="!revisandoAccesorios">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </template>
                                <span x-text="revisandoAccesorios ? 'Verificando...' : 'Tomar foto de verificación'"></span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Foto capturada --}}
                <template x-if="fotoCapturada">
                    <div class="space-y-3">
                        <div class="relative rounded-xl overflow-hidden border-2 border-green-400 dark:border-green-600" style="aspect-ratio: 4/3;">
                            <img :src="fotoCapturada" class="w-full h-full object-cover" style="transform: scaleX(-1);" alt="Foto del autorizador"/>
                            <div class="absolute top-2 right-2">
                                <span class="bg-green-600 text-white text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Foto registrada
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <button type="button"
                                    @click.prevent="volverATomarFoto()"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Volver a tomar
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </template>
    </div>
</div>
