@once
<style>
    .hca-btn {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        font-size: .75rem;
        padding: .3rem .75rem;
        border-radius: .375rem;
        border: 1px solid rgba(99,102,241,.25);
        color: #94a3b8;
        background: transparent;
        cursor: pointer;
        transition: color .15s, border-color .15s, background .15s;
        line-height: 1;
    }
    .hca-btn:disabled { opacity: .5; cursor: default; }
    .hca-btn:not(:disabled):hover { color: #a5b4fc; border-color: rgba(99,102,241,.5); }
    .hca-btn.hca-recording {
        color: #f87171;
        border-color: rgba(239,68,68,.4);
        background: rgba(239,68,68,.07);
    }
    .hca-btn.hca-transcribing {
        color: #a78bfa;
        border-color: rgba(139,92,246,.35);
        background: rgba(139,92,246,.07);
    }
    .hca-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
    .hca-pulse {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: #ef4444;
        animation: hca-blink 1s ease-in-out infinite;
    }
    @keyframes hca-blink { 0%,100%{opacity:1} 50%{opacity:.25} }
    @keyframes hca-spin { to { transform: rotate(360deg); } }
    .hca-spin { animation: hca-spin .8s linear infinite; }

    html:not(.dark) .hca-btn       { color: #6b7280; border-color: rgba(99,102,241,.2); }
    html:not(.dark) .hca-btn:not(:disabled):hover { color: #4f46e5; border-color: rgba(99,102,241,.45); }
    html:not(.dark) .hca-btn.hca-recording { color: #dc2626; border-color: rgba(220,38,38,.4); background: rgba(220,38,38,.05); }
    html:not(.dark) .hca-btn.hca-transcribing { color: #7c3aed; border-color: rgba(124,58,237,.35); background: rgba(124,58,237,.05); }
</style>

<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('micDictar', function () {
            return {
                recording: false,
                transcribing: false,
                useMediaRecorder: false,
                supported: true,
                recognition: null,
                mediaRecorder: null,
                audioChunks: [],
                feedbackTimer: null,

                init() {
                    // SpeechRecognition solo funciona en Chrome y Edge reales
                    // Opera tiene webkitSpeechRecognition pero falla al conectarse
                    var isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);
                    var isEdge   = /Edg\//.test(navigator.userAgent);
                    var hasSpeech = ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) && (isChrome || isEdge);
                    var hasMedia  = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

                    this.useMediaRecorder = !hasSpeech;
                    this.supported = hasSpeech || hasMedia;
                    console.log('[MIC] isChrome:', isChrome, 'isEdge:', isEdge, 'hasSpeech:', hasSpeech, 'hasMedia:', hasMedia, 'useMediaRecorder:', this.useMediaRecorder);

                    if (hasSpeech) {
                        this.initSpeechRecognition();
                    }

                    // Feedback en tiempo real mientras escribe (debounce 5s)
                    var self = this;
                    this.$wire.$watch('data.descripcion_hecho', function (val) {
                        if (self.recording || self.transcribing) return;
                        if (self.feedbackTimer) clearTimeout(self.feedbackTimer);
                        val = (val || '').trim();
                        if (val.length < 60) return;
                        self.feedbackTimer = setTimeout(function () {
                            self.$wire.call('obtenerFeedbackVoz');
                        }, 5000);
                    });
                },

                initSpeechRecognition() {
                    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                    this.recognition = new SR();
                    this.recognition.lang = 'es-CO';
                    this.recognition.continuous = true;
                    this.recognition.interimResults = false;
                    var self = this;
                    this.recognition.onresult = function (e) {
                        var transcript = '';
                        for (var i = e.resultIndex; i < e.results.length; i++) {
                            if (e.results[i].isFinal) transcript += e.results[i][0].transcript + ' ';
                        }
                        if (transcript.trim()) {
                            var current = self.$wire.get('data.descripcion_hecho') || '';
                            var joined  = (current.trim() + ' ' + transcript).trim();
                            self.$wire.set('data.descripcion_hecho', joined);
                        }
                    };
                    this.recognition.onend = function () {
                        self.recording = false;
                        var texto = (self.$wire.get('data.descripcion_hecho') || '').trim();
                        if (texto.length >= 30) {
                            self.$wire.call('obtenerFeedbackVoz');
                        }
                    };
                    this.recognition.onerror = function () { self.recording = false; };
                },

                toggle() {
                    if (!this.supported) {
                        alert('Micrófono no disponible. Verifique los permisos del navegador.');
                        return;
                    }
                    if (this.useMediaRecorder) {
                        this.toggleMediaRecorder();
                    } else {
                        this.toggleSpeechRecognition();
                    }
                },

                toggleSpeechRecognition() {
                    if (this.recording) {
                        this.recognition.stop();
                    } else {
                        this.recognition.start();
                        this.recording = true;
                    }
                },

                toggleMediaRecorder() {
                    if (this.recording) {
                        console.log('[MIC] deteniendo MediaRecorder');
                        if (this.mediaRecorder) this.mediaRecorder.stop();
                        return;
                    }
                    var self = this;
                    console.log('[MIC] solicitando micrófono vía MediaRecorder...');
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(function (stream) {
                            console.log('[MIC] micrófono concedido, iniciando grabación');
                            self.audioChunks = [];
                            self.mediaRecorder = new MediaRecorder(stream);
                            console.log('[MIC] MIME type:', self.mediaRecorder.mimeType);
                            self.mediaRecorder.ondataavailable = function (e) {
                                if (e.data.size > 0) self.audioChunks.push(e.data);
                            };
                            self.mediaRecorder.onstop = function () {
                                stream.getTracks().forEach(function (t) { t.stop(); });
                                self.recording    = false;
                                self.transcribing = true;
                                console.log('[MIC] grabación terminada, chunks:', self.audioChunks.length);
                                self.enviarAudio();
                            };
                            self.mediaRecorder.start();
                            self.recording = true;
                        })
                        .catch(function (err) {
                            console.error('[MIC] error getUserMedia:', err);
                            alert('No se pudo acceder al micrófono.\nVerifique los permisos del navegador.');
                        });
                },

                enviarAudio() {
                    var self = this;
                    var mime = self.mediaRecorder.mimeType || 'audio/webm';
                    var blob = new Blob(self.audioChunks, { type: mime });
                    console.log('[STT] enviando audio, tamaño:', blob.size, 'tipo:', mime);
                    var reader = new FileReader();
                    reader.onloadend = function () {
                        var base64 = reader.result.split(',')[1];
                        var csrf   = document.querySelector('meta[name="csrf-token"]');
                        fetch('/transcribir', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf ? csrf.content : '',
                            },
                            body: JSON.stringify({ audio: base64, tipo: mime }),
                        })
                        .then(function (r) {
                            console.log('[STT] HTTP', r.status);
                            return r.json();
                        })
                        .then(function (data) {
                            console.log('[STT] respuesta:', data);
                            self.transcribing = false;
                            if (data.texto && data.texto.trim()) {
                                var current = (self.$wire.get('data.descripcion_hecho') || '').trim();
                                var joined  = current ? (current + ' ' + data.texto.trim()) : data.texto.trim();
                                self.$wire.set('data.descripcion_hecho', joined);
                                if (joined.length >= 30) {
                                    self.$wire.call('obtenerFeedbackVoz');
                                }
                            }
                        })
                        .catch(function (err) {
                            console.error('[STT] Error fetch:', err);
                            self.transcribing = false;
                        });
                    };
                    reader.readAsDataURL(blob);
                }
            };
        });
    });

    // ── Desbloquear audio en el primer gesto del usuario ─────────────────────
    var hcaAudioUnlocked = false;
    function hcaUnlockAudio() {
        if (hcaAudioUnlocked) return;
        hcaAudioUnlocked = true;
        // Crear y reproducir un AudioContext vacío para desbloquear autoplay
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var buf = ctx.createBuffer(1, 1, 22050);
            var src = ctx.createBufferSource();
            src.buffer = buf;
            src.connect(ctx.destination);
            src.start(0);
            ctx.resume().then(function () { ctx.close(); });
        } catch(e) {}
    }
    document.addEventListener('keydown', hcaUnlockAudio, { once: false, passive: true });
    document.addEventListener('touchstart', hcaUnlockAudio, { once: false, passive: true });

    // ── TTS: ElevenLabs con fallback al browser ──────────────────────────────
    function hcaHablar(texto) {
        if (!texto) { console.log('[TTS] sin texto'); return; }
        console.log('[TTS] intentando ElevenLabs para:', texto.substring(0, 60));

        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
            console.warn('[TTS] no se encontró <meta name="csrf-token">, usando browser TTS');
            hcaHablarBrowser(texto);
            return;
        }

        fetch('/tts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
            },
            body: JSON.stringify({ texto: texto }),
        })
        .then(function (res) {
            console.log('[TTS] HTTP', res.status, res.headers.get('content-type'));
            if (!res.ok) {
                return res.text().then(function (t) { throw new Error('HTTP ' + res.status + ': ' + t.substring(0, 200)); });
            }
            return res.blob();
        })
        .then(function (blob) {
            console.log('[TTS] blob OK, tamaño:', blob.size);
            var url   = URL.createObjectURL(blob);
            var audio = new Audio(url);
            var p     = audio.play();
            if (p) {
                p.then(function () { console.log('[TTS] reproduciendo'); })
                 .catch(function (e) {
                     console.error('[TTS] play() bloqueado:', e.name, e.message);
                     URL.revokeObjectURL(url);
                 });
            }
            audio.onended = function () { URL.revokeObjectURL(url); };
        })
        .catch(function (err) {
            console.error('[TTS] ElevenLabs falló:', err.message);
            hcaHablarBrowser(texto);
        });
    }

    function hcaHablarBrowser(texto) {
        console.log('[TTS] usando browser speechSynthesis');
        if (!window.speechSynthesis) { console.warn('[TTS] speechSynthesis no disponible'); return; }

        function hablarConVoz(voices) {
            window.speechSynthesis.cancel();

            // Preferir voces neurales/naturales de Microsoft en español
            var preferencias = [
                'Microsoft Raul Online',   // es-MX, neural masculino
                'Microsoft Jorge Online',  // es-ES, neural masculino
                'Microsoft Pablo Online',  // es-US, neural masculino
                'Microsoft Andres Online', // es-CO si existe
                'Google español',
            ];
            var voz = null;
            for (var i = 0; i < preferencias.length; i++) {
                voz = voices.find(function (v) { return v.name.indexOf(preferencias[i]) === 0; });
                if (voz) break;
            }
            // Fallback: cualquier voz española masculina online
            if (!voz) {
                voz = voices.find(function (v) { return v.lang.startsWith('es') && /online|neural/i.test(v.name); });
            }
            // Último fallback: cualquier voz española
            if (!voz) {
                voz = voices.find(function (v) { return v.lang.startsWith('es'); });
            }

            console.log('[TTS] voz seleccionada:', voz ? voz.name : '(por defecto)');

            var utt  = new SpeechSynthesisUtterance(texto);
            utt.lang = 'es-CO';
            utt.rate = 0.9;
            if (voz) utt.voice = voz;
            utt.onstart = function () { console.log('[TTS] browser: hablando con', voz ? voz.name : 'default'); };
            utt.onerror = function (e) { console.error('[TTS] browser error:', e.error); };
            window.speechSynthesis.speak(utt);
        }

        var voices = window.speechSynthesis.getVoices();
        if (voices.length) {
            hablarConVoz(voices);
        } else {
            // Las voces se cargan async la primera vez
            window.speechSynthesis.addEventListener('voiceschanged', function handler() {
                window.speechSynthesis.removeEventListener('voiceschanged', handler);
                hablarConVoz(window.speechSynthesis.getVoices());
            });
        }
    }

    window.addEventListener('hablar-feedback', function (event) {
        console.log('[TTS] evento hablar-feedback recibido', event.detail);
        var texto = (event.detail && event.detail.texto) ? event.detail.texto : '';
        hcaHablar(texto);
    });
