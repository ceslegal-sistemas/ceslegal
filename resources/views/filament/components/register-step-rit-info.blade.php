{{--
    Rediseño (2026-09-28, pedido explícito del usuario: mismo estilo .rit-hero
    de "Socializa el RIT", sin la línea de acento izquierda de .pt-card - el
    acento "ámbar" que tenía el borde ahora vive solo en el color del icono
    principal, igual que antes).
--}}
@include('filament.components.pinfo-styles')
@include('filament.components.lupe-hero-styles')

<div class="rit-hero" style="padding:1.25rem 1.5rem;">
    <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
    <div style="position:relative;z-index:2">

        <div style="display:flex;align-items:center;gap:.625rem;margin-bottom:.5rem;">
            <lord-icon src="https://cdn.lordicon.com/hmpomorl.json" trigger="loop" delay="500" stroke="bold"
                colors="primary:#fbbf24,secondary:#fde68a" data-pt-icon
                data-pt-dark="primary:#fbbf24,secondary:#fde68a"
                data-pt-light="primary:#d97706,secondary:#fbbf24"
                style="width:32px;height:32px;flex-shrink:0">
            </lord-icon>
            <h1 class="rit-title" style="margin:0;font-size:1.05rem;">Reglamento Interno de Trabajo (RIT)</h1>
        </div>

        <p class="rit-sub" style="margin-bottom:.75rem;">
            El RIT es <strong>obligatorio</strong> para empresas con más de 5 trabajadores (Art. 105 CST).
            Sin él, la plataforma solo puede aplicar <strong>terminación de contrato</strong> como medida disciplinaria.
            Con RIT activo puede usar también llamados de atención y suspensiones.
        </p>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:.75rem;">

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#4ade80,secondary:#86efac" data-pt-icon
                    data-pt-dark="primary:#4ade80,secondary:#86efac"
                    data-pt-light="primary:#16a34a,secondary:#22c55e"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span><strong>Ya lo tengo:</strong> súbalo en formato .docx o .pdf - lo procesamos automáticamente.</span>
            </div>

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/vgwutnhw.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0" data-pt-icon
                    data-pt-dark="primary:#fb7185,secondary:#fb7185,tertiary:#e2e8f0"
                    data-pt-light="primary:#e11d48,secondary:#f97316,tertiary:#fecdd3"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span><strong>No lo tengo:</strong> la IA lo redacta completo (16 capítulos) tras el registro. Recomendado.</span>
            </div>

            <div class="pt-bullet">
                <lord-icon src="https://cdn.lordicon.com/uphbloed.json" trigger="loop" delay="500" stroke="bold"
                    colors="primary:#94a3b8,secondary:#64748b" data-pt-icon
                    data-pt-dark="primary:#94a3b8,secondary:#64748b"
                    data-pt-light="primary:#64748b,secondary:#94a3b8"
                    style="width:20px;height:20px;flex-shrink:0">
                </lord-icon>
                <span><strong>Después:</strong> puede subirlo o construirlo más adelante desde el panel.</span>
            </div>

        </div>

        <p class="pt-footer">
            Art. 105 CST - Ley 2365/2024 (prevención acoso sexual)
        </p>

    </div>
</div>
