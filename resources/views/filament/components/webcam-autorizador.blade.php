{{--
    Webcam de verificación fotográfica (equivalencia funcional de firma) - detección
    oval + validaciones estrictas de rostro.
    Correcciones v4:
    - Compatible con modo claro y oscuro (CSS custom properties)
    - face-api se carga dentro de cargarModelos() sin depender de <script> externo
    - Detecta cara recortada, perfil, inclinada, muy lejos
    - volverATomarFoto resetea todos los flags

    Reutilizable en cualquier contexto Livewire: el host (Table Action, Page Action o
    Wizard de un CreateRecord) debe exponer `verificarAccesoriosAutorizador($fotoBase64)`
    y `$alertaAccesoriosAutorizador` (ver App\Filament\Concerns\HasVerificacionFotografica),
    y pasar `wireTargetPath` con la ruta de estado Livewire donde escribir la foto:
      - Table Action:  'mountedTableActionsData.{n}.campo'  (default, compat. actual)
      - Page Action:   'mountedActionsData.{n}.campo'
      - Wizard/Create: 'data.campo'

    En modales (Table/Page Action) el contenido solo se monta al abrir el modal, así que
    "al iniciar el componente" == "al abrir el modal" (correcto: pide cámara justo ahí).
    En un Wizard, Filament renderiza TODOS los pasos en el DOM desde el primer paso (solo
    los oculta con CSS), así que sin esto la cámara se pediría de una vez al cargar la
    página. Por eso, si el host pasa `wizardStepId` (el id del Step donde vive este
    componente), el `x-init` no llama iniciarCamara() de inmediato: espera a que la
    variable reactiva `step` del Wizard ancestro (declarada en su x-data, heredada aquí
    sin redeclararla) coincida con ese id, y libera la cámara al salir del paso.
--}}
@php
    $wireTargetPath = $wireTargetPath ?? 'mountedTableActionsData.0.foto_autorizador_base64';
    $wizardStepId   = $wizardStepId ?? null;

    // Autorización de tratamiento de datos personales (Ley 1581 de 2012) -
    // OBLIGATORIA antes de cualquier captura fotográfica, por requisito legal
    // explícito del usuario. El flujo del trabajador (FormularioDescargos, etapa
    // 'disclaimer') ya exigía este mismo consentimiento con su propio texto
    // configurable; este componente - usado por el AUTORIZADOR de la empresa en
    // Emitir Sanción, CreateProcesoDisciplinario y la aceptación del RIT
    // mejorado - no pedía ninguno. Se bloquea la cámara hasta aceptar: así no se
    // solicita siquiera el permiso del navegador antes del consentimiento.
    $disclaimerTexto = $disclaimerTexto ?? 'AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES: Esta diligencia se realizará a través de medios digitales, electrónicos y/o virtuales, por lo cual autorizo que mi dirección IP, la fecha y hora exactas de cada acción, el canal de verificación utilizado, las fotografías tomadas en el desarrollo de la diligencia y en general el tratamiento de mis datos personales sean tratados conforme a la Ley 1581 de 2012 y demás normas que la adicionen, modifiquen y/o complementen.';
@endphp

