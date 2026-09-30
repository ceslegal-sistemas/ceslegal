{{--
    Reporte completo de socialización del RIT (pedido de Andrés Sarmiento,
    2026-09-29) - mismo detalle que el desplegable inline de la barra de
    progreso en mi-reglamento-interno.blade.php, sin recortar la lista.
    Variable esperada: $detalle (array de MiReglamentoInterno::detalleTrabajadoresSocializacion()).
--}}
<div style="max-height:60vh;overflow-y:auto">
    @if(empty($detalle))
        <p style="text-align:center;padding:1.5rem;color:#78716c;font-size:.875rem">No hay trabajadores activos registrados todavía.</p>
    @else
        <div style="display:flex;flex-direction:column;gap:.5rem">
            @foreach($detalle as $fila)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.65rem .9rem;border-radius:.6rem;background:rgba(0,0,0,.025)">
                    <div>
                        <p style="margin:0;font-size:.875rem;font-weight:600;color:#292524">{{ $fila['nombre'] }}</p>
                        @if($fila['cargo'])
                            <p style="margin:0;font-size:.75rem;color:#78716c">{{ $fila['cargo'] }}</p>
                        @endif
                    </div>
                    @if($fila['acepto'])
                        <span style="font-size:.75rem;font-weight:600;padding:.25rem .6rem;border-radius:999px;background:rgba(34,197,94,.12);color:#166534;white-space:nowrap">
                            Aceptó{{ $fila['fecha_aceptacion'] ? ' · ' . $fila['fecha_aceptacion']->format('d/m/Y') : '' }}
                        </span>
                    @else
                        <span style="font-size:.75rem;font-weight:600;padding:.25rem .6rem;border-radius:999px;background:rgba(234,179,8,.12);color:#854d0e;white-space:nowrap">
                            Pendiente
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
