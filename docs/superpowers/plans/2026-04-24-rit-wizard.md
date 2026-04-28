# RIT Wizard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the custom blade RIT builder page with a proper Filament wizard (7 steps, `CreateRecord + HasWizard`) backed by `ReglamentoInterno`, with actividades from DB, conditional fields, and Repeaters.

**Architecture:** New `ReglamentoInternoResource` (nav hidden) registers route `/admin/reglamentos-internos/create`. `CreateReglamentoInterno` uses `HasWizard` to collect questionnaire data; `handleRecordCreation()` serializes everything to `respuestas_cuestionario` JSON, calls `RITGeneratorService`, and saves the record. Existing `RITBuilder` page is hidden but stays for the download route.

**Tech Stack:** Laravel 11, Filament v3 (Forms, Resources, HasWizard), Livewire, `RITGeneratorService` (already exists).

---

## File Map

| Action | File |
|--------|------|
| **Create** | `app/Filament/Admin/Resources/ReglamentoInternoResource.php` |
| **Create** | `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php` |
| **Modify** | `app/Filament/Admin/Resources/RitBuilderResource.php` — hide from nav |
| **Modify** | `app/Filament/Admin/Pages/RITBuilder.php` — hide from nav |
| **Modify** | `resources/views/filament/admin/pages/dashboard.blade.php` — update link |
| **Modify** | `app/Filament/Admin/Pages/Auth/Register.php` — update redirect URL |

---

## Task 1: Resource stub + nav cleanup + link updates

**Files:**
- Create: `app/Filament/Admin/Resources/ReglamentoInternoResource.php`
- Create: `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`
- Modify: `app/Filament/Admin/Resources/RitBuilderResource.php`
- Modify: `app/Filament/Admin/Pages/RITBuilder.php`
- Modify: `resources/views/filament/admin/pages/dashboard.blade.php`
- Modify: `app/Filament/Admin/Pages/Auth/Register.php`

- [ ] **Step 1.1: Crear directorio de Pages**

```bash
mkdir -p "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages"
```

- [ ] **Step 1.2: Crear `ReglamentoInternoResource.php`**

Crear `app/Filament/Admin/Resources/ReglamentoInternoResource.php`:

```php
<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;
use App\Models\ReglamentoInterno;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ReglamentoInternoResource extends Resource
{
    protected static ?string $model = ReglamentoInterno::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    // Oculto del menú lateral — solo accesible por enlace directo
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreateReglamentoInterno::route('/create'),
        ];
    }
}
```

- [ ] **Step 1.3: Crear `CreateReglamentoInterno.php` (esqueleto)**

Crear `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`:

```php
<?php

namespace App\Filament\Admin\Resources\ReglamentoInternoResource\Pages;

use App\Filament\Admin\Resources\ReglamentoInternoResource;
use App\Models\ActividadEconomica;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Services\RITGeneratorService;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class CreateReglamentoInterno extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ReglamentoInternoResource::class;

    protected function getSteps(): array
    {
        return [
            // Steps se agregan en las siguientes tareas
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Implementado en Task 4
        return new ReglamentoInterno();
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.pages.dashboard');
    }

    private function getEmpresa(): ?Empresa
    {
        $user = Auth::user();
        if (!$user) return null;
        if ($user->hasRole('super_admin') || $user->hasRole('abogado')) {
            return Empresa::first();
        }
        return $user->empresa ?? null;
    }
}
```

- [ ] **Step 1.4: Ocultar `RitBuilderResource` del nav**

Editar `app/Filament/Admin/Resources/RitBuilderResource.php`. Reemplazar la línea:

```php
protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
```

Por:

```php
protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

public static function shouldRegisterNavigation(): bool
{
    return false;
}
```

- [ ] **Step 1.5: Ocultar `RITBuilder` Page del nav**

Editar `app/Filament/Admin/Pages/RITBuilder.php`. Reemplazar:

```php
protected static ?string $navigationGroup = 'Configuración';

protected static ?int $navigationSort = 20;
```

Por:

```php
protected static ?string $navigationGroup = 'Configuración';

protected static ?int $navigationSort = 20;

public static function shouldRegisterNavigation(): bool
{
    return false;
}
```

- [ ] **Step 1.6: Actualizar link en dashboard.blade.php**

En `resources/views/filament/admin/pages/dashboard.blade.php`, reemplazar:

```blade
<a href="{{ route('filament.admin.pages.rit-builder') }}"
```