<style>
/* ── Variables modo claro (default) / oscuro (html.dark) ─────────── */
:root {
    --wca-text:        rgba(17,24,39,0.80);
    --wca-text-muted:  rgba(17,24,39,0.58);
    --wca-list-color:  rgba(17,24,39,0.60);
    --wca-alert-text:  rgba(17,24,39,0.82);
    --wca-btn-sec-bg:  rgba(0,0,0,0.04);
    --wca-btn-sec-fg:  #374151;
    --wca-btn-sec-bd:  rgba(0,0,0,0.10);
    --wca-btn-dis-bg:  rgba(0,0,0,0.05);
    --wca-btn-dis-fg:  rgba(107,114,128,0.70);
    /* Marca LUPE Legal (rojo #E11D48) - misma paleta que .rit-btn-primary
       de lupe-hero-styles.blade.php, para no dejar un naranja genérico
       suelto en la verificación de identidad. */
    --wca-brand-bg:    rgba(225,29,72,0.10);
    --wca-brand-bd:    rgba(225,29,72,0.25);
    --wca-brand-fg:    #be123c;
    --wca-brand-solid: #e11d48;
}
html.dark {
    --wca-text:        rgba(255,255,255,0.80);
    --wca-text-muted:  rgba(255,255,255,0.65);
    --wca-list-color:  rgba(255,255,255,0.55);
    --wca-alert-text:  rgba(255,255,255,0.80);
    --wca-btn-sec-bg:  rgba(255,255,255,0.07);
    --wca-btn-sec-fg:  rgba(255,255,255,0.85);
    --wca-btn-sec-bd:  rgba(255,255,255,0.15);
    --wca-btn-dis-bg:  rgba(255,255,255,0.08);
    --wca-btn-dis-fg:  rgba(255,255,255,0.35);
    --wca-brand-bg:    rgba(251,113,133,0.18);
    --wca-brand-bd:    rgba(251,113,133,0.35);
    --wca-brand-fg:    #fecdd3;
    --wca-brand-solid: #fb7185;
}

/* ── Componentes (mismo lenguaje visual que .rit-btn de la marca) ──── */
.wca-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: .55rem 1.125rem; border-radius: .625rem;
    font-size: 13px; font-weight: 600; cursor: pointer;
    border: 1px solid transparent; transition: opacity 0.15s;
}
.wca-btn-primary:hover { opacity: .85; }
.wca-btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
.wca-btn-on  { background: var(--wca-brand-bg); border-color: var(--wca-brand-bd); color: var(--wca-brand-fg); }
.wca-btn-off { background: var(--wca-btn-dis-bg); color: var(--wca-btn-dis-fg); }
/* Sólido, no translúcido: es la puerta de entrada obligatoria a toda esta
   sección (sin aceptar, no hay cámara) - debe pesar visualmente igual que
   "Continuar" en el footer del modal, no verse como una acción secundaria. */
