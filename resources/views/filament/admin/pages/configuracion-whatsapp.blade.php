<x-filament-panels::page>

    {{-- Estado de conexión --}}
    @if ($estadoConexion !== null)
        <div @class([
            'rounded-xl border p-4 flex items-start gap-3 mb-2',
            'bg-success-50 border-success-200 dark:bg-success-950 dark:border-success-800' => $estadoConexion['ok'],
            'bg-danger-50 border-danger-200 dark:bg-danger-950 dark:border-danger-800'     => !$estadoConexion['ok'],
        ])>
            <x-filament::icon
                :icon="$estadoConexion['ok'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                @class([
                    'w-5 h-5 mt-0.5 flex-shrink-0',
                    'text-success-600 dark:text-success-400' => $estadoConexion['ok'],
                    'text-danger-600 dark:text-danger-400'   => !$estadoConexion['ok'],
                ])
            />
            <div class="text-sm">
                <p @class([
                    'font-semibold',
                    'text-success-700 dark:text-success-300' => $estadoConexion['ok'],
                    'text-danger-700 dark:text-danger-300'   => !$estadoConexion['ok'],
                ])>
                    {{ $estadoConexion['ok'] ? 'Conexión verificada' : 'Error de conexión' }}
                </p>
                <p class="text-gray-600 dark:text-gray-400 mt-0.5">{{ $estadoConexion['mensaje'] }}</p>
                @if (!empty($estadoConexion['numero']))
                    <p class="text-gray-500 dark:text-gray-500 mt-1 text-xs">
                        Número: <strong>{{ $estadoConexion['numero'] }}</strong>
                        @if (!empty($estadoConexion['nombre']))
                            · Nombre verificado: <strong>{{ $estadoConexion['nombre'] }}</strong>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Formulario --}}
    <form wire:submit="guardar">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-m-check">
                Guardar configuración
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="verificarConexion"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-m-signal"
            >
                <span wire:loading.remove wire:target="verificarConexion">Verificar conexión</span>
                <span wire:loading wire:target="verificarConexion">Verificando...</span>
            </x-filament::button>
        </div>
    </form>

    {{-- Guía rápida --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Guía rápida — cómo obtener las credenciales</x-slot>
        <x-slot name="description">Pasos para configurar WhatsApp Business con Meta Cloud API</x-slot>

        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li>
                Acceda a <strong class="text-gray-800 dark:text-gray-200">developers.facebook.com</strong>
                y cree (o seleccione) una app de tipo <em>Business</em>.
            </li>
            <li>
                En la app, agregue el producto <strong class="text-gray-800 dark:text-gray-200">WhatsApp</strong>
                y vincule una cuenta de WhatsApp Business.
            </li>
            <li>
                En <em>WhatsApp → API Setup</em>, copie el
                <strong class="text-gray-800 dark:text-gray-200">Phone Number ID</strong>
                y el <strong class="text-gray-800 dark:text-gray-200">WhatsApp Business Account ID</strong>.
            </li>
            <li>
                Genere un <strong class="text-gray-800 dark:text-gray-200">System User Token</strong> permanente
                en <em>Meta Business Suite → Usuarios del sistema</em> y asígnele permisos de
                <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">whatsapp_business_messaging</code>.
            </li>
            <li>
                Configure el <strong class="text-gray-800 dark:text-gray-200">Webhook</strong> con la URL
                mostrada arriba y el token de verificación definido en este formulario.
                Suscríbase al evento <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">messages</code>.
            </li>
        </ol>
    </x-filament::section>

</x-filament-panels::page>