</script>
@endonce

<div x-data="micDictar()" style="display:flex;align-items:center;gap:.625rem;margin-top:.125rem;flex-wrap:wrap;">

    {{-- Botón micrófono --}}
    <button type="button"
        @click="toggle()"
        :disabled="transcribing"
        :class="recording ? 'hca-recording' : (transcribing ? 'hca-transcribing' : '')"
        class="hca-btn"
        x-show="supported"
        :title="recording ? 'Clic para detener la grabación' : (transcribing ? 'Transcribiendo con IA...' : 'Dictar con voz')">

        {{-- Ícono micrófono (idle) --}}
        <svg x-show="!recording && !transcribing" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
        </svg>
        {{-- Ícono stop (grabando) --}}
        <svg x-show="recording" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z"/>
        </svg>
        {{-- Spinner (transcribiendo) --}}
        <svg x-show="transcribing" class="hca-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>

        <span x-text="recording ? 'Detener grabación' : (transcribing ? 'Transcribiendo...' : 'Dictar con voz')"></span>
        <span class="hca-pulse" x-show="recording" aria-hidden="true"></span>
    </button>

    {{-- Sin soporte --}}
    <span x-show="!supported" style="font-size:.72rem;color:#64748b;font-style:italic;">
        Micrófono no disponible
    </span>

    {{-- Hint modo --}}
    <span x-show="supported && !recording && !transcribing" style="font-size:.72rem;color:#475569;">
        <span x-show="!useMediaRecorder">Tiempo real · Chrome / Edge</span>
        <span x-show="useMediaRecorder">Grabación + IA · funciona en Opera / Firefox</span>
    </span>

</div>
