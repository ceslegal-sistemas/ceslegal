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
                            ->options([
                                'super_admin' => 'Administrador - Acceso total al sistema',
                                'abogado' => 'Abogado - Gestiona procesos disciplinarios y contratos',
                                'cliente' => 'Cliente - Visualiza procesos de su empresa y gestiona personal',
                            ])
                            ->required()
                            ->default('abogado')
                            ->live()
                            ->native(false)
                            ->helperText('Seleccione el rol principal del usuario')
                            ->suffixIcon('heroicon-o-user-circle'),

                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa Asignada')
                            ->relationship('empresa', 'razon_social')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => in_array($get('role'), ['abogado', 'cliente']))
                            ->hidden(fn (Get $get) => $get('role') === 'super_admin')
                            ->helperText(fn (Get $get) =>
                                $get('role') === 'super_admin'
                                    ? 'Los administradores tienen acceso a todas las empresas'
                                    : 'Seleccione la empresa a la que pertenece el usuario'
                            )
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
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->revealable()
                            ->placeholder('Mínimo 8 caracteres')
                            ->minLength(8)
                            ->helperText('Mínimo 8 caracteres. Deje vacío para mantener la contraseña actual'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(false)
                            ->revealable()
                            ->same('password')
                            ->placeholder('Repita la contraseña')
                            ->helperText('Debe coincidir con la contraseña anterior'),
                    ])->columns(2)
                    ->hiddenOn('view'),
            ]);
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
                    ])
                    ->icons([
                        'heroicon-o-shield-check' => 'super_admin',
                        'heroicon-o-scale' => 'abogado',
                        'heroicon-o-building-office' => 'cliente',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Administrador',
                        'abogado' => 'Abogado',
                        'cliente' => 'Cliente',
                        default => $state,
                    })
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
                    ->options([
                        'super_admin' => 'Administrador',
                        'abogado' => 'Abogado',
                        'cliente' => 'Cliente',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
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
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
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
