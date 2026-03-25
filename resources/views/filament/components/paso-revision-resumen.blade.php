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
    .prr-grid  { display: grid; grid-template-columns: repeat(2, 1fr); gap: .875rem; margin-bottom: 1rem; }
    .prr-card  { border-radius: .625rem; padding: .875rem 1rem; border: 1px solid rgba(255,255,255,.06); }
    .prr-label { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin: 0 0 .35rem; color: #64748b; }
    .prr-val   { font-size: .875rem; color: #e2e8f0; margin: 0; line-height: 1.55; }
    .prr-val.empty { color: #64748b; font-style: italic; }
    .prr-sub   { font-size: .75rem; color: #94a3b8; margin-top: .2rem; }
    .prr-tag   { display: inline-block; font-size: .7rem; background: rgba(99,102,241,.15); color: #a5b4fc; border-radius: .25rem; padding: .1rem .4rem; margin: .15rem .15rem 0 0; }
    .prr-pending { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); border-radius: .75rem; padding: .875rem 1rem; display:flex; align-items:center; gap:.5rem; }
    .prr-done    { background: rgba(34,197,94,.07); border-color: rgba(34,197,94,.22); border-radius: .75rem; padding: .875rem 1rem; display:flex; align-items:center; gap:.5rem; margin-bottom:1rem; }

    html:not(.dark) .prr-card  { border-color: rgba(0,0,0,.08); }
    html:not(.dark) .prr-val   { color: #111827; }
    html:not(.dark) .prr-val.empty { color: #9ca3af; }
    html:not(.dark) .prr-sub   { color: #6b7280; }
    html:not(.dark) .prr-tag   { background: rgba(99,102,241,.1); color: #4f46e5; }
    html:not(.dark) .prr-pending { background: rgba(245,158,11,.06); border-color: rgba(245,158,11,.2); }
    html:not(.dark) .prr-done    { background: rgba(34,197,94,.06); border-color: rgba(34,197,94,.2); }
    html:not(.dark) .prr-label { color: #9ca3af; }
</style>

{{-- Estado de generación --}}
@if($chat_listo)
<div style="border:1px solid" class="prr-done">
    <svg style="width:16px;height:16px;color:#4ade80;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span style="font-size:.8125rem;color:#86efac">Descripción jurídica ya generada. Puede regenerarla si modificó algún dato.</span>
</div>
@else
<div style="border:1px solid" class="prr-pending">
    <svg style="width:16px;height:16px;color:#fbbf24;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
    </svg>
    <span style="font-size:.8125rem;color:#fde68a">Revise el resumen y luego genere la descripción jurídica con el botón de abajo.</span>
</div>
@endif

{{-- Grid de resumen --}}
<div class="prr-grid">

    {{-- Trabajador --}}
    <div class="prr-card" style="background:rgba(99,102,241,.05)">
        <p class="prr-label">Trabajador involucrado</p>
        @if($trabajador)
            <p class="prr-val">{{ $trabajador->nombre_completo }}</p>
            <p class="prr-sub">{{ $trabajador->cargo ?? '—' }}</p>
        @else
            <p class="prr-val empty">No seleccionado</p>
        @endif
    </div>

    {{-- Quien reporta --}}
    <div class="prr-card" style="background:rgba(99,102,241,.05)">
        <p class="prr-label">Reportado por</p>
        <p class="prr-val {{ !$quien_reporta ? 'empty' : '' }}">
            {{ $quienLabels[$quien_reporta] ?? ($quien_reporta ? $quien_reporta : 'No indicado') }}
        </p>
    </div>

    {{-- Descripción --}}
    <div class="prr-card" style="background:rgba(99,102,241,.05);grid-column:span 2">
        <p class="prr-label">Descripción del hecho</p>
        @if($descripcion)
            <p class="prr-val" style="white-space:pre-line;max-height:80px;overflow:hidden;-webkit-mask-image:linear-gradient(to bottom,#000 60%,transparent)">{{ $descripcion }}</p>
        @else
            <p class="prr-val empty">Sin descripción</p>
        @endif
    </div>

    {{-- Fecha / hora / lugar --}}
    <div class="prr-card" style="background:rgba(99,102,241,.05)">
        <p class="prr-label">Cuándo y dónde</p>
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
    <div class="prr-card" style="background:rgba(99,102,241,.05)">
        <p class="prr-label">Evidencias</p>
        @if($tiene_evidencias === 'si' && !empty($tipos_evidencias))
            <p class="prr-val" style="margin-bottom:.3rem">Sí, se registraron:</p>
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
    <div class="prr-card" style="background:rgba(99,102,241,.05);grid-column:span 2">
        <p class="prr-label">Testigos</p>
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