Por:

```blade
<a href="{{ route('filament.admin.resources.reglamentos-internos.create') }}"
```

- [ ] **Step 1.7: Actualizar redirect en Register.php**

En `app/Filament/Admin/Pages/Auth/Register.php`, línea ~321, reemplazar:

```php
$this->redirectUrl = route('filament.admin.pages.rit-builder');
```

Por:

```php
$this->redirectUrl = route('filament.admin.resources.reglamentos-internos.create');
```

- [ ] **Step 1.8: Verificar que la ruta existe**

```bash
cd /c/laragon/www/ces-legal && php artisan route:list --name=reglamentos-internos
```

Resultado esperado: debe mostrar la ruta `admin/reglamentos-internos/create`.

- [ ] **Step 1.9: Commit**

```bash
git add app/Filament/Admin/Resources/ReglamentoInternoResource.php \
        "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php" \
        app/Filament/Admin/Resources/RitBuilderResource.php \
        app/Filament/Admin/Pages/RITBuilder.php \
        resources/views/filament/admin/pages/dashboard.blade.php \
        app/Filament/Admin/Pages/Auth/Register.php
git commit -m "feat: scaffold ReglamentoInternoResource wizard + hide old RIT nav items"
```

---

## Task 2: Steps 1-3 del wizard (Empresa, Estructura, Jornada)

**Files:**
- Modify: `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`

- [ ] **Step 2.1: No se necesita helper de opciones** — los Selects de actividad usan `getSearchResultsUsing` (búsqueda server-side). No agregar ningún método helper.

- [ ] **Step 2.2: Reemplazar `getSteps()` con Steps 1-3**

Reemplazar el método `getSteps()` completo con:

