<div class="space-y-4">
    <div>
        <label class="text-sm font-medium">Cargo</label>
        @include('filament.components.select-con-busqueda', [
            'modeloBusqueda' => 'cargoBusqueda',
            'metodoBuscar' => 'buscarCargos',
            'resultados' => $cargoResultados,
            'metodoSeleccionar' => 'seleccionarCargo',
            'placeholder' => 'Busque o escriba el cargo...',
            'valorActualLabel' => $cargo_contrato && $cargo_contrato !== '__otro__' ? $cargo_contrato : null,
            'deshabilitado' => false,
        ])
        <button type="button" wire:click="$set('cargo_contrato', '__otro__')" class="text-xs text-primary-600 hover:underline mt-1">Usar un cargo personalizado</button>
        @if ($cargo_contrato === '__otro__')
            <input type="text" wire:model="cargo_otro" placeholder="Ej: Jefe de Proyectos Especiales" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm mt-2" />
        @endif
        @error('cargo_contrato') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    @include('filament.components.solicitud-contrato-detalles-cargo-ia-boton-standalone')

    <div>
        <label class="text-sm font-medium">Responsabilidades del Cargo</label>
        @include('filament.components.mini-rich-editor', ['modelo' => 'responsabilidades', 'valorInicial' => $responsabilidades, 'placeholder' => 'Liste las responsabilidades principales del cargo...'])
        @error('responsabilidades') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Objeto Comercial</label>
        @include('filament.components.mini-rich-editor', ['modelo' => 'objeto_comercial', 'valorInicial' => $objeto_comercial, 'placeholder' => 'Describa el objeto comercial del contrato...'])
        @error('objeto_comercial') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    @if ($tipo_contrato === 'Contrato de Obra o Labor')
        <div>
            <label class="text-sm font-medium">Descripción de la obra o labor contratada</label>
            <textarea wire:model="descripcion_obra_labor" rows="3" placeholder="Ej: Construcción de la bodega de almacenamiento ubicada en..." class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm"></textarea>
            @error('descripcion_obra_labor') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label class="text-sm font-medium">Manual de Funciones</label>
        @include('filament.components.mini-rich-editor', ['modelo' => 'manual_funciones', 'valorInicial' => $manual_funciones, 'placeholder' => 'Detalle el manual de funciones...'])
        @error('manual_funciones') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="text-sm font-medium">Fecha de Inicio Propuesta</label>
            <input type="date" wire:model="fecha_inicio_propuesta" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
        </div>
        <div>
            <label class="text-sm font-medium">Fecha de Terminación del Contrato</label>
            <input type="date" wire:model="fecha_fin_contrato" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
            @if ($tipo_contrato === 'Contrato Ocasional o Transitorio')
                <p class="text-xs text-gray-500 mt-1">Este tipo de contrato tiene un máximo legal de 30 días desde la fecha de inicio.</p>
            @endif
            @error('fecha_fin_contrato') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="text-sm font-medium">Salario Propuesto</label>
        <input type="text" wire:model.live.debounce.150ms="salario_propuesto" placeholder="Ej: 2.500.000" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" />
        @error('salario_propuesto') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Período de Pago</label>
        <div class="flex flex-wrap gap-2 mt-1">
            @foreach (['mensual' => 'Mensual', 'quincenal' => 'Quincenal', 'semanal' => 'Semanal', 'diario' => 'Diario', 'destajo' => 'Por obra o destajo'] as $valor => $label)
                <button type="button" wire:click="$set('periodo_pago', '{{ $valor }}')"
                    class="px-3 py-1.5 rounded-full text-sm border {{ $periodo_pago === $valor ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 dark:border-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="text-sm font-medium">Departamento</label>
            <select wire:model.live="departamento" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm">
                <option value="">Seleccione...</option>
                @foreach (\App\Filament\Admin\Resources\SolicitudContratoResource::getDepartamentos() as $dep)
                    <option value="{{ $dep }}">{{ $dep }}</option>
                @endforeach
            </select>
            @error('departamento') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="text-sm font-medium">Ciudad</label>
            <select wire:model.live="ciudad" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm" {{ blank($departamento) ? 'disabled' : '' }}>
                <option value="">Seleccione primero el departamento...</option>
                @foreach ($this->getCiudadesDisponibles() as $ciu)
                    <option value="{{ $ciu }}">{{ $ciu }}</option>
                @endforeach
            </select>
            @error('ciudad') <p class="text-danger-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="text-sm font-medium">Jornada</label>
        <div class="flex flex-wrap gap-2 mt-1">
            @foreach (['Tiempo completo', 'Medio tiempo', 'Por horas', '__otro__'] as $valor)
                <button type="button" wire:click="$set('jornada', '{{ $valor }}')"
                    class="px-3 py-1.5 rounded-full text-sm border {{ $jornada === $valor ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 dark:border-gray-600' }}">
                    {{ $valor === '__otro__' ? 'Otro (personalizado)' : $valor }}
                </button>
            @endforeach
        </div>
        @if ($jornada === '__otro__')
            <input type="text" wire:model="jornada_otro" placeholder="Ej: Turnos rotativos" class="fi-input block w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 px-3 py-2 text-sm mt-2" />
        @endif
    </div>

    <div class="flex justify-between">
        <button type="button" wire:click="retrocederPaso" class="rit-btn">Volver</button>
        <button type="button" wire:click="avanzarPaso" class="rit-btn rit-btn-primary">Continuar</button>
    </div>
</div>
