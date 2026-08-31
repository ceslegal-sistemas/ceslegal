{{--
    Estilos compartidos del "visor de documento" (caja con encabezado +
    contador de caracteres + texto tipo serif desplazable), extraídos de
    mi-reglamento-interno.blade.php para reutilizar en otros Resources (ver
    SolicitudContratoResource::infolist()) sin duplicar el bloque completo.
    Incluir una sola vez por página con @include.
--}}
<style>
.rit-viewer{border-radius:1rem;border:1px solid rgba(255,255,255,.09);overflow:hidden;margin-top:1.25rem}
html:not(.dark) .rit-viewer{border-color:rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.06)}
.rit-viewer-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1.125rem;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04)}
html:not(.dark) .rit-viewer-header{background:rgba(0,0,0,.03);border-bottom-color:rgba(0,0,0,.07)}
.rit-viewer-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.rit-viewer-body{max-height:65vh;overflow-y:auto;padding:1.5rem 1.75rem;background:rgba(0,0,0,.15)}
html:not(.dark) .rit-viewer-body{background:#fafafa}
.rit-text{white-space:normal;font-family:'Georgia','Times New Roman',serif;font-size:.875rem;line-height:1.9;color:#cbd5e1;word-break:break-word}
html:not(.dark) .rit-text{color:#292524}
.rit-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3.5rem 2rem;text-align:center}
.rit-empty-icon{width:56px;height:56px;border-radius:50%;background:rgba(251,113,133,.12);border:1.5px solid rgba(251,113,133,.25);display:flex;align-items:center;justify-content:center;margin-bottom:1rem}
.rit-empty-title{font-size:1.0625rem;font-weight:700;color:#f1f5f9;margin:0 0 .4rem}
html:not(.dark) .rit-empty-title{color:#1c1917}
.rit-empty-sub{font-size:.825rem;color:#64748b;margin:0 0 1.5rem;max-width:380px}
</style>