```php
protected function getSteps(): array
{
    $empresa = $this->getEmpresa();

    return [
        // ── Step 1: Empresa y Actividad Económica ────────────────────────────
        Step::make('empresa')
            ->label('Empresa')
            ->description('Datos generales')
            ->icon('heroicon-o-building-office-2')
            ->schema([
                Forms\Components\Section::make('Datos de la empresa')
                    ->schema([
                        Forms\Components\TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->default($empresa?->razon_social ?? '')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nit')
                            ->label('NIT')
                            ->default($empresa?->nit ?? '')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('domicilio')
                            ->label('Domicilio principal')
                            ->default(trim(
                                ($empresa?->direccion ?? '') . ' ' .
                                ($empresa?->ciudad ?? '') . ', ' .
                                ($empresa?->departamento ?? '')
                            ))
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Actividad económica')
                    ->schema([
                        Forms\Components\Select::make('actividad_economica_id')
                            ->label('Actividad económica principal')
                            ->searchable()
                            ->getSearchResultsUsing(fn(string $search) =>
                                ActividadEconomica::query()
                                    ->where('activo', true)
                                    ->where(fn($q) => $q
                                        ->where('codigo', 'like', "%{$search}%")
                                        ->orWhere('nombre', 'like', "%{$search}%")
                                    )
                                    ->orderBy('codigo')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                    ->all()
                            )
                            ->getOptionLabelUsing(fn($value) =>
                                ($a = ActividadEconomica::find($value))
                                    ? "{$a->codigo} — {$a->nombre}"
                                    : $value
                            )
                            ->default($empresa?->actividad_economica_id)
                            ->nullable()
                            ->placeholder('Buscar por código o nombre...')
                            ->helperText('Actividad principal según el RUT de la empresa')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('actividades_secundarias_ids')
                            ->label('Actividades secundarias')
                            ->searchable()
                            ->multiple()
                            ->getSearchResultsUsing(fn(string $search) =>
                                ActividadEconomica::query()
                                    ->where('activo', true)
                                    ->where(fn($q) => $q
                                        ->where('codigo', 'like', "%{$search}%")
                                        ->orWhere('nombre', 'like', "%{$search}%")
                                    )
                                    ->orderBy('codigo')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($a) => [$a->id => "{$a->codigo} — {$a->nombre}"])
                                    ->all()
                            )
                            ->getOptionLabelUsing(fn($value) =>
                                ($a = ActividadEconomica::find($value))
                                    ? "{$a->codigo} — {$a->nombre}"
                                    : $value
                            )
                            ->default($empresa?->actividadesSecundarias?->pluck('id')->toArray() ?? [])
                            ->nullable()
                            ->placeholder('Buscar por código o nombre...')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Trabajadores y sucursales')
                    ->schema([
                        Forms\Components\TextInput::make('num_trabajadores')
                            ->label('Número de trabajadores')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->placeholder('Ej: 15'),

                        Forms\Components\Radio::make('tiene_sucursales')
                            ->label('¿Tiene sucursales o sedes adicionales?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline()
                            ->live(),
                    ])
                    ->columns(2),

                Forms\Components\Repeater::make('sucursales')
                    ->label('Sucursales / Sedes')
                    ->schema([
                        Forms\Components\TextInput::make('ciudad')
                            ->label('Ciudad')
                            ->required()
                            ->placeholder('Ej: Medellín'),
                        Forms\Components\TextInput::make('direccion')
                            ->label('Dirección')
                            ->placeholder('Ej: Calle 50 # 40-20'),
                        Forms\Components\TextInput::make('num_trabajadores')
                            ->label('N.° trabajadores')
                            ->numeric()
                            ->required()
                            ->placeholder('Ej: 5'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Agregar sucursal')
                    ->minItems(1)
                    ->visible(fn(Get $get) => $get('tiene_sucursales') === 'si')
                    ->columnSpanFull(),
            ]),

        // ── Step 2: Estructura y Contratos ──────────────────────────────────
        Step::make('estructura')
            ->label('Estructura')
            ->description('Cargos y contratos')
            ->icon('heroicon-o-users')
            ->schema([
                Forms\Components\Section::make('Cargos de la empresa')
                    ->description('Liste cada cargo que existe en su empresa y marque cuáles tienen facultad para imponer sanciones disciplinarias.')
                    ->schema([
                        Forms\Components\Repeater::make('cargos')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('nombre_cargo')
                                    ->label('Nombre del cargo')
                                    ->required()
                                    ->placeholder('Ej: Gerente General'),
                                Forms\Components\Toggle::make('puede_sancionar')
                                    ->label('¿Puede sancionar?')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->addActionLabel('Agregar cargo')
                            ->minItems(1)
                            ->defaultItems(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Documentación y contratos')
                    ->schema([
                        Forms\Components\Select::make('tiene_manual_funciones')
                            ->label('¿Tiene manual de funciones?')
                            ->options([
                                'si'              => 'Sí',
                                'no'              => 'No',
                                'en_construccion' => 'En construcción',
                            ])
                            ->default('no')
                            ->native(false),

                        Forms\Components\CheckboxList::make('tipos_contrato')
                            ->label('Tipos de contrato que maneja la empresa')
                            ->options([
                                'indefinido'  => 'Término indefinido',
                                'fijo'        => 'Término fijo',
                                'obra_labor'  => 'Obra o labor',
                                'aprendizaje' => 'Aprendizaje SENA',
                            ])
                            ->default(['indefinido'])
                            ->columns(2),

                        Forms\Components\Radio::make('tiene_trabajadores_mision')
                            ->label('¿Tiene trabajadores en misión o temporales (empresas de servicios temporales)?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline(),
                    ])
                    ->columns(1),
            ]),

        // ── Step 3: Jornada Laboral ──────────────────────────────────────────
        Step::make('jornada')
            ->label('Jornada')
            ->description('Horarios y turnos')
            ->icon('heroicon-o-clock')
            ->schema([
                Forms\Components\Section::make('Horario ordinario')
                    ->schema([
                        Forms\Components\TextInput::make('horario_entrada')
                            ->label('Hora de entrada')
                            ->required()
                            ->placeholder('Ej: 8:00 a.m.'),

                        Forms\Components\TextInput::make('horario_salida')
                            ->label('Hora de salida')
                            ->required()
                            ->placeholder('Ej: 5:00 p.m.'),

                        Forms\Components\Radio::make('trabaja_sabados')
                            ->label('¿Trabaja los sábados?')
                            ->options([
                                'no'            => 'No',
                                'media_jornada' => 'Sí, media jornada',
                                'dia_completo'  => 'Sí, jornada completa',
                            ])
                            ->default('no')
                            ->inline()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('horario_salida_sabado')
                            ->label('Hora de salida los sábados')
                            ->placeholder('Ej: 1:00 p.m.')
                            ->visible(fn(Get $get) => $get('trabaja_sabados') !== 'no')
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('trabaja_dominicales')
                            ->label('¿Trabaja domingos o festivos?')
                            ->options([
                                'no'            => 'No',
                                'ocasionalmente' => 'Ocasionalmente',
                                'regularmente'  => 'Regularmente',
                            ])
                            ->default('no')
                            ->inline()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Turnos y control')
                    ->schema([
                        Forms\Components\Radio::make('tiene_turnos')
                            ->label('¿Tiene turnos rotativos o nocturnos?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline()
                            ->live(),

                        Forms\Components\Textarea::make('descripcion_turnos')
                            ->label('Describa los turnos')
                            ->rows(2)
                            ->placeholder('Ej: Turno A 6am-2pm, Turno B 2pm-10pm, Turno C 10pm-6am')
                            ->visible(fn(Get $get) => $get('tiene_turnos') === 'si'),

                        Forms\Components\Select::make('control_asistencia')
                            ->label('¿Cómo controla la asistencia?')
                            ->options([
                                'biometrico'    => 'Reloj biométrico',
                                'planilla'      => 'Planilla manual',
                                'app'           => 'App móvil',
                                'sin_control'   => 'Sin control formal',
                            ])
                            ->default('planilla')
                            ->native(false),

                        Forms\Components\Select::make('politica_horas_extras')
                            ->label('Política de horas extras')
                            ->options([
                                'recargo_legal'  => 'Se pagan con recargo legal',
                                'no_autorizadas' => 'No se autorizan',
                                'compensatorio'  => 'Compensatorio en tiempo',
                            ])
                            ->default('recargo_legal')
                            ->native(false),
                    ])
                    ->columns(2),
            ]),
    ];
}
```

