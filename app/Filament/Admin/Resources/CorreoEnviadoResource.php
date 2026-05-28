<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CorreoEnviadoResource\Pages;
use App\Models\CorreoEnviado;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CorreoEnviadoResource extends Resource
{
    protected static ?string $model            = CorreoEnviado::class;
    protected static ?string $navigationIcon   = 'heroicon-o-envelope';
    protected static ?string $navigationLabel  = 'Correos Enviados';
    protected static ?string $modelLabel       = 'Correo';
    protected static ?string $pluralModelLabel = 'Correos Enviados';
    protected static ?string $navigationGroup  = 'Comunicaciones';
    protected static ?int    $navigationSort   = 90;

    // public static function canAccess(): bool
    // {
    //     $user = Auth::user();
    //     // return $user && ($user->hasRole('super_admin') || $user->hasRole('abogado'));
    // }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Destinatario')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\Select::make('trabajador_id')
                        ->label('Seleccionar trabajador (opcional)')
                        ->options(
                            Trabajador::query()
                                ->where('active', true)
                                ->get()
                                ->mapWithKeys(fn ($t) => [
                                    $t->id => "{$t->nombres} {$t->apellidos} — {$t->email}",
                                ])
                        )
                        ->searchable()
                        ->nullable()
                        ->placeholder('Buscar trabajador...')
                        ->live()
                        ->afterStateUpdated(function (?int $state, Set $set) {
                            if ($state) {
                                $trabajador = Trabajador::find($state);
                                if ($trabajador) {
                                    $set('email_destinatario', $trabajador->email);
                                    $set('destinatario_nombre', $trabajador->nombres . ' ' . $trabajador->apellidos);
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('destinatario_nombre')
                        ->label('Nombre del destinatario')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email_destinatario')
                        ->label('Email destinatario')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TagsInput::make('email_cc')
                        ->label('Con copia (CC)')
                        ->placeholder('correo@ejemplo.com')
                        ->helperText('Presione Enter o coma para agregar cada email')
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Correo')
                ->icon('heroicon-o-envelope-open')
                ->schema([
                    Forms\Components\Select::make('proceso_id')
                        ->label('Proceso disciplinario (opcional)')
                        ->options(
                            ProcesoDisciplinario::query()
                                ->select('id', 'codigo')
                                ->orderByDesc('created_at')
                                ->limit(300)
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => $p->codigo])
                        )
                        ->searchable()
                        ->nullable()
                        ->placeholder('Vincular a un proceso...'),

                    Forms\Components\Select::make('empresa_id')
                        ->label('Empresa remitente (Gmail)')
                        ->relationship('empresa', 'razon_social')
                        ->searchable()
                        ->nullable()
                        ->placeholder('Sistema (SMTP por defecto)')
                        ->helperText('Si la empresa tiene Gmail conectado, el correo saldrá desde ese Gmail.'),

                    Forms\Components\Select::make('prioridad')
                        ->label('Prioridad')
                        ->options([
                            'normal'  => 'Normal',
                            'alta'    => 'Alta',
                            'urgente' => 'Urgente',
                        ])
                        ->default('normal')
                        ->required(),

                    Forms\Components\TextInput::make('asunto')
                        ->label('Asunto')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('cuerpo')
                        ->label('Cuerpo del correo')
                        ->required()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'bulletList', 'orderedList',
                            'h2', 'h3',
                            'link',
                            'blockquote',
                            'undo', 'redo',
                        ])
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Adjuntos')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    Forms\Components\FileUpload::make('adjuntos')
                        ->label('Archivos adjuntos')
                        ->multiple()
                        ->disk('local')
                        ->directory(fn () => 'correos/' . now()->format('Y') . '/' . now()->format('m'))
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                        ])
                        ->maxSize(10240)
                        ->nullable(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('destinatario_nombre')
                    ->label('Destinatario')
                    ->description(fn (CorreoEnviado $r) => $r->email_destinatario)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asunto')
                    ->label('Asunto')
                    ->limit(55)
                    ->searchable(),

                Tables\Columns\TextColumn::make('proceso.codigo')
                    ->label('Proceso')
                    ->badge()
                    ->color('indigo')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('prioridad')
                    ->label('Prioridad')
                    ->colors([
                        'danger'  => 'urgente',
                        'warning' => 'alta',
                        'secondary' => 'normal',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'urgente' => 'Urgente',
                        'alta'    => 'Alta',
                        default   => 'Normal',
                    }),

                Tables\Columns\TextColumn::make('enviado_en')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Pendiente de envío'),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->formatStateUsing(fn (CorreoEnviado $r) => $r->getLabelEstado())
                    ->colors([
                        'success'   => fn (CorreoEnviado $r) => $r->estado === 'leido',
                        'warning'   => fn (CorreoEnviado $r) => $r->estado === 'entregado',
                        'secondary' => fn (CorreoEnviado $r) => $r->estado === 'pendiente',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'entregado' => 'Entregado',
                        'leido'     => 'Leído',
                    ]),

                Tables\Filters\SelectFilter::make('prioridad')
                    ->label('Prioridad')
                    ->options([
                        'normal'  => 'Normal',
                        'alta'    => 'Alta',
                        'urgente' => 'Urgente',
                    ]),

                Tables\Filters\Filter::make('rango_fechas')
                    ->label('Rango de fechas')
                    ->form([
                        Forms\Components\DatePicker::make('desde')->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn ($q) => $q->whereDate('created_at', '>=', $data['desde']))
                            ->when($data['hasta'], fn ($q) => $q->whereDate('created_at', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver acuse'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCorreosEnviados::route('/'),
            'create' => Pages\CreateCorreoEnviado::route('/create'),
            'view'   => Pages\ViewCorreoEnviado::route('/{record}'),
        ];
    }
}
