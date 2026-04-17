<div class="space-y-4">

    {{-- ── Enlace de acceso ── --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
            Enlace de acceso para el trabajador
        </p>

        {{-- Toque único selecciona todo gracias a user-select:all --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 mb-1">
            <p
                class="font-mono text-xs text-gray-800 dark:text-gray-100 break-all leading-relaxed"
                style="user-select:all; -webkit-user-select:all; cursor:text;"
            >{{ $url }}</p>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">
            Toca el enlace para seleccionarlo todo · o usa el botón
        </p>
    </div>

    {{-- ── Botón copiar principal ── --}}
    <button
        type="button"
        onclick="
            const url = @js($url);
            const btn = this;
            const orig = btn.innerHTML;
            function ok() {
                btn.innerHTML = '<span>✓ ¡Copiado!</span>';
                btn.classList.remove('bg-primary-600','hover:bg-primary-700');
                btn.classList.add('bg-success-600');
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.classList.add('bg-primary-600','hover:bg-primary-700');
                    btn.classList.remove('bg-success-600');
                }, 2500);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(ok).catch(ok);
            } else {
                ok();
            }
        "
        class="w-full py-3 text-base font-semibold rounded-xl bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white transition-colors"
    >
        <span>Copiar enlace completo</span>
    </button>

    {{-- ── Info del token ── --}}
    <div class="grid grid-cols-2 gap-3 text-sm pt-1 border-t border-gray-100 dark:border-gray-700">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Acceso habilitado</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $diligencia->acceso_habilitado ? 'Sí' : 'No' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Fecha permitida</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') ?? '—' }}</p>
        </div>
        <div class="col-span-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">Token expira</p>
            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $diligencia->token_expira_en?->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
    </div>

    {{-- ── Estado de acceso ── --}}
    @if($diligencia->trabajador_accedio_en)
        <div class="rounded-xl bg-success-50 dark:bg-success-900/20 p-3 border border-success-200 dark:border-success-800">
            <p class="text-sm font-semibold text-success-800 dark:text-success-300">Trabajador ya accedió</p>
            <p class="text-xs text-success-700 dark:text-success-400 mt-0.5">
                {{ $diligencia->trabajador_accedio_en->format('d/m/Y H:i') }} &middot; IP: {{ $diligencia->ip_acceso }}
            </p>
        </div>
    @else
        <div class="rounded-xl bg-warning-50 dark:bg-warning-900/20 p-3 border border-warning-200 dark:border-warning-800">
            <p class="text-sm text-warning-800 dark:text-warning-300">El trabajador aún no ha accedido.</p>
        </div>
    @endif

</div>