- [ ] **Step 2.3: Verificar que PHP no tiene errores de sintaxis**

```bash
php -l "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
```

Resultado esperado: `No syntax errors detected`

- [ ] **Step 2.4: Verificar que la página carga sin error 500**

```bash
php artisan route:list --name=reglamentos-internos
```

Resultado esperado: la ruta `admin/reglamentos-internos/create` aparece listada.

- [ ] **Step 2.5: Commit**

```bash
git add "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
git commit -m "feat: wizard RIT steps 1-3 (empresa, estructura, jornada)"
```

---

## Task 3: Steps 4-6 del wizard (Salario, Régimen, SST y Conducta)

**Files:**
- Modify: `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`

- [ ] **Step 3.1: Añadir Steps 4-6 al array en `getSteps()`**

En `getSteps()`, después del Step 3 (`jornada`) y antes del cierre `];`, agregar:

```php
        // ── Step 4: Salario y Beneficios ────────────────────────────────────
        Step::make('salario')
            ->label('Salario')
            ->description('Pago y beneficios')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Forms\Components\Section::make('Forma y periodicidad de pago')
                    ->schema([
                        Forms\Components\Select::make('forma_pago')
                            ->label('Forma de pago del salario')
                            ->options([
                                'transferencia' => 'Transferencia bancaria',
                                'cheque'        => 'Cheque',
                                'efectivo'      => 'Efectivo',
                                'mixto'         => 'Mixto (transferencia y efectivo)',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('periodicidad_pago')
                            ->label('Periodicidad de pago')
                            ->options([
                                'mensual'    => 'Mensual (último día hábil)',
                                'quincenal'  => 'Quincenal (15 y último día)',
                                'semanal'    => 'Semanal',
                            ])
                            ->default('mensual')
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Comisiones y beneficios')
                    ->schema([
                        Forms\Components\Radio::make('maneja_comisiones')
                            ->label('¿Maneja comisiones o bonos?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline()
                            ->live(),

                        Forms\Components\Select::make('tipo_comisiones')
                            ->label('Tipo de comisiones / bonos')
                            ->options([
                                'comisiones_ventas'  => 'Comisiones de ventas',
                                'bonos_desempeno'    => 'Bonos por desempeño',
                                'ambos'              => 'Ambos',
                            ])
                            ->native(false)
                            ->visible(fn(Get $get) => $get('maneja_comisiones') === 'si'),

                        Forms\Components\Radio::make('tiene_beneficios_extralegales')
                            ->label('¿Otorga beneficios extralegales?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('beneficios_extralegales')
                            ->label('Beneficios extralegales')
                            ->schema([
                                Forms\Components\TextInput::make('descripcion')
                                    ->label('Descripción')
                                    ->required()
                                    ->placeholder('Ej: Auxilio de alimentación $150.000/mes'),
                            ])
                            ->addActionLabel('Agregar beneficio')
                            ->visible(fn(Get $get) => $get('tiene_beneficios_extralegales') === 'si')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permisos y licencias')
                    ->schema([
                        Forms\Components\Select::make('politica_permisos')
                            ->label('¿Cómo maneja los permisos personales?')
                            ->options([
                                'solicitud_anticipada' => 'Solicitud escrita con 24h de anticipación',
                                'sin_politica'         => 'Sin política formal',
                            ])
                            ->default('solicitud_anticipada')
                            ->native(false)
                            ->columnSpanFull(),

                        Forms\Components\Radio::make('tiene_licencias_especiales')
                            ->label('¿Otorga licencias especiales adicionales a las legales?')
                            ->options(['no' => 'No', 'si' => 'Sí'])
                            ->default('no')
                            ->inline()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('descripcion_licencias')
                            ->label('Describa las licencias especiales')
                            ->rows(2)
                            ->placeholder('Ej: Licencia de matrimonio 1 día remunerado, calamidad doméstica 3 días')
                            ->visible(fn(Get $get) => $get('tiene_licencias_especiales') === 'si')
                            ->columnSpanFull(),
                    ]),
            ]),

        // ── Step 5: Régimen Disciplinario ────────────────────────────────────
        Step::make('disciplina')
            ->label('Disciplina')
            ->description('Faltas y sanciones')
            ->icon('heroicon-o-scale')
            ->schema([
                Forms\Components\Section::make('Clasificación de faltas')
                    ->description('Escriba ejemplos de faltas y presione Enter para agregarlas. Puede agregar las que necesite.')
                    ->schema([
                        Forms\Components\TagsInput::make('faltas_leves')
                            ->label('Faltas leves')
                            ->suggestions([
                                'Impuntualidad',
                                'No registrar asistencia',
                                'No usar el uniforme',
                                'Uso de celular en horario laboral',
                                'Desorden en el puesto de trabajo',
                            ])
                            ->placeholder('Escriba una falta y presione Enter')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('faltas_graves')
                            ->label('Faltas graves')
                            ->suggestions([
                                'Agresión verbal a compañero',
                                'Ausentismo sin justificación',
                                'Incumplir normas de seguridad',
                                'Desobedecer órdenes del superior',
                                'Daño a bienes de la empresa',
                            ])
                            ->placeholder('Escriba una falta y presione Enter')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('faltas_muy_graves')
                            ->label('Faltas muy graves')
                            ->suggestions([
                                'Hurto',
                                'Agresión física',
                                'Acoso sexual',
                                'Divulgación de secretos empresariales',
                                'Presentarse en estado de embriaguez',
                                'Falsificación de documentos',
                            ])
                            ->placeholder('Escriba una falta y presione Enter')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Sanciones a aplicar')
                    ->schema([
                        Forms\Components\CheckboxList::make('sanciones_contempladas')
                            ->label('Sanciones que contempla aplicar')
                            ->options([
                                'llamado_verbal'   => 'Llamado de atención verbal',
                                'llamado_escrito'  => 'Llamado de atención escrito',
                                'suspension_1_3'   => 'Suspensión 1-3 días',
                                'suspension_4_8'   => 'Suspensión 4-8 días',
                                'terminacion'      => 'Terminación con justa causa',
                            ])
                            ->default(['llamado_verbal', 'llamado_escrito', 'suspension_1_3', 'terminacion'])
                            ->columns(2),
                    ]),
            ]),

        // ── Step 6: SST y Conducta ───────────────────────────────────────────
        Step::make('sst_conducta')
            ->label('SST y Conducta')
            ->description('Seguridad y comportamiento')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Forms\Components\Section::make('Seguridad y Salud en el Trabajo')
                    ->schema([
                        Forms\Components\Select::make('tiene_sg_sst')
                            ->label('¿Tiene implementado el SG-SST?')
                            ->options([
                                'implementado'  => 'Sí, implementado',
                                'en_proceso'    => 'En proceso de implementación',
                                'no'            => 'No',
                            ])
                            ->default('en_proceso')
                            ->native(false),

                        Forms\Components\CheckboxList::make('riesgos_principales')
                            ->label('Principales riesgos en su operación')
                            ->options([
                                'ergonomico'  => 'Ergonómico (trabajo en pantallas, postura)',
                                'psicosocial' => 'Psicosocial (estrés, turnos)',
                                'mecanico'    => 'Mecánico (maquinaria, herramientas)',
                                'electrico'   => 'Eléctrico',
                                'publico'     => 'Público (atención al cliente)',
                                'otro'        => 'Otro',
                            ])
                            ->default(['ergonomico'])
                            ->live()
                            ->columns(2),

                        Forms\Components\TextInput::make('riesgos_otros')
                            ->label('Describa el otro riesgo')
                            ->placeholder('Ej: Riesgo químico por manejo de solventes')
                            ->visible(fn(Get $get) => in_array('otro', (array) $get('riesgos_principales'))),

                        Forms\Components\Radio::make('tiene_epp')
                            ->label('¿Requiere elementos de protección personal (EPP)?')
                            ->options([
                                'no'  => 'No aplica — trabajo de oficina',
                                'si'  => 'Sí, se requieren',
                            ])
                            ->default('no')
                            ->inline()
                            ->live(),

                        Forms\Components\TextInput::make('epp_descripcion')
                            ->label('EPP requeridos')
                            ->placeholder('Ej: Casco, guantes, botas de seguridad')
                            ->visible(fn(Get $get) => $get('tiene_epp') === 'si'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Conducta y uso de recursos')
                    ->schema([
                        Forms\Components\Select::make('politica_celular')
                            ->label('Política de uso de celular personal en horario laboral')
                            ->options([
                                'libre'        => 'Libre uso',
                                'descansos'    => 'Solo en descansos',
                                'prohibido'    => 'Prohibido salvo emergencias',
                            ])
                            ->default('descansos')
                            ->native(false),

                        Forms\Components\Radio::make('usa_uniforme')
                            ->label('¿La empresa entrega uniforme o dotación?')
                            ->options([
                                'no'              => 'No',
                                'uniforme'        => 'Sí, uniforme completo',
                                'dotacion_basica' => 'Sí, dotación básica',
                            ])
                            ->default('no')
                            ->inline(),

                        Forms\Components\Radio::make('tiene_codigo_etica')
                            ->label('¿Tiene código de ética o conducta?')
                            ->options([
                                'si'              => 'Sí',
                                'no'              => 'No',
                                'en_construccion' => 'En construcción',
                            ])
                            ->default('no')
                            ->inline(),

                        Forms\Components\Select::make('politica_confidencialidad')
                            ->label('Cláusula de confidencialidad')
                            ->options([
                                'por_contrato' => 'Sí, incluida en el contrato',
                                'solo_verbal'  => 'Solo verbal',
                                'no'           => 'No',
                            ])
                            ->default('no')
                            ->native(false),

                        Forms\Components\Textarea::make('que_quiere_prevenir')
                            ->label('¿Qué conductas quiere prevenir principalmente con este RIT? (opcional)')
                            ->rows(2)
                            ->placeholder('Ej: Impuntualidad crónica, conflictos entre compañeros, uso indebido de información')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]),
```

