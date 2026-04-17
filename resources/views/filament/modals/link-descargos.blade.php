<div class="space-y-4">
    {{-- URL completa almacenada en un atributo data para evitar truncamiento del input --}}
    <div class="rounded-lg bg-gray-50 p-4" data-full-url="{{ $url }}">
        <p class="text-sm text-gray-600 mb-2">Link de acceso para el trabajador:</p>
        <div class="flex items-center gap-2">
            <input
                type="text"
                value="{{ $url }}"
                readonly
                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                onclick="this.select(); this.setSelectionRange(0, 99999);"
            />
            <button
                type="button"
                onclick="
                    const fullUrl = this.closest('[data-full-url]').dataset.fullUrl;
                    const btn = this;
                    function mostrarCopiado() {
                        btn.textContent = '¡Copiado!';
                        btn.classList.add('bg-success-600');
                        btn.classList.remove('bg-primary-600');
                        setTimeout(() => {
                            btn.textContent = 'Copiar';
                            btn.classList.remove('bg-success-600');
                            btn.classList.add('bg-primary-600');
                        }, 2000);
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(fullUrl).then(mostrarCopiado).catch(() => {
                            const input = this.previousElementSibling;
                            input.select(); input.setSelectionRange(0, 99999);
                            document.execCommand('copy');
                            mostrarCopiado();
                        });
                    } else {
                        const input = this.previousElementSibling;
                        input.select(); input.setSelectionRange(0, 99999);
                        document.execCommand('copy');
                        mostrarCopiado();
                    }
                "
                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700"
            >
                Copiar
            </button>
        </div>
        {{-- Botón compartir nativo (visible solo en móviles con Web Share API) --}}
        <button
            type="button"
            id="btn-compartir-link"
            onclick="
                const fullUrl = this.closest('.rounded-lg').dataset.fullUrl;
                if (navigator.share) {
                    navigator.share({ title: 'Descargos', url: fullUrl });
                }
            "
            class="mt-2 w-full inline-flex items-center justify-center gap-2 px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            style="display:none"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
            Compartir enlace
        </button>
        <script>
            // Mostrar botón compartir solo si el dispositivo lo soporta (móviles)
            if (navigator.share) {
                document.getElementById('btn-compartir-link').style.display = 'flex';
            }
        </script>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="font-semibold text-gray-700">Token de Acceso:</p>
            <p class="text-gray-600 font-mono text-xs break-all">{{ $diligencia->token_acceso }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-700">Token Expira:</p>
            <p class="text-gray-600">{{ $diligencia->token_expira_en?->format('d/m/Y H:i') ?? 'N/A' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-700">Fecha Permitida:</p>
            <p class="text-gray-600">{{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') ?? 'N/A' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-700">Acceso Habilitado:</p>
            <p class="text-gray-600">{{ $diligencia->acceso_habilitado ? 'Sí' : 'No' }}</p>
        </div>
    </div>

    @if($diligencia->trabajador_accedio_en)
    <div class="rounded-lg bg-green-50 p-4 border border-green-200">
        <p class="text-sm font-semibold text-green-800">Trabajador ya accedió</p>
        <p class="text-sm text-green-700">
            Fecha y hora: {{ $diligencia->trabajador_accedio_en->format('d/m/Y H:i') }}<br>
            IP: {{ $diligencia->ip_acceso }}
        </p>
    </div>
    @else
    <div class="rounded-lg bg-yellow-50 p-4 border border-yellow-200">
        <p class="text-sm text-yellow-800">El trabajador aún no ha accedido al formulario.</p>
    </div>
    @endif

    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <p class="text-sm text-blue-800">
            <strong>Nota:</strong> El trabajador podrá acceder desde el día programado
            ({{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') }})
            hasta que expire el token ({{ $diligencia->token_expira_en?->format('d/m/Y') }}).
        </p>
    </div>
</div>
