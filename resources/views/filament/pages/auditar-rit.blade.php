<x-filament-panels::page>

<style>
.rit-audit-card{border-radius:1rem;padding:1.5rem;background:#fff;border:1px solid rgba(0,0,0,.07);box-shadow:0 2px 16px rgba(0,0,0,.06)}
.dark .rit-audit-card{background:#1e2535;border-color:rgba(255,255,255,.06)}
.seccion-card{border-radius:.75rem;padding:1.25rem;border-left:4px solid;margin-bottom:.75rem}
.seccion-completo{border-color:#22c55e;background:#f0fdf4}.dark .seccion-completo{background:#052e16}
.seccion-parcial{border-color:#f59e0b;background:#fffbeb}.dark .seccion-parcial{background:#1c1505}
.seccion-ausente{border-color:#ef4444;background:#fef2f2}.dark .seccion-ausente{background:#1c0505}
.score-ring{width:90px;height:90px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;border:6px solid}
.score-success{border-color:#22c55e;color:#22c55e}
.score-warning{border-color:#f59e0b;color:#f59e0b}
.score-danger{border-color:#ef4444;color:#ef4444}
.progress-step{display:flex;align-items:center;gap:.75rem;padding:.5rem 0}
.step-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.step-done{background:#22c55e}
.step-pending{background:#d1d5db}
.step-processing{background:#3b82f6;animation:pulse 1s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
</style>

@php
    $tieneAuditoria = $auditoria && $auditoria->estado !== 'pendiente';
    $secciones      = $auditoria?->secciones ?? [];
    $numCompletadas = count($secciones);
    $numTotal       = $this->getNumSecciones();
    $progreso       = $numTotal > 0 ? round($numCompletadas / $numTotal * 100) : 0;
    $titulos        = \App\Services\AuditoriaRITService::getTitulosSecciones();
    $colorScore     = $auditoria?->color_score ?? 'danger';
@endphp

{{-- ── HEADER ── --}}
<div class="rit-audit-card mb-6">
    <div class="flex items-start gap-4">
        <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
            <x-heroicon-o-magnifying-glass-circle class="w-8 h-8 text-indigo-600 dark:text-indigo-400"/>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Auditoría Legal del Reglamento Interno</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Revisión exhaustiva sección por sección contra la normativa vigente colombiana
                (Código Sustantivo del Trabajo, Ley 1010/2006, Ley 2365/2024 y jurisprudencia de la biblioteca legal).
            </p>
            @if($empresa)
                <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mt-2">
                    {{ $empresa->razon_social }}
                </p>
            @endif
        </div>
    </div>
</div>

{{-- ── ESTADO: EN PROCESO ── --}}
@if($procesando && $auditoria)
<div class="rit-audit-card mb-6">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-5 h-5 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="font-semibold text-gray-800 dark:text-white">Revisando su reglamento...</span>
        <span class="ml-auto text-sm text-gray-500">{{ $numCompletadas }}/{{ $numTotal }} secciones</span>
    </div>

    {{-- Barra de progreso --}}
    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 mb-4">
        <div class="bg-indigo-500 h-2 rounded-full transition-all duration-500" style="width: {{ $progreso }}%"></div>
    </div>

    {{-- Pasos --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
        @foreach($titulos as $clave => $titulo)
            @php
                $done       = isset($secciones[$clave]);
                $processing = !$done && $numCompletadas === array_search($clave, array_keys($titulos));
                $dotClass   = $done ? 'step-done' : ($processing ? 'step-processing' : 'step-pending');
            @endphp
            <div class="progress-step">
                <div class="step-dot {{ $dotClass }}"></div>
                <span class="text-sm {{ $done ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">
                    {{ $titulo }}
                    @if($done)
                        <span class="text-xs text-green-600 dark:text-green-400 ml-1">✓</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── ESTADO: COMPLETADO ── --}}
@if($auditoria && $auditoria->estado === 'completado')
<div class="mb-6">

    {{-- Score general --}}
    <div class="rit-audit-card mb-4 flex items-center gap-6">
        <div class="score-ring score-{{ $colorScore }}">
            {{ $auditoria->score }}
        </div>
        <div>
            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Resultado de la Auditoría</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if($auditoria->score >= 80) Reglamento en buen estado jurídico
                @elseif($auditoria->score >= 50) Reglamento con observaciones importantes
                @else Reglamento requiere actualización urgente
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-1">
                Auditado el {{ $auditoria->updated_at->format('d/m/Y \a \l\a\s g:i A') }}
                · {{ $auditoria->fuente === 'externo' ? 'Documento externo' : 'RIT generado en el sistema' }}
            </p>
        </div>
        <div class="ml-auto">
            <x-filament::button wire:click="nuevaAuditoria" color="gray" size="sm">
                Nueva auditoría
            </x-filament::button>
        </div>
    </div>

    {{-- Resumen general --}}
    @if($auditoria->resumen_general)
    <div class="rit-audit-card mb-4">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-2 flex items-center gap-2">
            <x-heroicon-o-document-text class="w-4 h-4 text-indigo-500"/>
            Resumen Ejecutivo
        </h4>
        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $auditoria->resumen_general }}</p>
    </div>
    @endif

    {{-- Resultados por sección --}}
    <div class="rit-audit-card">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <x-heroicon-o-list-bullet class="w-4 h-4 text-indigo-500"/>
            Detalle por Sección
        </h4>

        @foreach($secciones as $clave => $sec)
            @php
                $calif    = $sec['calificacion'] ?? 'Ausente';
                $cardCls  = match($calif) {
                    'Completo' => 'seccion-completo',
                    'Parcial'  => 'seccion-parcial',
                    default    => 'seccion-ausente',
                };
                $icon = match($calif) {
                    'Completo' => '✓',
                    'Parcial'  => '!',
                    default    => '✗',
                };
                $iconColor = match($calif) {
                    'Completo' => 'text-green-600',
                    'Parcial'  => 'text-amber-600',
                    default    => 'text-red-600',
                };
            @endphp
            <div class="seccion-card {{ $cardCls }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="font-bold {{ $iconColor }}">{{ $icon }}</span>
                        <h5 class="font-semibold text-gray-800 dark:text-white text-sm">{{ $sec['titulo'] ?? $clave }}</h5>
                    </div>
                    <div class="flex items-center gap-3">
                        @if(!($sec['seccion_encontrada'] ?? true))
                            <span class="text-xs text-gray-400 italic">No encontrado en el RIT</span>
                        @endif
                        <span class="text-xs font-bold {{ $iconColor }}">{{ $sec['score'] ?? 0 }}/100</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                            {{ $calif === 'Completo' ? 'bg-green-100 text-green-700 dark:bg-green-900/30' : ($calif === 'Parcial' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30' : 'bg-red-100 text-red-700 dark:bg-red-900/30') }}">
                            {{ $calif }}
                        </span>
                    </div>
                </div>

                @if(!empty($sec['hallazgos']))
                <div class="mt-2">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Hallazgos</p>
                    <ul class="space-y-1">
                        @foreach($sec['hallazgos'] as $hallazgo)
                            <li class="text-xs text-gray-700 dark:text-gray-300 flex gap-1.5">
                                <span class="shrink-0 mt-0.5 text-gray-400">›</span>{{ $hallazgo }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(!empty($sec['recomendaciones']))
                <div class="mt-2">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Recomendaciones</p>
                    <ul class="space-y-1">
                        @foreach($sec['recomendaciones'] as $rec)
                            <li class="text-xs text-gray-700 dark:text-gray-300 flex gap-1.5">
                                <span class="shrink-0 mt-0.5 text-indigo-400">→</span>{{ $rec }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(!empty($sec['articulos_referencia']))
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach($sec['articulos_referencia'] as $art)
                        <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-mono">{{ $art }}</span>
                    @endforeach
                </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

{{-- ── ESTADO: ERROR ── --}}
@elseif($auditoria && $auditoria->estado === 'error')
<div class="rit-audit-card mb-6 border-red-200 dark:border-red-800">
    <div class="flex items-center gap-3 mb-2">
        <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-500"/>
        <span class="font-semibold text-red-700 dark:text-red-400">Error en la auditoría</span>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $auditoria->mensaje_error }}</p>
    <div class="mt-3">
        <x-filament::button wire:click="nuevaAuditoria" color="gray" size="sm">
            Intentar nuevamente
        </x-filament::button>
    </div>
</div>
@endif

{{-- ── FORMULARIO: INICIAR AUDITORÍA ── --}}
@if(!$procesando && (!$auditoria || $auditoria->estado === 'error' || !$tieneAuditoria))
<div class="rit-audit-card">
    <h3 class="font-semibold text-gray-800 dark:text-white mb-1">Iniciar Auditoría</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        @if($rit && !empty($rit->texto_completo))
            Se auditará el RIT registrado en el sistema
            <span class="font-medium text-gray-700 dark:text-gray-300">({{ $rit->updated_at->format('d/m/Y') }})</span>.
            También puede subir un documento externo para auditarlo en su lugar.
        @else
            No hay RIT en el sistema. Suba el documento de su empresa para auditarlo.
        @endif
    </p>

    <form wire:submit="iniciarAuditoria">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass-circle">
                Iniciar Auditoría Legal
            </x-filament::button>
        </div>
    </form>
</div>
@endif

</x-filament-panels::page>
