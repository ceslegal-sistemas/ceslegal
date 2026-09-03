{{--
    Vista custom de "Ver Solicitud de Contrato" - mismo lenguaje visual
    (.rit-info-card/.rit-viewer) que "Mi Reglamento Interno", a pedido
    explícito del usuario ("mismo reskin"). No usa Infolist/form() de
    Filament para el cuerpo: se arma a mano leyendo $record directo, igual
    que mi-reglamento-interno.blade.php lee $reglamento directo.

    El hero de marca (SolicitudContratoRecordHeroWidget) sigue renderizando
    solo arriba - <x-filament-panels::page> lo hace automáticamente vía
    getHeaderWidgets(), sin importar qué haya en este slot.
--}}
@php
    $periodoPagoLabel = match ($record->periodo_pago) {
        'mensual' => 'Mensual',
        'quincenal' => 'Quincenal',
        'semanal' => 'Semanal',
        'diario' => 'Diario',
        'destajo' => 'Por obra o destajo',
        default => $record->periodo_pago,
    };

    // Se lee del timeline en vez de recalcularlo aquí, para no disparar la
    // extracción de conductas del RIT (ni ninguna llamada a IA) solo por
    // abrir la página "Ver" - refleja lo que realmente se usó la última vez
    // que se generó el documento.
    $ultimoDocumentoGenerado = $record->timeline()->where('accion', 'Documento generado')->first();
    $faltasGravesOrigenLabel = match ($ultimoDocumentoGenerado?->metadata['faltas_graves_origen'] ?? null) {
        'rit' => 'Según RIT de la empresa',
        'sin_rit' => 'Listado general (sin RIT registrado)',
        'sin_conductas' => 'Listado general (RIT sin faltas identificadas)',
        default => null,
    };
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @include('filament.components.documento-viewer-styles')

    @if ($record->tipo_contrato === 'Contrato a Término Fijo')
        @include('filament.components.solicitud-contrato-vencimiento-card', ['record' => $record])
    @endif

    @include('filament.components.rit-info-card', [
        'icon' => 'https://cdn.lordicon.com/moedrfvp.json',
        'title' => 'Información Básica',
        'rows' => [
            ['label' => 'Empresa', 'value' => $record->empresa?->razon_social],
            ['label' => 'Tipo de Contrato', 'value' => $record->tipo_contrato],
            ['label' => 'Fecha de Solicitud', 'value' => $record->fecha_solicitud?->format('d/m/Y')],
        ],
    ])

    @include('filament.components.rit-info-card', [
        'icon' => 'https://cdn.lordicon.com/hqkfqrrm.json',
        'title' => 'Trabajador',
        'rows' => [
            ['label' => 'Nombre', 'value' => trim("{$record->trabajador_nombres} {$record->trabajador_apellidos}")],
            ['label' => 'Documento', 'value' => "{$record->trabajador_documento_tipo}: {$record->trabajador_documento_numero}"],
            ['label' => 'Correo Electrónico', 'value' => $record->trabajador_email],
            ['label' => 'Teléfono', 'value' => $record->trabajador_telefono],
            ['label' => 'Dirección', 'value' => $record->trabajador_direccion, 'full' => true],
        ],
    ])

    @include('filament.components.rit-info-card', [
        'icon' => 'https://cdn.lordicon.com/vgwutnhw.json',
        'title' => 'Condiciones del Contrato',
        'rows' => [
            ['label' => 'Cargo', 'value' => $record->cargo_contrato],
            ['label' => 'Jornada', 'value' => $record->jornada],
            ['label' => 'Fecha de Inicio', 'value' => $record->fecha_inicio_propuesta?->format('d/m/Y')],
            ['label' => 'Fecha de Terminación', 'value' => $record->fecha_fin_contrato?->format('d/m/Y')],
            ['label' => 'Salario', 'value' => $record->salario_propuesto ? number_format((float) $record->salario_propuesto, 2, ',', '.') . ' COP' : null],
            ['label' => 'Período de Pago', 'value' => $periodoPagoLabel],
            ['label' => 'Faltas Graves', 'value' => $faltasGravesOrigenLabel],
            ['label' => 'Lugar de Labores', 'value' => $record->lugar_labores, 'full' => true],
        ],
    ])

    @if ($record->estado === 'rechazado')
        @include('filament.components.rit-info-card', [
            'icon' => 'https://cdn.lordicon.com/tdrtiskw.json',
            'title' => 'Motivo del Rechazo',
            'rows' => [
                ['label' => 'Motivo', 'value' => $record->motivo_rechazo, 'full' => true],
            ],
        ])
    @endif

    @include('filament.components.solicitud-contrato-historial-cambios', [
        'modificaciones' => $record->modificaciones,
    ])

    @include('filament.components.solicitud-contrato-documento-viewer', ['texto' => $record->objeto_juridico_redactado])

    @include('filament.components.solicitud-contrato-timeline', [
        'eventos' => $record->timeline()->with('user')->get(),
    ])
</x-filament-panels::page>