- [ ] **Step 3.2: Verificar sintaxis**

```bash
php -l "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
```

Resultado esperado: `No syntax errors detected`

- [ ] **Step 3.3: Commit**

```bash
git add "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
git commit -m "feat: wizard RIT steps 4-6 (salario, disciplina, SST/conducta)"
```

---

## Task 4: Step 7 (Revisión + Generar) y `handleRecordCreation()`

**Files:**
- Modify: `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`

- [ ] **Step 4.1: Añadir Step 7 al array en `getSteps()`**

Después del Step 6 (`sst_conducta`) y antes del cierre `];`, agregar:

```php
        // ── Step 7: Revisión y Generar ───────────────────────────────────────
        Step::make('revision')
            ->label('Generar')
            ->description('Revisar y construir')
            ->icon('heroicon-o-cpu-chip')
            ->schema([
                Forms\Components\Placeholder::make('aviso_revision')
                    ->label('')
                    ->content(fn() => new HtmlString(
                        '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-700">'
                        . '<p class="font-semibold text-blue-900 dark:text-blue-100">Listo para generar su Reglamento Interno</p>'
                        . '<p class="text-sm text-blue-700 dark:text-blue-300 mt-1">'
                        . 'Al hacer clic en "Crear" se enviará la información a la IA para redactar el RIT completo. '
                        . 'Este proceso puede tardar hasta 60 segundos. No cierre la ventana.'
                        . '</p>'
                        . '<p class="text-xs text-blue-600 dark:text-blue-400 mt-2">'
                        . '<strong>Importante:</strong> El documento generado debe ser revisado por un abogado laboral '
                        . 'antes de presentarlo ante el Ministerio del Trabajo.'
                        . '</p>'
                        . '</div>'
                    ))
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('resumen_empresa')
                    ->label('Resumen de sus respuestas')
                    ->content(fn(Get $get) => new HtmlString(
                        '<div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">'
                        . '<div><strong>Trabajadores:</strong> ' . ($get('num_trabajadores') ?: '—') . '</div>'
                        . '<div><strong>Horario:</strong> ' . ($get('horario_entrada') ?: '—') . ' — ' . ($get('horario_salida') ?: '—') . '</div>'
                        . '<div><strong>Forma de pago:</strong> ' . ($get('forma_pago') ?: '—') . '</div>'
                        . '<div><strong>Turnos:</strong> ' . ($get('tiene_turnos') === 'si' ? 'Sí' : 'No') . '</div>'
                        . '<div><strong>SG-SST:</strong> ' . ($get('tiene_sg_sst') ?: '—') . '</div>'
                        . '</div>'
                    ))
                    ->columnSpanFull(),
            ]),
    ];
}
```

