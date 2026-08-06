{{--
    Contenido del paso 5 ("Reglamento Interno") del wizard "Mi Empresa" - solo
    resume el estado del RIT, no duplica el uploader/builder (eso vive en
    "Mi Reglamento Interno"). Variable esperada: $empresa (App\Models\Empresa).
--}}
<div>
    {!! view('filament.components.empresa-rit-status', [
        'empresa' => $empresa,
        'mostrarObligacion' => true,
    ])->render() !!}

    <a href="{{ \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl() }}"
       style="display:inline-flex;align-items:center;gap:.4rem;margin-top:.9rem;padding:.55rem 1.1rem;border-radius:.6rem;background:#22c55e;color:white;font-size:.875rem;font-weight:600;text-decoration:none;">
        Ir a Mi Reglamento Interno
    </a>
</div>
