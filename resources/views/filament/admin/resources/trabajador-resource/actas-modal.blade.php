{{--
    Modal "Ver Actas" del historial de aceptaciones del RIT de un trabajador
    (evidencia jurídica, 2026-09-28) - se muestra cuando hay 2+ actas
    generadas: cada actualización del RIT exige una nueva aceptación, así
    que con el tiempo un mismo trabajador acumula varias actas históricas.

    Variables esperadas:
      $actas ( Collection<AceptacionReglamentoInterno> ) - ya ordenadas desc
      $trabajador (App\Models\Trabajador)
--}}
<div class="space-y-3">
    @foreach($actas as $i => $acta)
        <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl border {{ $i === 0 ? 'border-primary-300 bg-primary-50/40' : 'border-gray-200 bg-gray-50' }}">
            <div>
                <p class="text-sm font-semibold text-gray-900 m-0">
                    {{ $acta->aceptado_en?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm A') }}
                    @if($i === 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-success-100 text-success-700" style="margin-left:.4rem">Vigente</span>
                    @endif
                </p>
                <p class="text-xs text-gray-500 m-0 mt-0.5 font-mono">SHA-256: {{ \Illuminate\Support\Str::limit($acta->texto_rit_hash, 24, '…') }}</p>
            </div>
            <a href="{{ route('trabajador.acta-rit.descargar', ['trabajador' => $trabajador->id, 'aceptacion' => $acta->id]) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-lg transition-colors flex-shrink-0">
                @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                Descargar
            </a>
        </div>
    @endforeach
</div>
