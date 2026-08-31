<div class="space-y-4">
    <label class="flex items-center gap-2">
        <input type="checkbox" wire:model.live="usarTrabajadorExistente" class="rounded" />
        <span class="text-sm font-medium">¿Usar trabajador existente?</span>
    </label>

    @if ($usarTrabajadorExistente)
        @include('filament.components.select-con-busqueda', [
            'modeloBusqueda' => 'trabajadorBusqueda',
            'metodoBuscar' => 'buscarTrabajadores',
            'resultados' => $trabajadorResultados,
            'metodoSeleccionar' => 'seleccionarTrabajador',
            'placeholder' => 'Busque por nombre, apellidos o número de documento...',
            'valorActualLabel' => $trabajador_id ? "{$trabajador_nombres} {$trabajador_apellidos} - {$trabajador_documento_tipo}: {$trabajador_documento_numero}" : null,
            'deshabilitado' => false,
        ])
        @error('trabajador_id') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Nombres</label>
                <input type="text" wire:model="trabajador_nombres" placeholder="Ej: Juan Carlos" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
                @error('trabajador_nombres') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Apellidos</label>
                <input type="text" wire:model="trabajador_apellidos" placeholder="Ej: Pérez García" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
                @error('trabajador_apellidos') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Tipo de Documento</label>
                <select wire:model="trabajador_documento_tipo" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm">
                    <option value="CC">Cédula de Ciudadanía</option>
                    <option value="CE">Cédula de Extranjería</option>
                    <option value="TI">Tarjeta de Identidad</option>
                    <option value="PASS">Pasaporte</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Número de Documento</label>
                <input type="text" inputmode="numeric" wire:model="trabajador_documento_numero" placeholder="Ej: 1234567890" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
                @error('trabajador_documento_numero') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Correo Electrónico</label>
                <input type="email" wire:model="trabajador_email" placeholder="trabajador@empresa.com" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
                @error('trabajador_email') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Teléfono / Celular</label>
                <input type="tel" wire:model="trabajador_telefono" placeholder="Ej: +57 300 123 4567" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm font-medium">Dirección de Residencia</label>
                <textarea wire:model="trabajador_direccion" rows="2" placeholder="Ej: Calle 123 # 45-67" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm"></textarea>
            </div>
        </div>
    @endif

    <div class="flex justify-between">
        <button type="button" wire:click="retrocederPaso" class="rit-btn">Volver</button>
        <button type="button" wire:click="avanzarPaso" class="rit-btn rit-btn-primary">Continuar</button>
    </div>
</div>