- [ ] **Step 4.2: Reemplazar `handleRecordCreation()` con la implementación completa**

Reemplazar el método `handleRecordCreation(array $data): Model` placeholder completo con:

```php
protected function handleRecordCreation(array $data): Model
{
    $empresa = $this->getEmpresa();

    if (!$empresa) {
        Notification::make()
            ->danger()
            ->title('Error')
            ->body('No se encontró la empresa asociada a su cuenta.')
            ->send();
        // Retornar modelo vacío para no romper Filament
        return new ReglamentoInterno();
    }

    // ── 1. Resolver actividad económica → texto ──────────────────────────
    $actividadId = $data['actividad_economica_id'] ?? null;
    if ($actividadId) {
        $actividad = ActividadEconomica::find($actividadId);
        $data['actividad_economica'] = $actividad
            ? "{$actividad->codigo} — {$actividad->nombre}"
            : '';
    }
    unset($data['actividad_economica_id']);

    $actividadesIds = $data['actividades_secundarias_ids'] ?? [];
    if (!empty($actividadesIds)) {
        $actividades = ActividadEconomica::whereIn('id', $actividadesIds)->get();
        $data['actividades_secundarias'] = $actividades
            ->map(fn($a) => "{$a->codigo} — {$a->nombre}")
            ->join(', ');
    }
    unset($data['actividades_secundarias_ids']);

    // ── 2. Añadir datos de la empresa (razon social, nit, domicilio) ─────
    $data['razon_social'] = $empresa->razon_social ?? '';
    $data['nit']          = $empresa->nit ?? '';
    $data['domicilio']    = trim(
        ($empresa->direccion ?? '') . ' ' .
        ($empresa->ciudad ?? '') . ', ' .
        ($empresa->departamento ?? '')
    );

    // ── 3. Llamar a la IA ─────────────────────────────────────────────────
    try {
        $service  = app(RITGeneratorService::class);
        $textoRIT = $service->generarTextoRIT($data, $empresa);
    } catch (\Exception $e) {
        Log::error('Error generando RIT con IA', [
            'empresa_id' => $empresa->id,
            'error'      => $e->getMessage(),
        ]);
        Notification::make()
            ->danger()
            ->title('Error al generar el RIT')
            ->body('No se pudo conectar con la IA. Intente nuevamente en unos minutos.')
            ->send();
        return new ReglamentoInterno();
    }

    // ── 4. Guardar en BD ─────────────────────────────────────────────────
    $record = ReglamentoInterno::updateOrCreate(
        ['empresa_id' => $empresa->id],
        [
            'nombre'                  => 'Reglamento Interno generado con IA — ' . now()->format('d/m/Y'),
            'texto_completo'          => $textoRIT,
            'activo'                  => true,
            'respuestas_cuestionario' => $data,
            'fuente'                  => 'construido_ia',
        ]
    );

    // ── 5. Generar documento Word ─────────────────────────────────────────
    try {
        $service->generarDocumentoWord($textoRIT, $empresa);
    } catch (\Exception $e) {
        Log::warning('Error generando DOCX del RIT', [
            'empresa_id' => $empresa->id,
            'error'      => $e->getMessage(),
        ]);
        // No fatal — el texto quedó guardado en BD
    }

    // ── 6. Notificar éxito ───────────────────────────────────────────────
    Notification::make()
        ->success()
        ->title('¡Reglamento Interno generado!')
        ->body('Su RIT fue redactado con IA y guardado. Puede descargarlo desde la sección de Configuración.')
        ->send();

    return $record;
}
```

