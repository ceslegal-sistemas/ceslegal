{{--
    Camara simple SOLO para capturar una foto de referencia - a diferencia
    de webcam-autorizador.blade.php y verificacion-facial-descargos.blade.php,
    NO carga face-api.js ni detecta parpadeo/vida (decision del usuario: aqui
    no hay el mismo riesgo legal que en una diligencia de descargos, el
    objetivo es solo tener una foto de referencia confiable).

    wire:key en el elemento raiz: este partial se incluye desde una rama
    @elseif($etapa === 'foto') que cambia a otras etapas - sin wire:key,
    morphdom puede dejar el scope de Alpine sin inicializar al volver a esta
    etapa (mismo bug real ya ocurrido en emitir-sancion-pasos.blade.php y
    verificacion-facial-descargos.blade.php).
--}}
<div wire:key="foto-simple-captura" x-data="{
        stream: null,
        fotoCapturada: null,
        errorCamara: false,
        async iniciarCamara() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
                });
                this.$refs.video.srcObject = this.stream;
                this.errorCamara = false;
            } catch (e) {
                this.errorCamara = true;
            }
        },
        detenerCamara() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
        },
        capturar() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.fotoCapturada = canvas.toDataURL('image/jpeg', 0.80);
            this.detenerCamara();
        },
        repetir() {
            this.fotoCapturada = null;
            this.iniciarCamara();
        },
        confirmar() {
            $wire.guardarFotoSimple(this.fotoCapturada);
        }
     }"
     x-init="iniciarCamara()"
     x-on:beforeunload.window="detenerCamara()"
     class="space-y-4">

    <p class="text-sm text-gray-600">Tómate una foto para tu registro.</p>

    <div x-show="errorCamara" class="bg-danger-50 border border-danger-200 rounded-xl p-4 text-sm text-danger-700">
        No pudimos acceder a tu cámara. Verifica los permisos del navegador e intenta de nuevo.
    </div>

    <div x-show="!fotoCapturada" class="rounded-xl overflow-hidden bg-black">
        <video x-ref="video" autoplay playsinline muted class="w-full"></video>
    </div>
    <canvas x-ref="canvas" class="hidden"></canvas>

    <img x-show="fotoCapturada" x-bind:src="fotoCapturada" class="w-full rounded-xl" />

    <div class="flex gap-3">
        <button type="button" x-show="!fotoCapturada" x-on:click="capturar()" class="w-full bg-primary-600 text-white font-semibold rounded-xl py-3">
            Tomar foto
        </button>
        <button type="button" x-show="fotoCapturada" x-on:click="repetir()" class="w-1/2 bg-gray-200 text-gray-700 font-semibold rounded-xl py-3">
            Repetir
        </button>
        <button type="button" x-show="fotoCapturada" x-on:click="confirmar()" class="w-1/2 bg-primary-600 text-white font-semibold rounded-xl py-3">
            Continuar
        </button>
    </div>
</div>
