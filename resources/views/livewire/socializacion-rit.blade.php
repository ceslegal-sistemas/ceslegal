@include('filament.components.lupe-hero-styles')

<div class="min-h-screen bg-gray-50 sm:bg-gray-100 sm:py-8 sm:px-4">
    <div class="sm:max-w-xl sm:mx-auto">
        <div class="bg-white sm:rounded-2xl sm:shadow-lg sm:border sm:border-gray-200 min-h-screen sm:min-h-0 sm:overflow-hidden">
            <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4">
                <h1 class="text-base font-semibold text-gray-900">Reglamento Interno de Trabajo</h1>
                <p class="text-xs text-gray-500">{{ $empresa->razon_social }}</p>
            </header>

            <main class="px-4 sm:px-6 py-6">
                @if ($etapa === 'sin_rit')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-none">Sin Reglamento</span>
                            <h1 class="rit-title">Esta empresa aún no tiene un Reglamento Interno para socializar</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'ya_acepto')
                    <div class="rit-hero" style="padding:1.25rem 1.5rem;">
                        <div class="rit-orb-b"></div><div class="rit-orb-g"></div><div class="rit-overlay"></div>
                        <div style="position:relative;z-index:2">
                            <span class="rit-badge rit-badge-sub">
                                <lord-icon src="https://cdn.lordicon.com/wpsdctqb.json" trigger="loop" delay="800" stroke="bold" colors="primary:#86efac,secondary:#86efac" style="width:16px;height:16px;flex-shrink:0"></lord-icon>
                                Ya registrado
                            </span>
                            <h1 class="rit-title">Ya aceptaste el Reglamento Interno vigente</h1>
                        </div>
                    </div>
                @elseif ($etapa === 'documento')
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Ingresa tu documento de identidad para comenzar.</p>
                        <select wire:model="tipoDocumento" class="w-full rounded-lg border-gray-300">
                            <option value="CC">Cédula de Ciudadanía</option>
                            <option value="CE">Cédula de Extranjería</option>
                            <option value="TI">Tarjeta de Identidad</option>
                            <option value="PASS">Pasaporte</option>
                        </select>
                        <input type="text" wire:model="numeroDocumento" placeholder="Número de documento" class="w-full rounded-lg border-gray-300">
                        <button type="button" wire:click="buscarTrabajador" class="w-full bg-primary-600 text-white font-semibold rounded-xl py-3">
                            Continuar
                        </button>
                    </div>
                @elseif ($etapa === 'datos')
                    <div class="space-y-4">
                        <input type="text" wire:model="nombres" placeholder="Nombres" class="w-full rounded-lg border-gray-300">
                        <input type="text" wire:model="apellidos" placeholder="Apellidos" class="w-full rounded-lg border-gray-300">
                        <select wire:model="genero" class="w-full rounded-lg border-gray-300">
                            <option value="">Género</option>
                            <option value="masculino">Masculino</option>
                            <option value="femenino">Femenino</option>
                            <option value="otro">Otro</option>
                        </select>
                        <input type="text" wire:model="cargo" placeholder="Cargo" class="w-full rounded-lg border-gray-300">
                        <input type="email" wire:model="email" placeholder="Correo (opcional)" class="w-full rounded-lg border-gray-300">
                        <input type="text" wire:model="telefono" placeholder="Teléfono (opcional)" class="w-full rounded-lg border-gray-300">
                        <button type="button" wire:click="guardarDatos" class="w-full bg-primary-600 text-white font-semibold rounded-xl py-3">
                            Continuar
                        </button>
                    </div>
                @elseif ($etapa === 'foto')
                    @include('livewire.partials.foto-simple-captura')
                @endif
            </main>
        </div>
    </div>
</div>
