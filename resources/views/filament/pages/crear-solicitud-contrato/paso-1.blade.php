<div class="space-y-4">
    <div>
        <label class="text-sm font-medium">Empresa</label>
        @include('filament.components.select-con-busqueda', [
            'modeloBusqueda' => 'empresaBusqueda',
            'metodoBuscar' => 'buscarEmpresas',
            'resultados' => $empresaResultados,
            'metodoSeleccionar' => 'seleccionarEmpresa',
            'placeholder' => 'Busque y seleccione la empresa...',
            'valorActualLabel' => $empresa_id ? \App\Models\Empresa::find($empresa_id)?->razon_social : null,
            'deshabilitado' => auth()->user()?->isCliente() ?? false,
        ])
        @error('empresa_id') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Tipo de Contrato</label>
        <select wire:model="tipo_contrato" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm">
            <option value="">Seleccione el tipo de contrato...</option>
            <option value="Contrato a Término Fijo">Contrato a Término Fijo - Duración determinada</option>
            <option value="Contrato a Término Indefinido">Contrato a Término Indefinido - Sin fecha de terminación</option>
            <option value="Contrato de Obra o Labor">Contrato de Obra o Labor - Por proyecto específico</option>
            <option value="Contrato de Prestación de Servicios">Contrato de Prestación de Servicios - Independiente</option>
            <option value="Contrato de Aprendizaje">Contrato de Aprendizaje - Estudiante/Aprendiz</option>
            <option value="Contrato Ocasional o Transitorio">Contrato Ocasional o Transitorio - Máximo 30 días</option>
        </select>
        @error('tipo_contrato') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Fecha de Solicitud</label>
        <input type="datetime-local" wire:model="fecha_solicitud" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
        @error('fecha_solicitud') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex justify-end">
        <button type="button" wire:click="avanzarPaso" class="rit-btn rit-btn-primary">Continuar</button>
    </div>
</div>
