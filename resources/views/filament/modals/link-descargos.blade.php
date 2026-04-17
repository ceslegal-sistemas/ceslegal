<div class="space-y-4">
    {{-- URL del enlace --}}
    <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Link de acceso para el trabajador:</p>
        <div class="flex gap-2">
            <textarea
                readonly
                rows="2"
                onclick="this.select(); this.setSelectionRange(0, 99999);"
                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm p-2 resize-none font-mono break-all"
            >{{ $url }}</textarea>
            <button
                type="button"
                onclick="
                    const url = @js($url);
                    const btn = this;
                    function ok() {
                        btn.textContent = '✓';
                        setTimeout(() => { btn.textContent = 'Copiar'; }, 2000);
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(ok).catch(() => {
                            document.execCommand('copy'); ok();
                        });
                    } else {
                        const ta = btn.previousElementSibling;
                        ta.select(); ta.setSelectionRange(0, 99999);
                        document.execCommand('copy'); ok();
                    }
                "
                class="self-start px-3 py-2 text-sm font-medium rounded-lg bg-primary-600 hover:bg-primary-700 text-white whitespace-nowrap"
            >Copiar</button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">También puede seleccionar el texto y copiarlo manualmente.</p>
    </div>

    {{-- Detalles del token --}}
    <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <p class="font-semibold text-gray-600 dark:text-gray-400">Token Expira:</p>
            <p class="text-gray-900 dark:text-gray-100">{{ $diligencia->token_expira_en?->format('d/m/Y H:i') ?? 'N/A' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-600 dark:text-gray-400">Fecha Permitida:</p>
            <p class="text-gray-900 dark:text-gray-100">{{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') ?? 'N/A' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-600 dark:text-gray-400">Acceso Habilitado:</p>
            <p class="text-gray-900 dark:text-gray-100">{{ $diligencia->acceso_habilitado ? 'Sí' : 'No' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-600 dark:text-gray-400">Token (primeros 16):</p>
            <p class="text-gray-900 dark:text-gray-100 font-mono text-xs">{{ substr($diligencia->token_acceso ?? '', 0, 16) }}…</p>
        </div>
    </div>

    {{-- Estado de acceso --}}
    @if($diligencia->trabajador_accedio_en)
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3 border border-green-200 dark:border-green-800">
            <p class="text-sm font-semibold text-green-800 dark:text-green-300">Trabajador ya accedió</p>
            <p class="text-sm text-green-700 dark:text-green-400">
                {{ $diligencia->trabajador_accedio_en->format('d/m/Y H:i') }} — IP: {{ $diligencia->ip_acceso }}
            </p>
        </div>
    @else
        <div class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-3 border border-yellow-200 dark:border-yellow-800">
            <p class="text-sm text-yellow-800 dark:text-yellow-300">El trabajador aún no ha accedido al formulario.</p>
        </div>
    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Acceso disponible desde <strong>{{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') }}</strong>
        hasta <strong>{{ $diligencia->token_expira_en?->format('d/m/Y') }}</strong>.
    </p>
</div>
