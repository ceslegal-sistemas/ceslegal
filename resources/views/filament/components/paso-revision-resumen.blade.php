@php
    $quienLabels = [
        'empleador'  => 'El empleador directamente',
        'supervisor' => 'Un supervisor o jefe inmediato',
        'rrhh'       => 'El área de Recursos Humanos',
        'compañero'  => 'Un compañero de trabajo',
        'cliente'    => 'Un cliente o proveedor',
        'otro'       => 'Otro',
    ];
    $lugarLabels = [
        'planta'         => 'Planta de producción',
        'oficina'        => 'Oficina',
        'sede_principal' => 'Sede principal',
        'bodega'         => 'Bodega / Almacén',
        'externo'        => 'Lugar externo a la empresa',
        'virtual'        => 'Entorno virtual / remoto',
        'otro'           => $lugar_libre ?? 'Otro',
    ];
    $evidenciasLabels = [
        'correo'             => 'Correo electrónico',
        'asistencia'         => 'Registro de asistencia',
        'camaras'            => 'Cámaras de seguridad',
        'documento'          => 'Documento interno',
        'reporte_supervisor' => 'Reporte del supervisor',
        'testigos'           => 'Testigos presenciales',
        'otro'               => 'Otro',
    ];
    $horarioLabels = ['si' => 'Sí', 'no' => 'No', 'parcial' => 'Parcialmente'];
    $trabajador = $trabajador_id ? \App\Models\Trabajador::find($trabajador_id) : null;
@endphp