- [ ] **Step 4.3: Verificar sintaxis**

```bash
php -l "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
```

Resultado esperado: `No syntax errors detected`

- [ ] **Step 4.4: Verificar que Filament registra el recurso sin errores**

```bash
php artisan optimize:clear 2>&1 | tail -3
php artisan route:list --name=reglamentos-internos
```

Resultado esperado: la ruta `admin/reglamentos-internos/create` aparece en la lista.

- [ ] **Step 4.5: Commit**

```bash
git add "app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
git commit -m "feat: wizard RIT step 7 (revisión) + handleRecordCreation con IA"
```

---

## Task 5: Verificación manual end-to-end

**Files:** ninguno (solo verificación)

- [ ] **Step 5.1: Abrir el wizard en el navegador**

Navegar a `http://localhost/admin/reglamentos-internos/create` (o el dominio local del proyecto).
Verificar que se muestra el wizard con Step 1 activo y los campos de empresa pre-llenados (razón social, NIT, domicilio en gris/disabled).

- [ ] **Step 5.2: Verificar actividades económicas**

En el campo "Actividad económica principal", escribir parte de un código CIIU (ej: "4711").
Verificar que aparecen opciones del dropdown con formato `XXXX — Nombre de la actividad`.

- [ ] **Step 5.3: Verificar condicional de sucursales**

