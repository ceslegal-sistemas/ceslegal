@php
    $tipoDocLabel = match ($trabajador->tipo_documento ?? '') {
        'CC'   => 'C.C.',
        'CE'   => 'C.E.',
        'TI'   => 'T.I.',
        'PASS' => 'Pasaporte',
        default => $trabajador->tipo_documento ?? '',
    };
@endphp

<style>
    .ptc-card {
        background: rgba(34, 197, 94, .07);
        border: 1px solid rgba(34, 197, 94, .22);
        border-radius: .75rem;
        padding: 1rem 1.25rem;
    }

    .ptc-label {
        font-size: .75rem;
        font-weight: 700;
        color: #4ade80;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ptc-name {
        margin: 0 0 .35rem;
        font-size: 1.0625rem;
        font-weight: 700;
        color: #f1f5f9;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .ptc-meta {
        margin: 0 0 .625rem;
        font-size: .8125rem;
        color: #94a3b8;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .ptc-email-row {
        display: flex;
        align-items: flex-start;
        gap: .4rem;
        font-size: .8125rem;
        color: #94a3b8;
        flex-wrap: wrap;
    }

    .ptc-email-addr {
        word-break: break-all;
        overflow-wrap: anywhere;
        min-width: 0;
    }

    .ptc-email-note {
        font-size: .7rem;
        color: #64748b;
        width: 100%;
        padding-left: 20px; /* align under email, past icon */
    }

    html:not(.dark) .ptc-card {
        background: rgba(34, 197, 94, .06);
        border-color: rgba(34, 197, 94, .28);
    }

    html:not(.dark) .ptc-label {
        color: #15803d;
    }

    html:not(.dark) .ptc-name {
        color: #0f172a;
    }

    html:not(.dark) .ptc-meta {
        color: #475569;
    }

    html:not(.dark) .ptc-email-row {
        color: #374151;
    }

    html:not(.dark) .ptc-email-note {
        color: #9ca3af;
    }
</style>

<div class="ptc-card">

    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.625rem;">
        <lord-icon
            src="https://cdn.lordicon.com/hqkfqrrm.json"
            trigger="loop" delay="500" stroke="bold"
            colors="primary:#4ade80,secondary:#86efac,tertiary:#166534"
            data-pt-icon
            data-pt-dark="primary:#4ade80,secondary:#86efac,tertiary:#166534"
            data-pt-light="primary:#16a34a,secondary:#22c55e,tertiary:#bbf7d0"
            style="width:22px;height:22px;flex-shrink:0">
        </lord-icon>
        <span class="ptc-label">Trabajador seleccionado</span>
    </div>

    <p class="ptc-name">{{ $trabajador->nombre_completo }}</p>

    <p class="ptc-meta">
        {{ $trabajador->cargo ?? 'Cargo no registrado' }}
        &nbsp;·&nbsp;
        {{ $tipoDocLabel }} {{ $trabajador->numero_documento }}
    </p>

    @if ($trabajador->email)
        <div class="ptc-email-row">
            <lord-icon
                src="https://cdn.lordicon.com/wpsdctqb.json"
                trigger="loop" delay="500"
                colors="primary:#94a3b8,secondary:#64748b"
                data-pt-icon
                data-pt-dark="primary:#94a3b8,secondary:#64748b"
                data-pt-light="primary:#374151,secondary:#6b7280"
                style="width:16px;height:16px;flex-shrink:0">
            </lord-icon>
            <span class="ptc-email-addr">{{ $trabajador->email }}</span>
            <span class="ptc-email-note">La citación se enviará a este correo</span>
        </div>
    @endif

</div>
