{{--
    Contenido del paso 5 ("Reglamento Interno") del wizard "Mi Empresa" - solo
    resume el estado del RIT, no duplica el uploader/builder (eso vive en
    "Mi Reglamento Interno"). Variable esperada: $empresa (App\Models\Empresa).
--}}
<div>
    {{-- mostrarObligacion en false a propósito (2026-09-07): "¿está
         obligada a tener RIT?" ya se responde en el Paso 4 (donde se
         indica el número de empleados) - repetirlo acá, donde el cliente
         ya ve si TIENE o no un RIT construido, resultaba confuso ("¿por
         qué me pregunta si necesito uno si ya lo tengo?"). --}}
    {!! view('filament.components.empresa-rit-status', [
        'empresa' => $empresa,
        'mostrarObligacion' => false,
    ])->render() !!}

    {{-- Antes era un botón verde suelto que no coincidía con la marca -
         mismo degradado rosa/naranja que ya usa .ers-btn (empresa-rit-status.blade.php)
         y el resto de botones primarios del sistema. --}}
    <a href="{{ \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl() }}"
       style="display:inline-flex;align-items:center;gap:.5rem;margin-top:.9rem;padding:.6rem 1.2rem;border-radius:.65rem;background:linear-gradient(135deg,#e11d48,#f97316);color:#fff;font-size:.85rem;font-weight:700;text-decoration:none;transition:filter .15s"
       onmouseover="this.style.filter='brightness(1.06)'" onmouseout="this.style.filter='none'">
        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        Ir a Mi Reglamento Interno
    </a>
</div>
