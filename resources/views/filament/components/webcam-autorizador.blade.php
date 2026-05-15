{{--
    Webcam del Autorizador — detección oval + validaciones estrictas de rostro
    Correcciones v3:
    - Usa x-show en lugar de <template x-if> para mantener el video en el DOM
    - Detecta cara recortada en bordes del frame
    - Detecta foto de perfil (nariz descentrada entre ojos)
    - volverATomarFoto ya no necesita reiniciar la cámara
    - Texto con contraste explícito para modo oscuro
--}}
{{-- face-api se carga directamente dentro de cargarModelos() para evitar
     condiciones de carrera con la inicialización de Alpine.js en Livewire --}}

<style>
.wca-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 18px; border-radius: 10px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    border: none; transition: opacity 0.15s;
}
.wca-btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
.wca-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 10px;
    font-size: 12px; font-weight: 500; cursor: pointer;
    background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.80);
    border: 1px solid rgba(255,255,255,0.12);
}
.wca-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 100px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
</style>

<div wire:ignore
     x-data="{
         stream: null,
         fotoCapturada: null,
         errorCamara: false,
         modelsCargados: false,
         estadoRostro: 'esperando',
         intervaloDeteccion: null,
         revisandoAccesorios: false,
         alertaAccesorios: '',
         intervaloAccesorios: null,
         verificandoAccesoriosVivo: false,

         mensajeEstado: {
             esperando:   'Posicione su rostro frente a la cámara',
             sin_rostro:  'No se detecta un rostro',
             muy_lejos:   'Acérquese más a la cámara',
             recortado:   'Centre su rostro completamente en el óvalo',
             inclinado:   'Enderece la cabeza y mire de frente',
             perfil:      'Mire directamente a la cámara (foto de frente)',
             ok:          'Listo — tome la foto ahora',
             sin_modelo:  'Verificación automática no disponible',
         },

         colorEstado: {
             esperando:   'rgba(255,255,255,0.45)',
             sin_rostro:  '#f87171',
             muy_lejos:   '#fbbf24',
             recortado:   '#fb923c',
             inclinado:   '#fb923c',
             perfil:      '#fb923c',
             ok:          '#4ade80',
             sin_modelo:  'rgba(255,255,255,0.45)',
         },

         get colorEncuadre() {
             if (this.alertaAccesorios) return '#f97316';
             return this.colorEstado[this.estadoRostro] || 'rgba(255,255,255,0.45)';
         },

         async iniciarCamara() {
             if (this.stream && this.stream.active) return;
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

         async cargarModelos() {
             try {
                 // Cargar face-api.js si aún no está en la página.
                 // Se hace aquí (no en <script> externo) para evitar la condición
                 // de carrera entre el <script> inyectado por Livewire y x-init de Alpine.
                 if (typeof faceapi === 'undefined') {
                     // Si otro instancia ya está cargando el script, esperar a que termine
                     if (window._faceApiScriptEl) {
                         await new Promise((resolve, reject) => {
                             window._faceApiScriptEl.addEventListener('load',  resolve, { once: true });
                             window._faceApiScriptEl.addEventListener('error', reject,  { once: true });
                             // Si ya cargó entre el check y el listener, faceapi ya está disponible
                             if (typeof faceapi !== 'undefined') resolve();
                         });
                     } else {
                         // Primera vez: inyectar y esperar
                         await new Promise((resolve, reject) => {
                             const s = document.createElement('script');
                             s.src = '{{ asset('vendor/face-api/face-api.js') }}';
                             s.onload  = resolve;
                             s.onerror = reject;
                             window._faceApiScriptEl = s;
                             document.head.appendChild(s);
                         });
                     }
                 }
                 await Promise.all([
                     faceapi.nets.tinyFaceDetector.loadFromUri('{{ asset('vendor/face-api/model') }}'),
                     faceapi.nets.faceLandmark68TinyNet.loadFromUri('{{ asset('vendor/face-api/model') }}'),
                 ]);
                 this.modelsCargados = true;
                 this.iniciarDeteccion();
                 this.iniciarDeteccionAccesorios();
             } catch (e) {
                 // fail-open: modelos no disponibles — se permite la foto pero se advierte
                 // NO poner estadoRostro = 'ok' para no saltarse la validación silenciosamente
                 console.error('face-api: error cargando modelos', e);
                 this.modelsCargados = true;
                 this.estadoRostro = 'sin_modelo';
                 this.iniciarDeteccionAccesorios();
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
                     const vw  = video.videoWidth;
                     const vh  = video.videoHeight;

                     // ① Demasiado lejos
                     const ratio = (box.width * box.height) / (vw * vh);
                     if (ratio < 0.08) { this.estadoRostro = 'muy_lejos'; return; }

                     // ② Cara recortada en los bordes del frame (margen 5%)
                     const em = 0.05;
                     if (box.x / vw < em ||
                         (box.x + box.width)  / vw > (1 - em) ||
                         box.y / vh < em ||
                         (box.y + box.height) / vh > (1 - em)) {
                         this.estadoRostro = 'recortado'; return;
                     }

                     // ③ Cara fuera del óvalo — el centro facial debe quedar dentro del óvalo
                     // Óvalo SVG: cx=50%, cy=36/75≈48%, rx=23%, ry=29/75≈38.7%
                     // Tolerancias = dimensiones del óvalo × 1.15 (margen mínimo de holgura)
                     const faceCx = (box.x + box.width  / 2) / vw;
                     const faceCy = (box.y + box.height / 2) / vh;
                     const dxN = (faceCx - 0.50) / 0.265; // ±26.5% horizontal
                     const dyN = (faceCy - 0.48) / 0.445; // ±44.5% vertical
                     if (dxN * dxN + dyN * dyN > 1.0) {
                         this.estadoRostro = 'recortado'; return;
                     }

                     // ④ Ojos no visibles / cara tapada
                     const lEye = detection.landmarks.getLeftEye();
                     const rEye = detection.landmarks.getRightEye();
                     const eyeSep = Math.abs(rEye[0].x - lEye[0].x);
                     if (eyeSep < box.width * 0.18) { this.estadoRostro = 'sin_rostro'; return; }

                     // ⑤ Cara inclinada — el eje entre los ojos debe ser casi horizontal
                     // Calcula el centro promedio de cada ojo y el ángulo entre ellos
                     const lEyeCx = lEye.reduce((s, p) => s + p.x, 0) / lEye.length;
                     const lEyeCy = lEye.reduce((s, p) => s + p.y, 0) / lEye.length;
                     const rEyeCx = rEye.reduce((s, p) => s + p.x, 0) / rEye.length;
                     const rEyeCy = rEye.reduce((s, p) => s + p.y, 0) / rEye.length;
                     // Para cara erguida: ~0°. Para cara ladeada 90°: ~90°.
                     const rollDeg = Math.abs(Math.atan2(rEyeCy - lEyeCy, rEyeCx - lEyeCx) * 180 / Math.PI);
                     if (rollDeg > 30) { this.estadoRostro = 'inclinado'; return; }

                     // ⑥ Foto de perfil — nariz no centrada entre los ojos
                     const nose  = detection.landmarks.getNose();
                     const lCx   = lEyeCx;
                     const rCx   = rEyeCx;
                     const noseX = (nose[3] || nose[0]).x;
                     const offset = Math.abs(noseX - (lCx + rCx) / 2) / box.width;
                     if (offset > 0.22) { this.estadoRostro = 'perfil'; return; }

                     this.estadoRostro = 'ok';
                 } catch (e) { /* ignorar errores de detección */ }
             }, 500);
         },

         iniciarDeteccionAccesorios() {
             if (this.intervaloAccesorios) clearInterval(this.intervaloAccesorios);
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
                 ctx.translate(tmp.width, 0); ctx.scale(-1, 1);
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
             ctx.translate(canvas.width, 0); ctx.scale(-1, 1);
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
                 this.fotoCapturada = foto;
                 // Persistir en el campo Filament — el video permanece en el DOM
                 // (x-show en lugar de x-if) así el stream sobrevive al re-render
                 $wire.$set('mountedTableActionsData.0.foto_autorizador_base64', foto);
             }
         },

         volverATomarFoto() {
             // Limpiar estado — resetear flags que pueden quedar bloqueados
             this.fotoCapturada           = null;
             this.alertaAccesorios        = '';
             this.estadoRostro            = 'esperando';
             this.revisandoAccesorios     = false;   // por si tomarFoto lo dejó true
             this.verificandoAccesoriosVivo = false; // evita que el nuevo intervalo quede bloqueado
             // No llamar $wire.$set(null) — evita re-render innecesario
             // No reiniciar cámara — el stream sigue activo gracias a x-show
             this.detenerDeteccion(); // limpiar cualquier intervalo anterior
             this.iniciarDeteccion();
             this.iniciarDeteccionAccesorios();
         },

         detenerCamara() {
             this.detenerDeteccion();
             if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
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
        <div x-show="errorCamara" style="display:none">
            <div style="padding:14px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.30);border-radius:10px;">
                <p style="font-weight:700;color:#f87171;margin:0 0 6px;font-size:13px;">No se puede acceder a la cámara</p>
                <p style="color:rgba(255,255,255,0.65);font-size:12px;margin:0 0 4px;">Para continuar permita el acceso en el navegador:</p>
                <ul style="color:rgba(255,255,255,0.55);font-size:12px;margin:0;padding-left:18px;line-height:1.8;">
                    <li>Busque el ícono de cámara en la barra de direcciones</li>
                    <li>Haga clic y seleccione "Permitir"</li>
                </ul>
            </div>
        </div>

        <div x-show="!errorCamara" style="display:none">

            {{-- ══ VISOR DE CÁMARA — siempre en DOM (x-show, no x-if) ══ --}}
            <div x-show="!fotoCapturada" style="display:none" class="space-y-3">

                {{-- Video + overlay oval --}}
                <div style="position:relative;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:4/3;">
                    <video x-ref="video" autoplay playsinline muted
                           style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block;"></video>

                    {{-- Overlay oval --}}
                    <svg style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"
                         viewBox="0 0 100 75" preserveAspectRatio="none">
                        <defs>
                            <mask id="oval-aut">
                                <rect width="100" height="75" fill="white"/>
                                <ellipse cx="50" cy="36" rx="23" ry="29" fill="black"/>
                            </mask>
                        </defs>
                        <rect width="100" height="75" fill="rgba(0,0,0,0.50)" mask="url(#oval-aut)"/>
                        <ellipse cx="50" cy="36" rx="23" ry="29" fill="none"
                                 :stroke="colorEncuadre" stroke-width="0.9" stroke-dasharray="3,1.5"/>
                        <text x="50" y="71" text-anchor="middle"
                              fill="rgba(255,255,255,0.85)" font-size="3.6" font-family="sans-serif"
                              x-show="modelsCargados && estadoRostro !== 'ok'">
                            Centre su rostro aquí
                        </text>
                    </svg>

                    {{-- Cargando modelos --}}
                    <div x-show="!modelsCargados"
                         style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);">
                        <span class="wca-badge" style="background:rgba(0,0,0,0.70);color:white;">
                            <svg style="width:12px;height:12px;animation:spin 1s linear infinite;flex-shrink:0;" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
                                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Cargando verificación...
                        </span>
                    </div>

                    {{-- Estado del rostro --}}
                    <div x-show="modelsCargados"
                         style="position:absolute;bottom:10px;left:0;right:0;display:flex;justify-content:center;padding:0 8px;">

                        <span x-show="estadoRostro === 'sin_rostro'"
                              class="wca-badge" style="background:rgba(220,38,38,0.85);color:white;display:none;">
                            No se detecta un rostro — acérquese y mire de frente
                        </span>
                        <span x-show="estadoRostro === 'muy_lejos'"
                              class="wca-badge" style="background:rgba(180,83,9,0.85);color:white;display:none;">
                            Acérquese más a la cámara
                        </span>
                        <span x-show="estadoRostro === 'recortado'"
                              class="wca-badge" style="background:rgba(194,65,12,0.85);color:white;display:none;">
                            Centre su rostro completamente en el óvalo
                        </span>
                        <span x-show="estadoRostro === 'inclinado'"
                              class="wca-badge" style="background:rgba(194,65,12,0.85);color:white;display:none;">
                            Enderece la cabeza y mire de frente
                        </span>
                        <span x-show="estadoRostro === 'perfil'"
                              class="wca-badge" style="background:rgba(194,65,12,0.85);color:white;display:none;">
                            Mire directamente a la cámara (foto de frente)
                        </span>
                        <span x-show="estadoRostro === 'ok' && !alertaAccesorios"
                              class="wca-badge" style="background:rgba(22,101,52,0.85);color:#86efac;display:none;">
                            <svg style="width:11px;height:11px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Listo — tome la foto ahora
                        </span>
                        <span x-show="estadoRostro === 'ok' && alertaAccesorios"
                              x-text="alertaAccesorios"
                              class="wca-badge" style="background:rgba(180,83,9,0.90);color:white;max-width:280px;text-align:center;white-space:normal;display:none;">
                        </span>
                        <span x-show="estadoRostro === 'sin_modelo'"
                              class="wca-badge" style="background:rgba(75,85,99,0.88);color:rgba(255,255,255,0.80);display:none;">
                            Verificación automática no disponible — puede tomar la foto
                        </span>
                    </div>
                </div>

                {{-- Alerta de accesorios detectados --}}
                <div x-show="alertaAccesorios" style="display:none;padding:10px 14px;background:rgba(180,83,9,0.15);border:1px solid rgba(251,191,36,0.30);border-radius:10px;display:flex;align-items:flex-start;gap:8px;">
                    <svg style="width:16px;height:16px;color:#fbbf24;flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p x-text="alertaAccesorios" style="font-size:12px;color:rgba(255,255,255,0.80);margin:0;line-height:1.5;"></p>
                </div>

                {{-- Canvas oculto para captura --}}
                <canvas x-ref="canvas" style="display:none;"></canvas>

                {{-- Botón tomar foto --}}
                <div style="display:flex;justify-content:center;">
                    <button type="button"
                            :disabled="(estadoRostro !== 'ok' && estadoRostro !== 'sin_modelo') || !!alertaAccesorios || revisandoAccesorios"
                            @click.prevent="tomarFoto()"
                            class="wca-btn-primary"
                            :style="((estadoRostro === 'ok' || estadoRostro === 'sin_modelo') && !alertaAccesorios && !revisandoAccesorios)
                                ? 'background:#6366f1;color:white;'
                                : 'background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.35);'">

                        <svg x-show="!revisandoAccesorios" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg x-show="revisandoAccesorios" style="width:16px;height:16px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="revisandoAccesorios ? 'Verificando foto...' : 'Tomar foto de verificación'"></span>
                    </button>
                </div>
            </div>

            {{-- ══ FOTO CAPTURADA (el video sigue en DOM, solo oculto) ══ --}}
            <div x-show="fotoCapturada" style="display:none;" class="space-y-3">
                <div style="position:relative;border-radius:12px;overflow:hidden;border:2px solid #4ade80;aspect-ratio:4/3;">
                    <img :src="fotoCapturada" style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);" alt="Foto del autorizador"/>
                    <div style="position:absolute;top:10px;right:10px;">
                        <span class="wca-badge" style="background:rgba(22,101,52,0.90);color:#86efac;">
                            <svg style="width:11px;height:11px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Foto registrada
                        </span>
                    </div>
                </div>
                <div style="display:flex;justify-content:center;">
                    <button type="button" @click.prevent="volverATomarFoto()" class="wca-btn-secondary">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Volver a tomar
                    </button>
                </div>
            </div>

        </div>{{-- /!errorCamara --}}
    </div>

    <style>
    @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</div>
