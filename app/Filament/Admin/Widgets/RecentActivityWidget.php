<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Timeline;
use App\Services\EstadoProcesoService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $estadoService = app(EstadoProcesoService::class);

        $query = Timeline::query()
            ->where('proceso_tipo', 'proceso_disciplinario')
            ->orderBy('created_at', 'desc')
            ->limit(15);

        // Filtrar por empresa si no es admin o super_admin
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            $query->whereIn('proceso_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('procesos_disciplinarios')
                    ->where('empresa_id', $user->empresa_id);
            });
        }

        // Si es abogado, filtrar solo sus casos asignados
        if ($user->role === 'abogado') {
            $query->whereIn('proceso_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('procesos_disciplinarios')
                    ->where('abogado_id', $user->id);
            });
        }

        return $table
            ->query($query)
            ->heading('Actividad Reciente del Sistema')
            ->columns([
                Tables\Columns\IconColumn::make('accion')
                    ->label('')
                    ->icon(fn (Timeline $record): string => match ($record->accion) {
                        'Creación' => 'heroicon-o-plus-circle',
                        'Cambio de estado' => 'heroicon-o-arrow-path',
                        'Documento generado' => 'heroicon-o-document',
                        'Notificación enviada' => 'heroicon-o-envelope',
                        'Actualización' => 'heroicon-o-pencil',
                        'Asignación de abogado' => 'heroicon-o-user-plus',
                        'Comentario agregado' => 'heroicon-o-chat-bubble-left',
                        'Proceso archivado' => 'heroicon-o-archive-box',
                        default => 'heroicon-o-information-circle',
                    })
                    ->color(fn (Timeline $record): string => match ($record->accion) {
                        'Creación' => 'success',
                        'Cambio de estado' => 'primary',
                        'Documento generado' => 'info',
                        'Notificación enviada' => 'warning',
                        'Actualización' => 'info',
                        'Asignación de abogado' => 'success',
                        'Comentario agregado' => 'gray',
                        'Proceso archivado' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('proceso_id')
                    ->label('Proceso')
                    ->formatStateUsing(function (Timeline $record): string {
                        $proceso = \App\Models\ProcesoDisciplinario::find($record->proceso_id);
                        return $proceso ? $proceso->codigo : "PD-{$record->proceso_id}";
                    })
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\BadgeColumn::make('accion')
                    ->label('Tipo')
                    ->colors([
                        'success' => ['Creación', 'Asignación de abogado'],
                        'primary' => 'Cambio de estado',
                        'info' => ['Documento generado', 'Actualización'],
                        'warning' => 'Notificación enviada',
                        'gray' => 'Comentario agregado',
                        'danger' => 'Proceso archivado',
                    ]),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(60)
                    ->tooltip(fn (Timeline $record): string => $record->descripcion)
                    ->wrap(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->toggleable()
                    ->default('Sistema'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since()
                    ->sortable()
                    ->dateTime('d/m/Y H:i')
                    ->tooltip(fn (Timeline $record): string => $record->created_at->format('d/m/Y H:i:s')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('accion')
                    ->label('Tipo de Actividad')
                    ->options([
                        'Creación' => 'Creación',
                        'Cambio de estado' => 'Cambio de Estado',
                        'Documento generado' => 'Documento Generado',
                        'Notificación enviada' => 'Notificación Enviada',
                        'Actualización' => 'Actualización',
                        'Asignación de abogado' => 'Asignación de Abogado',
                        'Comentario agregado' => 'Comentario',
                        'Proceso archivado' => 'Proceso Archivado',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_proceso')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Timeline $record): ?string =>
                        $record->proceso_id
                            ? route('filament.admin.resources.proceso-disciplinarios.edit', $record->proceso_id)
                            : null
                    )
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
