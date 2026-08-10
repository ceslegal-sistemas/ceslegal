<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SolicitudCambioEmpresaResource\Pages;
use App\Models\SolicitudCambioEmpresa;
use App\Services\NotificacionService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SolicitudCambioEmpresaResource extends Resource
{
    protected static ?string $model = SolicitudCambioEmpresa::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Solicitudes de cambio de empresa';
    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 50;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('estado', 'pendiente')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('solicitante.name')
                    ->label('Solicitado por'),

                Tables\Columns\TextColumn::make('mensaje')
                    ->label('Mensaje')
                    ->limit(60)
                    ->tooltip(fn(SolicitudCambioEmpresa $record) => $record->mensaje),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pendiente' => 'warning',
                        'aprobada' => 'success',
                        'rechazada' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(SolicitudCambioEmpresa $record) => $record->estado === 'pendiente')
                    ->fillForm(fn(SolicitudCambioEmpresa $record) => [
                        'razon_social' => $record->empresa->razon_social,
                        'tipo_societario' => $record->empresa->tipo_societario,
                        'nit' => $record->empresa->nit,
                        'representante_legal' => $record->empresa->representante_legal,
                    ])
                    ->form([
                        Forms\Components\Placeholder::make('mensaje_cliente')
                            ->label('Mensaje del cliente')
                            ->content(fn(SolicitudCambioEmpresa $record) => $record->mensaje),

                        Forms\Components\TextInput::make('razon_social')->label('Razón Social')->required(),
                        Forms\Components\Select::make('tipo_societario')
                            ->label('Tipo Societario')
                            ->options(\App\Models\Empresa::TIPOS_SOCIETARIOS),
                        Forms\Components\TextInput::make('nit')->label('NIT')->required(),
                        Forms\Components\TextInput::make('representante_legal')->label('Representante Legal')->required(),
                    ])
                    ->modalHeading('Aprobar solicitud de cambio')
                    ->modalSubmitActionLabel('Guardar y aprobar')
                    ->action(function (SolicitudCambioEmpresa $record, array $data): void {
                        $record->empresa->update([
                            'razon_social' => $data['razon_social'],
                            'tipo_societario' => $data['tipo_societario'],
                            'nit' => $data['nit'],
                            'representante_legal' => $data['representante_legal'],
                        ]);

                        $record->update([
                            'estado' => 'aprobada',
                            'resuelto_por' => auth()->id(),
                            'resuelto_en' => now(),
                        ]);

                        app(NotificacionService::class)->notificarResultadoSolicitudCambioEmpresa($record->fresh());

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Solicitud aprobada')
                            ->body('Los datos de la empresa fueron actualizados.')
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(SolicitudCambioEmpresa $record) => $record->estado === 'pendiente')
                    ->form([
                        Forms\Components\Textarea::make('motivo_rechazo')
                            ->label('Motivo del rechazo')
                            ->required()
                            ->rows(3),
                    ])
                    ->modalHeading('Rechazar solicitud de cambio')
                    ->requiresConfirmation()
                    ->action(function (SolicitudCambioEmpresa $record, array $data): void {
                        $record->update([
                            'estado' => 'rechazada',
                            'motivo_rechazo' => $data['motivo_rechazo'],
                            'resuelto_por' => auth()->id(),
                            'resuelto_en' => now(),
                        ]);

                        app(NotificacionService::class)->notificarResultadoSolicitudCambioEmpresa($record->fresh());

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Solicitud rechazada')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSolicitudesCambioEmpresa::route('/'),
        ];
    }
}