.wca-btn-solid { background: var(--wca-brand-solid); color: #fff; width: 100%; justify-content: center; }
.wca-btn-solid:disabled { background: var(--wca-btn-dis-bg); color: var(--wca-btn-dis-fg); }
button.wca-btn-secondary,
button.wca-btn-secondary:hover {
    display: inline-flex; align-items: center; gap: 6px;
    padding: .5rem 1rem; border-radius: .625rem;
    font-size: 12px; font-weight: 600; cursor: pointer;
    background: var(--wca-btn-sec-bg) !important;
    color: var(--wca-btn-sec-fg) !important;
    border: 1px solid var(--wca-btn-sec-bd) !important;
}
.wca-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 100px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
/* Tarjeta del consentimiento - mismo lenguaje que "Declaración del
   Autorizador" (el Placeholder nativo justo arriba en este mismo modal:
   acento de borde izquierdo sutil, etiqueta pequeña en mayúsculas, sin
   barra de color sólida) - antes tenían 2 estilos de tarjeta distintos
   uno junto al otro dentro del mismo modal. */
.wca-card {
    padding: 14px 16px; border-radius: 12px;
    background: var(--wca-btn-sec-bg); border: 1px solid var(--wca-btn-sec-bd);
    border-left: 3px solid var(--wca-brand-solid);
}
.wca-card-label {
    display: flex; align-items: center; gap: .4rem;
    font-size: 10px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--wca-brand-fg); margin: 0 0 6px;
}
.wca-consent {
    display: flex; align-items: flex-start; gap: 10px;
    margin-top: 12px; cursor: pointer;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div wire:ignore
     x-data="{
         disclaimerAceptado: false,
         disclaimerMarcado: false,
         stream: null,
         fotoCapturada: null,
         errorCamara: false,
         modelsCargados: false,
         estadoRostro: 'esperando',
         intervaloDeteccion: null,
         parpadeoDetectado: false,
         ojosCerradosPrevio: false,
         revisandoAccesorios: false,
         alertaAccesorios: '',
         intervaloAccesorios: null,
         verificandoAccesoriosVivo: false,

         get colorEncuadre() {
             if (this.alertaAccesorios) return '#f97316';
             const map = {
                 esperando:  'rgba(255,255,255,0.45)',
                 sin_rostro: '#f87171',
                 muy_lejos:  '#fbbf24',
                 recortado:  '#fb923c',
                 inclinado:  '#fb923c',
                 perfil:     '#fb923c',
                 falta_parpadeo: '#38bdf8',
                 ok:         '#4ade80',
                 sin_modelo: 'rgba(255,255,255,0.45)',
             };
             return map[this.estadoRostro] || 'rgba(255,255,255,0.45)';
         },

         aceptarDisclaimer() {
             if (!this.disclaimerMarcado) return;
             this.disclaimerAceptado = true;
             // Se registra en el servidor (hora del servidor + IP real): el
             // bloqueo en el navegador solo controla la interfaz, no sirve como
             // prueba de que hubo consentimiento.
             $wire.aceptarDisclaimerDatos();
             this.iniciarCamara();
         },

         async iniciarCamara() {
             // Guarda dura: ninguna ruta (x-init, $watch del wizard) puede
             // encender la cámara sin el consentimiento previo aceptado.
             if (!this.disclaimerAceptado) return;
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
                 // Cargar face-api.js si aún no está disponible.
                 // Se hace aquí (no en <script> externo) para evitar la condición
                 // de carrera entre el <script> insertado por Livewire y x-init de Alpine.
                 if (typeof faceapi === 'undefined') {
                     if (window._faceApiScriptEl) {
                         await new Promise((resolve, reject) => {
                             window._faceApiScriptEl.addEventListener('load',  resolve, { once: true });
                             window._faceApiScriptEl.addEventListener('error', reject,  { once: true });
                             if (typeof faceapi !== 'undefined') resolve();
                         });
                     } else {
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
                 console.error('face-api: error cargando modelos', e);
                 this.modelsCargados = true;
                 this.estadoRostro = 'sin_modelo';
                 this.iniciarDeteccionAccesorios();
             }
         },

         /**
          * Eye Aspect Ratio (Soukupová & Čech): promedio de las 2 distancias
          * verticales del ojo dividido por su distancia horizontal. Los 6 puntos
          * que entrega face-api vienen en orden: 0 y 3 son las esquinas
          * (horizontal), 1-5 y 2-4 los pares verticales.
          */
         calcularEAR(ojo) {
             const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
             const horizontal = dist(ojo[0], ojo[3]);
             if (!horizontal) return 1;
             return (dist(ojo[1], ojo[5]) + dist(ojo[2], ojo[4])) / (2 * horizontal);
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

                     // ③ Cara fuera del óvalo
                     const faceCx = (box.x + box.width  / 2) / vw;
                     const faceCy = (box.y + box.height / 2) / vh;
                     const dxN = (faceCx - 0.50) / 0.265;
                     const dyN = (faceCy - 0.48) / 0.445;
                     if (dxN * dxN + dyN * dyN > 1.0) {
                         this.estadoRostro = 'recortado'; return;
                     }

                     // ④ Ojos no visibles / cara tapada
                     const lEye = detection.landmarks.getLeftEye();
                     const rEye = detection.landmarks.getRightEye();
                     const eyeSep = Math.abs(rEye[0].x - lEye[0].x);
                     if (eyeSep < box.width * 0.18) { this.estadoRostro = 'sin_rostro'; return; }

                     // ⑤ Cara inclinada
                     const lEyeCx = lEye.reduce((s, p) => s + p.x, 0) / lEye.length;
                     const lEyeCy = lEye.reduce((s, p) => s + p.y, 0) / lEye.length;
                     const rEyeCx = rEye.reduce((s, p) => s + p.x, 0) / rEye.length;
                     const rEyeCy = rEye.reduce((s, p) => s + p.y, 0) / rEye.length;
                     const rollDeg = Math.abs(Math.atan2(rEyeCy - lEyeCy, rEyeCx - lEyeCx) * 180 / Math.PI);
                     if (rollDeg > 30) { this.estadoRostro = 'inclinado'; return; }

                     // ⑥ Foto de perfil
                     const nose  = detection.landmarks.getNose();
                     const noseX = (nose[3] || nose[0]).x;
                     const offset = Math.abs(noseX - (lEyeCx + rEyeCx) / 2) / box.width;
                     if (offset > 0.22) { this.estadoRostro = 'perfil'; return; }

                     // ⑦ Prueba de vida: exigir un parpadeo real antes de habilitar
                     // la captura. Evita que una fotografía impresa o una pantalla
                     // puesta frente a la cámara pase la verificación - refuerza el
                     // equivalente funcional de firma. Se mide el Eye Aspect Ratio
                     // (EAR) sobre los landmarks de ambos ojos, que face-api ya
                     // calcula aquí mismo: alto = ojo abierto, bajo = ojo cerrado.
                     // Un parpadeo es la transición cerrado -> abierto.
                     if (!this.parpadeoDetectado) {
                         const ear = (this.calcularEAR(lEye) + this.calcularEAR(rEye)) / 2;
                         if (ear < 0.21) {
                             this.ojosCerradosPrevio = true;
                         } else if (ear > 0.28 && this.ojosCerradosPrevio) {
                             this.parpadeoDetectado = true;
                             this.estadoRostro = 'ok';
                             // Captura inmediata en el instante del parpadeo: la foto
                             // queda tomada en el mismo momento en que se probó que hay
                             // una persona viva frente a la cámara, sin un clic manual
                             // posterior que rompería esa cadena.
                             if (!this.alertaAccesorios && !this.revisandoAccesorios && !this.fotoCapturada) {
                                 this.tomarFoto();
                             }
                             return;
                         }
                         if (!this.parpadeoDetectado) { this.estadoRostro = 'falta_parpadeo'; return; }
                     }

                     this.estadoRostro = 'ok';
                 } catch (e) { /* ignorar errores de detección */ }
             // 250ms (antes 500ms): un parpadeo dura ~100-400ms, a 500ms se
             // perdía la fase de ojos cerrados y la prueba de vida casi nunca
             // se disparaba.
             }, 250);
         },

         iniciarDeteccionAccesorios() {
             if (this.intervaloAccesorios) clearInterval(this.intervaloAccesorios);
             this.intervaloAccesorios = setInterval(async () => {
                 if (this.fotoCapturada || this.revisandoAccesorios || this.verificandoAccesoriosVivo) return;
                 // También corre en 'falta_parpadeo', no solo en 'ok'. Con la
                 // captura automática, 'ok' dura una fracción de segundo (el
                 // instante del parpadeo) y dispara tomarFoto() de inmediato,
                 // que a su vez detiene este intervalo - así nunca alcanzaba a
                 // correr ni una vez y el aviso para quitarse las gafas quedó
                 // código muerto (bug real reportado por el usuario: dejó de
                 // pedir quitarse las gafas tras agregar la captura automática).
                 // Con el rostro ya bien encuadrado (aunque falte el parpadeo)
                 // es el momento correcto para revisar accesorios de todas
                 // formas - antes de pedir el parpadeo, no después.
                 if (this.estadoRostro !== 'ok' && this.estadoRostro !== 'falta_parpadeo') return;
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
                 // Se exige parpadear de nuevo: como la captura ahora es automática
                 // y no hay botón manual, sin este reset el usuario quedaría con el
                 // rostro en estado ok pero sin ninguna forma de reintentar la foto.
                 this.parpadeoDetectado  = false;
                 this.ojosCerradosPrevio = false;
                 this.iniciarDeteccion();
                 this.iniciarDeteccionAccesorios();
             } else {
                 this.fotoCapturada = foto;
                 $wire.$set('{{ $wireTargetPath }}', foto);
             }
         },

         volverATomarFoto() {
             this.fotoCapturada           = null;
             this.alertaAccesorios        = '';
             this.estadoRostro            = 'esperando';
             // La prueba de vida se exige de nuevo en cada intento de captura.
             this.parpadeoDetectado       = false;
             this.ojosCerradosPrevio      = false;
             this.revisandoAccesorios     = false;
             this.verificandoAccesoriosVivo = false;
             this.detenerDeteccion();
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
     x-init="
        @if($wizardStepId)
            if (step === {{ Illuminate\Support\Js::from($wizardStepId) }}) { iniciarCamara() }
            $watch('step', (valor) => {
                if (valor === {{ Illuminate\Support\Js::from($wizardStepId) }}) { iniciarCamara() }
                else { detenerCamara() }
            })
        @else
            iniciarCamara()
        @endif
     "
     @modal-closed.window="detenerCamara()">

    <div class="space-y-3">

        {{-- ══ Autorización de datos personales (Ley 1581 de 2012) - obligatoria.
             Mismo lenguaje visual que "Declaración del Autorizador" (el
             Placeholder justo arriba en este modal), para que ambas tarjetas
             de declaración/consentimiento se lean como una misma familia. ══ --}}
        <div x-show="!disclaimerAceptado">
            <div class="wca-card">
                <p class="wca-card-label">
                    <svg style="width:12px;height:12px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.746 3.746 0 0121 12z"/>
                    </svg>
                    Autorización de tratamiento de datos personales
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;color:var(--wca-text);">{{ $disclaimerTexto }}</p>
            </div>

            <label class="wca-consent">
                <input type="checkbox" x-model="disclaimerMarcado" style="margin-top:2px;flex-shrink:0;accent-color:var(--wca-brand-solid);">
                <span style="font-size:13px;line-height:1.55;color:var(--wca-text);">
                    He leído y acepto la autorización de tratamiento de mis datos personales.
                </span>
            </label>

            <div style="margin-top:14px;">
                <button type="button"
                        :disabled="!disclaimerMarcado"
                        @click.prevent="aceptarDisclaimer()"
                        :class="disclaimerMarcado ? 'wca-btn-primary wca-btn-solid' : 'wca-btn-primary wca-btn-off'">
                    <svg style="width:15px;height:15px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    Aceptar y activar la cámara
                </button>
            </div>
        </div>

        {{-- ══ Error de cámara ══ --}}
        <div x-show="disclaimerAceptado && errorCamara" style="display:none">
            <div style="padding:14px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.30);border-radius:10px;">
                <p style="font-weight:700;color:#f87171;margin:0 0 6px;font-size:13px;">No se puede acceder a la cámara</p>
                <p style="color:var(--wca-text-muted);font-size:12px;margin:0 0 4px;">Para continuar permita el acceso en el navegador:</p>
                <ul style="color:var(--wca-list-color);font-size:12px;margin:0;padding-left:18px;line-height:1.8;">
                    <li>Busque el ícono de cámara en la barra de direcciones</li>
                    <li>Haga clic y seleccione "Permitir"</li>
                </ul>
            </div>
        </div>

        <div x-show="disclaimerAceptado && !errorCamara" style="display:none">

            {{-- ══ VISOR DE CÁMARA - siempre en DOM (x-show, no x-if) ══ --}}
            <div x-show="!fotoCapturada" style="display:none" class="space-y-3">

                {{-- Video + overlay oval (fondo siempre negro - badges en blanco son correctos).
                     max-width la limita en pantallas de escritorio (el contenedor del wizard
                     puede ser mucho más ancho que un recuadro 4:3 cómodo); en móvil, más angosto
                     que el tope, simplemente ocupa el 100% de su contenedor como antes. --}}
                <div style="position:relative;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:4/3;max-width:480px;margin:0 auto;">
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

                    {{-- Cargando modelos (sobre video - blanco correcto) --}}
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

                    {{-- Estado del rostro (sobre video - fondos opacos, blanco correcto) --}}
                    <div x-show="modelsCargados"
                         style="position:absolute;bottom:10px;left:0;right:0;display:flex;justify-content:center;padding:0 8px;">

                        <span x-show="estadoRostro === 'sin_rostro'"
                              class="wca-badge" style="background:rgba(220,38,38,0.85);color:white;display:none;">
                            No se detecta un rostro - acérquese y mire de frente
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
                        <span x-show="estadoRostro === 'falta_parpadeo'"
                              class="wca-badge" style="background:rgba(225,29,72,0.90);color:white;display:none;">
                            <svg style="width:11px;height:11px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Parpadee - la foto se tomará sola
                        </span>
                        <span x-show="estadoRostro === 'ok' && !alertaAccesorios"
                              class="wca-badge" style="background:rgba(22,101,52,0.85);color:#86efac;display:none;">
                            <svg style="width:11px;height:11px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Parpadeo confirmado - capturando
                        </span>
                        <span x-show="estadoRostro === 'ok' && alertaAccesorios"
                              x-text="alertaAccesorios"
                              class="wca-badge" style="background:rgba(180,83,9,0.90);color:white;max-width:280px;text-align:center;white-space:normal;display:none;">
                        </span>
                        <span x-show="estadoRostro === 'sin_modelo'"
                              class="wca-badge" style="background:rgba(75,85,99,0.88);color:white;display:none;">
                            Verificación automática no disponible - puede tomar la foto
                        </span>
                    </div>
                </div>

                {{-- Alerta de accesorios (fuera del video - usa variables de modo) --}}
                <div x-show="alertaAccesorios"
                     style="display:none;padding:10px 14px;background:rgba(180,83,9,0.15);border:1px solid rgba(251,191,36,0.35);border-radius:10px;">
                    <div style="display:flex;align-items:flex-start;gap:8px;">
                        <svg style="width:16px;height:16px;color:#d97706;flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p x-text="alertaAccesorios" style="font-size:12px;color:var(--wca-alert-text);margin:0;line-height:1.5;"></p>
                    </div>
                </div>

                {{-- Canvas oculto para captura --}}
                <canvas x-ref="canvas" style="display:none;"></canvas>

                {{-- Aviso de captura automática (cuando la detección está activa) --}}
                <div x-show="estadoRostro !== 'sin_modelo'" style="display:none;text-align:center;">
                    <p style="margin:0;font-size:12px;color:var(--wca-text-muted);line-height:1.5;">
                        La foto se toma automáticamente al detectar su parpadeo. No hay que presionar nada.
                    </p>
                </div>

                {{-- Botón tomar foto - solo como respaldo cuando la verificación
                     automática no está disponible (face-api no cargó). Con detección
                     activa la captura es automática al parpadear: dejar además un
                     botón manual permitiría saltarse la prueba de vida. --}}
                <div x-show="estadoRostro === 'sin_modelo'" style="display:none;text-align:center;">
                    <button type="button"
                            :disabled="!!alertaAccesorios || revisandoAccesorios"
                            @click.prevent="tomarFoto()"
                            :class="(!alertaAccesorios && !revisandoAccesorios)
                                ? 'wca-btn-primary wca-btn-on'
                                : 'wca-btn-primary wca-btn-off'">

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
                <div style="position:relative;border-radius:12px;overflow:hidden;border:2px solid #4ade80;aspect-ratio:4/3;max-width:480px;margin:0 auto;">
                    <img :src="fotoCapturada" style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);" alt="Foto de verificación"/>
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
</div>