Cambiar "¿Tiene sucursales?" a "Sí".
Verificar que aparece el Repeater de sucursales.
Volver a "No" y verificar que el Repeater desaparece.

- [ ] **Step 5.4: Verificar Step 2 — cargos**

Navegar al Step 2.
Verificar que el Repeater de cargos tiene 2 filas por defecto.
Agregar un cargo nuevo con el botón "Agregar cargo".

- [ ] **Step 5.5: Verificar condicionales en Steps 3-6**

- Step 3: Cambiar "¿Trabaja los sábados?" a "Sí, media jornada" → verificar que aparece campo de hora salida sábado.
- Step 3: Cambiar "¿Tiene turnos?" a "Sí" → verificar descripción de turnos.
- Step 4: Cambiar "¿Maneja comisiones?" a "Sí" → verificar Select de tipo.
- Step 4: Cambiar "¿Beneficios extralegales?" a "Sí" → verificar Repeater.
- Step 6: Marcar "Otro" en riesgos → verificar TextInput de descripción.
- Step 6: Cambiar EPP a "Sí" → verificar campo descripción EPP.

- [ ] **Step 5.6: Verificar Step 5 — TagsInput**

En el Step 5, escribir "Robo de mercancía" en Faltas muy graves y presionar Enter.
Verificar que se agrega como tag.

- [ ] **Step 5.7: Verificar el dashboard sin RIT**

Con una empresa que NO tiene RIT activo, verificar que el banner amarillo en el dashboard muestra el link "Construir RIT" y que ese link lleva a `/admin/reglamentos-internos/create`.

- [ ] **Step 5.8: Verificar que la Page RITBuilder y RitBuilderResource no aparecen en el menú lateral**

Verificar visualmente que el menú de navegación lateral ya no muestra "Construir RIT" ni "Rit Builders".

- [ ] **Step 5.9: Commit final**

```bash
git add .
git commit -m "chore: verificación manual completada — RIT wizard MVP funcional"
```

---

## Notas de implementación

### Por qué `dehydrated(false)` en los campos disabled del Step 1

Los campos `razon_social`, `nit` y `domicilio` son de solo lectura y no deben incluirse en los `$data` que Filament envía a `handleRecordCreation`. `->dehydrated(false)` evita que se incluyan. Los datos reales se leen directamente desde `$empresa` en `handleRecordCreation`.

### Por qué `updateOrCreate` en vez de `create`

Una empresa puede tener solo un RIT activo. Si el usuario completa el wizard dos veces, el segundo reemplaza al primero en lugar de crear un duplicado.

### Timeout del wizard con la IA

Gemini puede tardar hasta 90 segundos. Filament no tiene timeout en el botón "Crear" del wizard — el spinner seguirá hasta que el servidor responda o PHP timeout. El `timeout(90)` en `Http::post()` en `RITGeneratorService` controla esto.
