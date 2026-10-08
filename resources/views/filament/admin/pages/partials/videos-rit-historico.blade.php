<div style="display:flex;flex-direction:column;gap:1.25rem;">
    @foreach ($historico->capitulos ?? [] as $indice => $capitulo)
        <div>
            <p style="font-weight:600;margin-bottom:.5rem;">{{ $indice + 1 }}. {{ $capitulo['titulo'] ?? 'Capítulo' }}</p>
            <video controls preload="metadata" style="width:100%;border-radius:.5rem;"
                src="{{ route('rit.video-didactico.historico', ['reglamento' => $historico->reglamento_interno_id, 'historico' => $historico->id, 'capitulo' => $indice]) }}"></video>
        </div>
    @endforeach
</div>