<style>
    .prr-wrap   { margin-bottom: .25rem; }

    /* Estado banner */
    .prr-banner {
        display: flex; align-items: center; gap: .625rem;
        border-radius: .625rem; padding: .75rem 1rem;
        border: 1px solid; margin-bottom: 1rem;
        font-size: .8125rem; line-height: 1.5;
    }
    .prr-banner.pending {
        background: rgba(245,158,11,.07);
        border-color: rgba(245,158,11,.25);
        color: #fde68a;
    }
    .prr-banner.done {
        background: rgba(34,197,94,.07);
        border-color: rgba(34,197,94,.22);
        color: #86efac;
    }
    html:not(.dark) .prr-banner.pending { background: rgba(245,158,11,.06); border-color: rgba(245,158,11,.2); color: #92400e; }
    html:not(.dark) .prr-banner.done    { background: rgba(34,197,94,.06);  border-color: rgba(34,197,94,.2);  color: #166534; }

    /* Grid */
    .prr-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .75rem;
    }
    @media (max-width: 640px) { .prr-grid { grid-template-columns: 1fr; } }

    /* Cards */
    .prr-card {
        border-radius: .625rem;
        padding: .875rem 1rem;
        border: 1px solid rgba(99,102,241,.12);
        background: rgba(99,102,241,.04);
    }
    html:not(.dark) .prr-card { border-color: rgba(0,0,0,.07); background: rgba(79,70,229,.03); }

    .prr-card-header {
        display: flex; align-items: center; gap: .4rem;
        margin-bottom: .4rem;
    }
    .prr-card-icon {
        width: 14px; height: 14px; flex-shrink: 0;
        color: #6366f1; opacity: .7;
    }
    html:not(.dark) .prr-card-icon { color: #4f46e5; }

    .prr-label {
        font-size: .68rem; font-weight: 700;
        letter-spacing: .07em; text-transform: uppercase;
        color: #64748b; margin: 0;
    }
    html:not(.dark) .prr-label { color: #9ca3af; }

    .prr-val   { font-size: .875rem; color: #e2e8f0; margin: 0; line-height: 1.55; }
    .prr-val.empty { color: #475569; font-style: italic; }
    .prr-sub   { font-size: .75rem; color: #64748b; margin-top: .2rem; }

    html:not(.dark) .prr-val       { color: #111827; }
    html:not(.dark) .prr-val.empty { color: #9ca3af; }
    html:not(.dark) .prr-sub       { color: #6b7280; }

    .prr-tag {
        display: inline-block;
        font-size: .7rem;
        background: rgba(99,102,241,.14);
        color: #a5b4fc;
        border-radius: .3rem;
        padding: .1rem .45rem;
        margin: .15rem .15rem 0 0;
        font-weight: 500;
    }
    html:not(.dark) .prr-tag { background: rgba(79,70,229,.1); color: #4338ca; }

    .prr-desc-text {
        white-space: pre-line;
        max-height: 72px;
        overflow: hidden;
        -webkit-mask-image: linear-gradient(to bottom, #000 55%, transparent);
        mask-image: linear-gradient(to bottom, #000 55%, transparent);
    }
</style>

<div class="prr-wrap">

    {{-- Banner de estado --}}
    @if($chat_listo)
    <div class="prr-banner done">
        <svg style="width:16px;height:16px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Descripción jurídica generada. Puede regenerarla si modificó algún dato.</span>
    </div>
    @else
    <div class="prr-banner pending">
        <svg style="width:16px;height:16px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
        </svg>
        <span>Revise el resumen y luego genere la descripción jurídica con el botón de abajo.</span>
    </div>
    @endif

    {{-- Grid de resumen --}}
    <div class="prr-grid">

        {{-- Trabajador --}}
        <div class="prr-card">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
                <p class="prr-label">Trabajador involucrado</p>
            </div>
            @if($trabajador)
                <p class="prr-val">{{ $trabajador->nombre_completo }}</p>
                <p class="prr-sub">{{ $trabajador->cargo ?? '—' }}</p>
            @else
                <p class="prr-val empty">No seleccionado</p>
            @endif
        </div>

        {{-- Quien reporta --}}
        <div class="prr-card">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>
                </svg>
                <p class="prr-label">Reportado por</p>
            </div>
            <p class="prr-val {{ !$quien_reporta ? 'empty' : '' }}">
                {{ $quienLabels[$quien_reporta] ?? ($quien_reporta ? $quien_reporta : 'No indicado') }}
            </p>
        </div>

        {{-- Descripción del hecho --}}
        <div class="prr-card" style="grid-column: span 2">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <p class="prr-label">Descripción del hecho</p>
            </div>
            @if($descripcion)
                <p class="prr-val prr-desc-text">{{ $descripcion }}</p>
            @else
                <p class="prr-val empty">Sin descripción</p>
            @endif
        </div>

        {{-- Cuándo y dónde --}}
        <div class="prr-card">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
                <p class="prr-label">Cuándo y dónde</p>
            </div>
            <p class="prr-val {{ !$fecha ? 'empty' : '' }}">
                {{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'Fecha no indicada' }}
                @if($hora) · {{ $hora }} @endif
            </p>
            <p class="prr-sub">
                {{ $lugarLabels[$lugar_tipo] ?? ($lugar_tipo ? $lugar_tipo : '—') }}
                @if($en_horario) · Horario laboral: {{ $horarioLabels[$en_horario] ?? $en_horario }} @endif
            </p>
        </div>

        {{-- Evidencias --}}
        <div class="prr-card">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                </svg>
                <p class="prr-label">Evidencias</p>
            </div>
            @if($tiene_evidencias === 'si' && !empty($tipos_evidencias))
                <p class="prr-val" style="margin-bottom:.35rem">Sí, se registraron:</p>
                @foreach($tipos_evidencias as $ev)
                    <span class="prr-tag">{{ $evidenciasLabels[$ev] ?? $ev }}</span>
                @endforeach
            @elseif($tiene_evidencias === 'si')
                <p class="prr-val">Sí (sin tipo especificado)</p>
            @elseif($tiene_evidencias === 'no')
                <p class="prr-val">No hay evidencia registrada</p>
            @else
                <p class="prr-val empty">No indicado</p>
            @endif
        </div>

        {{-- Testigos --}}
        <div class="prr-card" style="grid-column: span 2">
            <div class="prr-card-header">
                <svg class="prr-card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                </svg>
                <p class="prr-label">Testigos</p>
            </div>
            @if($hubo_testigos === 'si' && !empty($testigos))
                <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.2rem">
                    @foreach($testigos as $t)
                        @if(!empty($t['nombre']))
                        <span class="prr-tag">{{ $t['nombre'] }}{{ !empty($t['cargo']) ? ' — '.$t['cargo'] : '' }}</span>
                        @endif
                    @endforeach
                </div>
            @elseif($hubo_testigos === 'no')
                <p class="prr-val">No hubo testigos</p>
            @else
                <p class="prr-val empty">No indicado</p>
            @endif
        </div>

    </div>

</div>
