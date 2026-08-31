<div class="space-y-4">
    @if (in_array($tipo_contrato, ['Contrato de Prestación de Servicios', 'Contrato de Obra o Labor']))
        @include('filament.components.subida-archivo-zona', [
            'modelo' => 'ruta_orden_compra',
            'label' => 'Orden de Compra',
            'ayuda' => 'Adjunte la orden de compra o autorización (PDF, JPG, PNG - Máx. 5MB)',
            'tiposAceptados' => 'application/pdf,image/jpeg,image/png',
        ])
    @endif

    @include('filament.components.subida-archivo-zona', [
        'modelo' => 'ruta_manual_funciones',
        'label' => 'Manual de Funciones (archivo adjunto, opcional)',
        'ayuda' => 'Adjunte el manual de funciones (PDF, DOC, DOCX - Máx. 10MB)',
        'tiposAceptados' => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])

    <div class="flex justify-between">
        <button type="button" wire:click="retrocederPaso" class="rit-btn">Volver</button>
        <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar" class="rit-btn rit-btn-primary">
            <span wire:loading.remove wire:target="guardar">Crear Solicitud</span>
            <span wire:loading wire:target="guardar">Generando...</span>
        </button>
    </div>
</div>
