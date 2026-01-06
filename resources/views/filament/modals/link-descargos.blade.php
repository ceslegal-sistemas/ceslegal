<div class="space-y-4">
    <div class="rounded-lg bg-gray-50 p-4">
        <p class="text-sm text-gray-600 mb-2">Link de acceso para el trabajador:</p>
        <div class="flex items-center gap-2">
            <input
                type="text"
                value="{{ $url }}"
                readonly
                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                onclick="this.select()"
            />
            <button
                type="button"
                onclick="
                    const input = this.previousElementSibling;
                    input.select();
                    document.execCommand('copy');
                    const btn = this;
                    const originalText = btn.textContent;
                    btn.textContent = '¡Copiado!';
                    btn.classList.add('bg-success-600');
                    btn.classList.remove('bg-primary-600');
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.classList.remove('bg-success-600');
                        btn.classList.add('bg-primary-600');
                    }, 2000);
                "
                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700"
            >
                Copiar
            </button>
        </div>
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
            <strong>Nota:</strong> El trabajador solo podrá acceder el día programado ({{ $diligencia->fecha_acceso_permitida?->format('d/m/Y') }})
            y antes de que expire el token ({{ $diligencia->token_expira_en?->format('d/m/Y') }}).
        </p>
    </div>
</div>
