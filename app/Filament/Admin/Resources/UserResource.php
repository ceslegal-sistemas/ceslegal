<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Empresa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Personal')
                    ->description('Datos básicos del usuario')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Juan Pérez García')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, ?string $old, Get $get) {
                                // Auto-generar email si está vacío
                                if (empty($get('email')) && !empty($state)) {
                                    $emailBase = Str::slug(Str::lower($state));
                                    $set('email', $emailBase . '@ceslegal.co');
                                }
                            })
                            ->helperText('Ingrese el nombre completo del usuario'),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('usuario@ceslegal.com')
                            ->helperText('Se generó automáticamente, puede modificarlo si lo desea')
                            ->suffixIcon('heroicon-o-envelope'),
                    ])->columns(2),

                Forms\Components\Section::make('Rol y Permisos')
                    ->description('Seleccione el rol y permisos del usuario')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Rol del Usuario')
                            ->options(fn() => static::roleOptions())
                            ->required()
                            ->default('abogado')
                            ->live()
                            ->native(false)
                            ->helperText('Seleccione el rol principal del usuario')
                            ->suffixIcon('heroicon-o-user-circle'),

                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa Asignada')
                            ->relationship(
                                name: 'empresa',
                                titleAttribute: 'razon_social',
                                modifyQueryUsing: fn (Builder $query, ?\Illuminate\Database\Eloquent\Model $record) => $query->paraAsignar($record?->empresa_id),
                            )
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => in_array($get('role'), ['cliente']))
                            // Solo el cliente pertenece a una empresa concreta.
                            ->hidden(fn(Get $get) => $get('role') !== 'cliente')
                            ->helperText('Seleccione la empresa a la que pertenece el usuario')
                            ->placeholder('Seleccione una empresa...')
                            ->suffixIcon('heroicon-o-building-office')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('razon_social')
                                    ->label('Razón Social')
                                    ->required()
                                    ->placeholder('Ej: EMPRESA ABC S.A.S'),
                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required()
                                    ->placeholder('Ej: 900123456-7'),
                            ])
                            ->createOptionModalHeading('Crear Nueva Empresa'),

                        // Bufete = firma de abogados externa que gestiona los procesos
                        // disciplinarios de VARIAS empresas clientes. Este usuario es un
                        // abogado de esa firma; aquí se indica a qué firma pertenece.
                        Forms\Components\Select::make('bufete_id')
                            ->label('Bufete (firma de abogados)')
                            ->relationship('bufete', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => $get('role') === 'bufete')
                            ->visible(fn(Get $get) => $get('role') === 'bufete')
                            ->helperText('Firma de abogados externa que atiende a varias empresas. Si no está en la lista, créela con el botón +.')
                            ->placeholder('Seleccione o cree un bufete...')
                            ->suffixIcon('heroicon-o-briefcase')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre del bufete')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: Abogados Asociados S.A.S')
                                    ->helperText('Nombre legal de la firma de abogados'),
                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required()
                                    ->unique(table: 'bufetes', column: 'nit')
                                    ->maxLength(50)
                                    ->mask('999999999-9')
                                    ->placeholder('Ej: 900123456-7')
                                    ->helperText('Incluya el dígito de verificación')
                                    ->rules(['regex:/^\d{6,12}-\d$/'])
                                    ->validationMessages([
                                        'regex'  => 'El NIT debe incluir el dígito de verificación (ej: 900123456-7).',
                                        'unique' => 'Ya existe un bufete con este NIT.',
                                    ]),
                                Forms\Components\TextInput::make('representante')
                                    ->label('Representante')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Juan Pérez García'),
                                Forms\Components\TextInput::make('email_contacto')
                                    ->label('Correo de contacto')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('contacto@bufete.com'),
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->mask('9999999999')
                                    ->maxLength(10)
                                    ->placeholder('3001234567'),
                            ])
                            ->createOptionModalHeading('Crear nuevo bufete'),

                        Forms\Components\Toggle::make('active')
                            ->label('Usuario Activo')
                            ->default(true)
                            ->helperText('Desactive si el usuario ya no debe tener acceso al sistema')
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Credenciales de Acceso')
                    ->description('Contraseña para acceder al sistema')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrateStateUsing(fn($state) => !empty($state) ? Hash::make($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->revealable()
                            ->placeholder('Mínimo 8 caracteres')
                            ->minLength(8)
                            ->helperText('Mínimo 8 caracteres. Deje vacío para mantener la contraseña actual'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->required(fn(string $context): bool => $context === 'create')
                            ->dehydrated(false)
                            ->revealable()
                            ->same('password')
                            ->placeholder('Repita la contraseña')
                            ->helperText('Debe coincidir con la contraseña anterior'),
                    ])->columns(2)
                    ->hiddenOn('view'),
            ]);
    }

    /** Etiqueta legible por rol (descripción larga para los roles conocidos). */
    protected static function descripcionesRoles(): array
    {
        return [
            'super_admin' => 'Administrador - Acceso total al sistema',
            'abogado'     => 'Abogado - Gestiona procesos disciplinarios y contratos',
            'cliente'     => 'Cliente - Visualiza procesos de su empresa y gestiona personal',
            'bufete'      => 'Bufete - Gestiona los procesos de varias empresas clientes',
        ];
    }

    /** Nombre corto por rol (para tabla y filtros). */
    public static function etiquetasRoles(): array
    {
        return [
            'super_admin' => 'Administrador',
            'abogado'     => 'Abogado',
            'cliente'     => 'Cliente',
            'bufete'      => 'Bufete',
        ];
    }

    /**
     * Opciones de rol para el formulario: se leen dinámicamente de los roles de
     * Shield/Spatie, así cualquier rol nuevo aparece sin tocar este código.
     */
    public static function roleOptions(): array
    {
        $desc = static::descripcionesRoles();

        return \Spatie\Permission\Models\Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn($name) => [$name => $desc[$name] ?? \Illuminate\Support\Str::headline($name)])
            ->toArray();
    }

    /** Opciones de rol (nombre corto) para tabla/filtros, también dinámicas. */
    public static function roleFilterOptions(): array
    {
        $cortas = static::etiquetasRoles();

        return \Spatie\Permission\Models\Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn($name) => [$name => $cortas[$name] ?? \Illuminate\Support\Str::headline($name)])
            ->toArray();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rol')
                    ->colors([
                        'danger' => 'super_admin',
                        'primary' => 'abogado',
                        'success' => 'cliente',
                        'warning' => 'bufete',
                    ])
                    ->icons([
                        'heroicon-o-shield-check' => 'super_admin',
                        'heroicon-o-scale' => 'abogado',
                        'heroicon-o-building-office' => 'cliente',
                        'heroicon-o-briefcase' => 'bufete',
                    ])
                    ->formatStateUsing(fn(string $state): string => static::etiquetasRoles()[$state] ?? \Illuminate\Support\Str::headline($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->default('Todas las empresas')
                    ->icon('heroicon-o-building-office'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options(fn() => static::roleFilterOptions())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social', modifyQueryUsing: fn (Builder $query) => $query->paraAsignar())
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos los usuarios')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                Tables\Actions\Action::make('reenviar_enlace_contrasena')
                    ->label('Reenviar enlace')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn(User $record) => $record->role === 'cliente')
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar enlace para configurar contraseña')
                    ->modalDescription(fn(User $record) => "Se enviará un correo nuevo a {$record->email} con un enlace para configurar su contraseña. El enlace anterior (si lo había) dejará de funcionar.")
                    ->modalSubmitActionLabel('Enviar')
                    ->action(function (User $record) {
                        \App\Notifications\ConfigurarContrasenaNotification::enviarA(
                            $record,
                            $record->empresa?->razon_social ?? config('app.name'),
                        );

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Enlace reenviado')
                            ->body("Se envió un correo nuevo a {$record->email}.")
                            ->send();
                    }),

                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->action(function (\App\Models\User $record, Tables\Actions\DeleteAction $action) {
                        try {
                            $record->delete();
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Por integridad del expediente legal, documentos.generado_por (y
                            // similares) usan onDelete('restrict') a propósito - no se pierde el
                            // rastro de quién generó un documento aunque ese usuario se retire.
                            // La vía correcta para "quitarle acceso" es desactivarlo (ya existe el
                            // toggle 'active' en el formulario), no borrarlo. Antes esto mostraba
                            // la excepción SQL cruda al administrador.
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('No se puede eliminar este usuario')
                                ->body('Este usuario generó documentos u otros registros que dependen de él - por integridad del expediente legal, no se puede eliminar. Edítelo y desmarque "Activo" para quitarle el acceso sin perder ese historial.')
                                ->persistent()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->action(function (\Illuminate\Support\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $bloqueados = [];
                            foreach ($records as $record) {
                                try {
                                    $record->delete();
                                } catch (\Illuminate\Database\QueryException $e) {
                                    $bloqueados[] = $record->name;
                                }
                            }

                            if (!empty($bloqueados)) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Algunos usuarios no se pudieron eliminar')
                                    ->body('Tienen documentos u otros registros que dependen de ellos: ' . implode(', ', $bloqueados) . '. Desactívelos en su lugar (edite cada uno y desmarque "Activo").')
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
