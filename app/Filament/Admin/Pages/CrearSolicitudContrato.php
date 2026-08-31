<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\SolicitudContratoResource;
use App\Models\Empresa;
use App\Models\SolicitudContrato;
use App\Models\Trabajador;
use App\Services\SolicitudContratoIAService;
use App\Support\FormateoNumerico;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

class CrearSolicitudContrato extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.pages.crear-solicitud-contrato';

    protected static bool $shouldRegisterNavigation = false;

    public int $paso = 1;

    // Paso 1
    public ?int $empresa_id = null;
    public string $empresaBusqueda = '';
    public array $empresaResultados = [];
    public string $tipo_contrato = '';
    public string $fecha_solicitud = '';

    // Paso 2
    public bool $usarTrabajadorExistente = false;
    public ?int $trabajador_id = null;
    public string $trabajadorBusqueda = '';
    public array $trabajadorResultados = [];
    public string $trabajador_nombres = '';
    public string $trabajador_apellidos = '';
    public string $trabajador_documento_tipo = 'CC';
    public string $trabajador_documento_numero = '';
    public string $trabajador_email = '';
    public string $trabajador_telefono = '';
    public string $trabajador_direccion = '';

    // Paso 3
    public string $cargo_contrato = '';
    public string $cargo_otro = '';
    public string $cargoBusqueda = '';
    public array $cargoResultados = [];
    public string $responsabilidades = '';
    public string $objeto_comercial = '';
    public string $manual_funciones = '';
    public string $descripcion_obra_labor = '';
    public ?string $fecha_inicio_propuesta = null;
    public ?string $fecha_fin_contrato = null;
    public string $salario_propuesto = '';
    public string $periodo_pago = 'mensual';
    public ?string $departamento = null;
    public ?string $ciudad = null;
    public ?string $lugar_labores = null;
    public string $jornada = 'Tiempo completo';
    public string $jornada_otro = '';

    // Paso 4
    public $ruta_orden_compra = null;
    public $ruta_manual_funciones = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user && $user->isCliente()) {
            $this->empresa_id = $user->empresa_id;
            $empresa = Empresa::find($this->empresa_id);
            if ($empresa) {
                $this->empresaResultados = [['id' => $empresa->id, 'label' => $empresa->razon_social]];
            }
        }

        $this->fecha_solicitud = now()->format('Y-m-d\TH:i');
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->can('create_solicitud::contrato');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return 'Nueva Solicitud de Contrato';
    }

    // ---------------------------------------------------------------
    // Navegación entre pasos + validación
    // ---------------------------------------------------------------

    public function avanzarPaso(): void
    {
        match ($this->paso) {
            1 => $this->validate($this->reglasPaso1()),
            2 => $this->validate($this->reglasPaso2()),
            3 => $this->validate($this->reglasPaso3()),
            default => null,
        };

        if ($this->paso < 4) {
            $this->paso++;
        }

        if ($this->paso === 3) {
            $this->respaldarLugarDesdeEmpresaSiVacio();
        }
    }

    public function retrocederPaso(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    protected function reglasPaso1(): array
    {
        return [
            'empresa_id' => ['required', 'exists:empresas,id'],
            'tipo_contrato' => ['required', 'string'],
            'fecha_solicitud' => ['required', 'date'],
        ];
    }

    protected function reglasPaso2(): array
    {
        if ($this->usarTrabajadorExistente) {
            return ['trabajador_id' => ['required', 'exists:trabajadores,id']];
        }

        return [
            'trabajador_nombres' => ['required', 'string', 'max:255'],
            'trabajador_apellidos' => ['required', 'string', 'max:255'],
            'trabajador_documento_tipo' => ['required', 'in:CC,CE,TI,PASS'],
            'trabajador_documento_numero' => ['required', 'string', 'max:50'],
            'trabajador_email' => ['required', 'email', 'max:255'],
            'trabajador_telefono' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function reglasPaso3(): array
    {
        $reglas = [
            'cargo_contrato' => ['required_without:cargo_otro'],
            'responsabilidades' => ['required', 'string'],
            'objeto_comercial' => ['required', 'string'],
            'manual_funciones' => ['required', 'string'],
            'departamento' => ['required', 'string'],
            'ciudad' => ['required', 'string'],
            'fecha_fin_contrato' => ['nullable', 'date', 'after_or_equal:fecha_inicio_propuesta'],
            // OJO: NO usar la regla 'numeric' de Laravel directo sobre
            // salario_propuesto - a diferencia del TextInput de Filament (que
            // valida el estado YA sin puntos), acá $this->salario_propuesto
            // SIEMPRE tiene los puntos de miles puestos por
            // updatedSalarioPropuesto() (ej. "2.500.000") - is_numeric()
            // rechazaría cualquier salario de 7+ dígitos por tener más de un
            // punto.
            'salario_propuesto' => ['nullable', function (string $attribute, $value, $fail) {
                $limpio = str_replace('.', '', (string) $value);
                if ($limpio !== '' && (! is_numeric($limpio) || (float) $limpio < 0)) {
                    $fail('El salario debe ser un número válido.');
                }
            }],
        ];

        if ($this->tipo_contrato === 'Contrato de Obra o Labor') {
            $reglas['descripcion_obra_labor'] = ['required', 'string'];
        }

        if ($this->tipo_contrato === 'Contrato Ocasional o Transitorio' && $this->fecha_inicio_propuesta) {
            $reglas['fecha_fin_contrato'][] = 'before_or_equal:' . \Carbon\Carbon::parse($this->fecha_inicio_propuesta)->addDays(30)->toDateString();
        }

        if ($this->jornada === '__otro__') {
            $reglas['jornada_otro'] = ['required', 'string'];
        }

        return $reglas;
    }

    protected function reglasPaso4(): array
    {
        $reglas = [
            'ruta_manual_funciones' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];

        if (in_array($this->tipo_contrato, ['Contrato de Prestación de Servicios', 'Contrato de Obra o Labor'], true)) {
            $reglas['ruta_orden_compra'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }

        return $reglas;
    }

    /**
     * Replica SolicitudContratoResource.php:590-623 (afterStateHydrated de
     * ciudad): si al llegar al Paso 3 departamento/ciudad/lugar_labores
     * siguen vacíos, se rellenan desde la empresa como respaldo.
     */
    protected function respaldarLugarDesdeEmpresaSiVacio(): void
    {
        if (! $this->empresa_id || (filled($this->departamento) && filled($this->ciudad) && filled($this->lugar_labores))) {
            return;
        }

        $empresa = Empresa::find($this->empresa_id);
        if (! $empresa) {
            return;
        }

        $this->departamento ??= $empresa->departamento;
        $this->ciudad ??= $empresa->ciudad;
        $this->lugar_labores ??= collect([$empresa->ciudad, $empresa->departamento])->filter()->implode(', ');
    }

    // ---------------------------------------------------------------
    // Paso 1 - búsqueda de empresa
    // ---------------------------------------------------------------

    public function updatedEmpresaBusqueda(): void
    {
        $this->buscarEmpresas();
    }

    public function buscarEmpresas(): void
    {
        $this->empresaResultados = Empresa::query()
            ->paraAsignar($this->empresa_id)
            ->when($this->empresaBusqueda, fn ($q) => $q->where('razon_social', 'like', "%{$this->empresaBusqueda}%"))
            ->limit(10)
            ->get(['id', 'razon_social'])
            ->map(fn ($e) => ['id' => $e->id, 'label' => $e->razon_social])
            ->toArray();
    }

    public function seleccionarEmpresa(int $id): void
    {
        $this->empresa_id = $id;
        $this->empresaBusqueda = '';
        $this->empresaResultados = [];
        $this->actualizarLugarDesdeEmpresa();
    }

    /**
     * Replica SolicitudContratoResource.php:78-104 (afterStateUpdated de
     * empresa_id): siembra departamento/ciudad/lugar_labores desde la
     * empresa elegida.
     */
    protected function actualizarLugarDesdeEmpresa(): void
    {
        $empresa = $this->empresa_id ? Empresa::find($this->empresa_id) : null;

        if (! $empresa) {
            $this->departamento = null;
            $this->ciudad = null;
            $this->lugar_labores = null;
            return;
        }

        $this->departamento = $empresa->departamento;
        $this->ciudad = $empresa->ciudad;
        $this->lugar_labores = collect([$empresa->ciudad, $empresa->departamento])->filter()->implode(', ');
    }

    // ---------------------------------------------------------------
    // Paso 2 - búsqueda de trabajador
    // ---------------------------------------------------------------

    public function updatedTrabajadorBusqueda(): void
    {
        $this->buscarTrabajadores();
    }

    public function buscarTrabajadores(): void
    {
        $this->trabajadorResultados = Trabajador::query()
            ->when($this->trabajadorBusqueda, function ($q) {
                $termino = $this->trabajadorBusqueda;
                $q->where(fn ($qq) => $qq
                    ->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellidos', 'like', "%{$termino}%")
                    ->orWhere('numero_documento', 'like', "%{$termino}%"));
            })
            ->limit(10)
            ->get()
            ->map(fn (Trabajador $t) => [
                'id' => $t->id,
                'label' => "{$t->nombres} {$t->apellidos} - {$t->tipo_documento}: {$t->numero_documento}",
            ])
            ->toArray();
    }

    public function seleccionarTrabajador(int $id): void
    {
        $trabajador = Trabajador::find($id);
        if (! $trabajador) {
            return;
        }

        $this->trabajador_id = $trabajador->id;
        $this->trabajador_nombres = $trabajador->nombres;
        $this->trabajador_apellidos = $trabajador->apellidos;
        $this->trabajador_documento_tipo = $trabajador->tipo_documento;
        $this->trabajador_documento_numero = $trabajador->numero_documento;
        $this->trabajador_email = $trabajador->email ?? '';
        $this->trabajador_telefono = $trabajador->telefono ?? '';
        $this->trabajador_direccion = $trabajador->direccion ?? '';
        $this->trabajadorBusqueda = '';
        $this->trabajadorResultados = [];
    }

    // ---------------------------------------------------------------
    // Paso 3 - cargo, IA, salario, ubicación
    // ---------------------------------------------------------------

    public function updatedCargoBusqueda(): void
    {
        $this->buscarCargos();
    }

    public function buscarCargos(): void
    {
        $this->cargoResultados = collect(SolicitudContratoResource::getCargos())
            ->filter(fn ($c) => Str::contains(Str::lower($c), Str::lower($this->cargoBusqueda)))
            ->take(10)
            ->map(fn ($c) => ['id' => $c, 'label' => $c])
            ->values()
            ->toArray();
    }

    public function seleccionarCargo(string $cargo): void
    {
        $this->cargo_contrato = $cargo;
        $this->cargoBusqueda = '';
        $this->cargoResultados = [];
    }

    /**
     * Misma lógica de negocio que CompletaDetallesCargoConIA::completarDetallesConIA()
     * (app/Filament/Admin/Resources/SolicitudContratoResource/Concerns/CompletaDetallesCargoConIA.php),
     * reescrita para leer propiedades públicas en vez de $this->data - ESE
     * trait sigue existiendo tal cual, en uso por EditSolicitudContrato
     * (fuera de alcance), no se toca ni se borra.
     */
    public function completarConIA(): void
    {
        $cargo = $this->cargo_contrato === '__otro__' ? $this->cargo_otro : $this->cargo_contrato;

        if (empty($this->empresa_id) || empty($cargo)) {
            Notification::make()
                ->danger()
                ->title('Complete primero la empresa y el cargo')
                ->body('Seleccione la empresa (paso 1) y el cargo (paso 3) antes de completar con IA.')
                ->send();
            return;
        }

        $solicitudTemporal = new SolicitudContrato([
            'empresa_id' => $this->empresa_id,
            'tipo_contrato' => $this->tipo_contrato,
            'cargo_contrato' => $cargo,
            'trabajador_nombres' => $this->trabajador_nombres,
            'trabajador_apellidos' => $this->trabajador_apellidos,
        ]);

        $detalles = app(SolicitudContratoIAService::class)->completarDetallesCargo($solicitudTemporal);

        if (filled($detalles['responsabilidades'] ?? null)) {
            $this->responsabilidades = $detalles['responsabilidades'];
        }
        if (filled($detalles['objeto_comercial'] ?? null)) {
            $this->objeto_comercial = $detalles['objeto_comercial'];
        }
        if (filled($detalles['manual_funciones'] ?? null)) {
            $this->manual_funciones = $detalles['manual_funciones'];
        }

        Notification::make()->success()->title('Detalles del cargo completados con IA')->body('Revise y edite el contenido antes de continuar.')->send();
    }

    public function updatedSalarioPropuesto(?string $value): void
    {
        $this->salario_propuesto = FormateoNumerico::miles($value);
    }

    public function updatedDepartamento(): void
    {
        $this->ciudad = null;
        $this->actualizarLugarLabores();
    }

    public function updatedCiudad(): void
    {
        $this->actualizarLugarLabores();
    }

    protected function actualizarLugarLabores(): void
    {
        $this->lugar_labores = collect([$this->ciudad, $this->departamento])->filter()->implode(', ');
    }

    public function getCiudadesDisponibles(): array
    {
        return SolicitudContratoResource::getCiudadesPorDepartamento($this->departamento);
    }

    // ---------------------------------------------------------------
    // Guardar (Paso 4)
    // ---------------------------------------------------------------

    public function guardar(): void
    {
        $this->validate(array_merge(
            $this->reglasPaso1(),
            $this->reglasPaso2(),
            $this->reglasPaso3(),
            $this->reglasPaso4(),
        ));

        $cargo = $this->cargo_contrato === '__otro__' ? $this->cargo_otro : $this->cargo_contrato;
        $jornadaFinal = $this->jornada === '__otro__' ? $this->jornada_otro : $this->jornada;

        $solicitud = SolicitudContrato::create([
            'empresa_id' => $this->empresa_id,
            'estado' => 'borrador',
            'tipo_contrato' => $this->tipo_contrato,
            'fecha_solicitud' => $this->fecha_solicitud,
            'trabajador_id' => $this->usarTrabajadorExistente ? $this->trabajador_id : null,
            'trabajador_nombres' => $this->trabajador_nombres,
            'trabajador_apellidos' => $this->trabajador_apellidos,
            'trabajador_documento_tipo' => $this->trabajador_documento_tipo,
            'trabajador_documento_numero' => $this->trabajador_documento_numero,
            'trabajador_email' => $this->trabajador_email,
            'trabajador_telefono' => $this->trabajador_telefono,
            'trabajador_direccion' => $this->trabajador_direccion,
            'cargo_contrato' => $cargo,
            'jornada' => $jornadaFinal,
            'responsabilidades' => $this->responsabilidades,
            'objeto_comercial' => $this->objeto_comercial,
            'manual_funciones' => $this->manual_funciones,
            'descripcion_obra_labor' => $this->descripcion_obra_labor,
            'fecha_inicio_propuesta' => $this->fecha_inicio_propuesta,
            'fecha_fin_contrato' => $this->fecha_fin_contrato,
            'salario_propuesto' => $this->salario_propuesto ? (float) str_replace('.', '', $this->salario_propuesto) : null,
            'periodo_pago' => $this->periodo_pago,
            'lugar_labores' => $this->lugar_labores,
            'ruta_orden_compra' => $this->ruta_orden_compra?->store('solicitudes-contratos/ordenes-compra', 'public'),
            'ruta_manual_funciones' => $this->ruta_manual_funciones?->store('solicitudes-contratos/manuales-funciones', 'public'),
        ]);

        // --- Trasladado tal cual de CreateSolicitudContrato::afterCreate() ---
        try {
            $service = app(SolicitudContratoIAService::class);

            if (empty($solicitud->objeto_juridico_redactado)) {
                $texto = $service->redactarObjetoJuridico($solicitud);
                $solicitud->update(['objeto_juridico_redactado' => $texto]);
            }

            if ($solicitud->tipo_contrato === 'Contrato de Obra o Labor'
                && empty($solicitud->duracion_terminacion_obra_redactada)) {
                $duracionTerminacion = $service->redactarDuracionTerminacionObraLabor($solicitud);
                $solicitud->update(['duracion_terminacion_obra_redactada' => $duracionTerminacion]);
            }

            $service->generarContratoPDF($solicitud, borrador: true);
        } catch (\Throwable $e) {
            Log::error('SolicitudContrato: falló la generación automática del borrador', [
                'solicitud_id' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);

            $solicitud->update(['estado' => 'borrador']);

            Notification::make()
                ->warning()
                ->title('La solicitud se creó, pero el borrador no se pudo generar automáticamente')
                ->body('Use "Regenerar Borrador" desde el listado para intentarlo de nuevo.')
                ->send();
        }
        // --- Fin del bloque trasladado ---

        $this->redirect(SolicitudContratoResource::getUrl('view', ['record' => $solicitud]));
    }
}
