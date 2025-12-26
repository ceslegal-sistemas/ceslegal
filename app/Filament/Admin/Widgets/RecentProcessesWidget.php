<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ProcesoDisciplinario;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentProcessesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Auth::user();

        $query = ProcesoDisciplinario::query()
            ->whereNotIn('estado', ['cerrado', 'archivado'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        // Filtrar por empresa si no es admin
        if ($user->role !== 'admin') {
            $query->where('empresa_id', $user->empresa_id);
        }

        // Si es abogado, filtrar solo sus casos asignados
        if ($user->role === 'abogado') {
            $query->where('abogado_id', $user->id);
        }

        return $table
            ->query($query)
            ->heading('Procesos Disciplinarios Recientes')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable()
                    ->description(fn (ProcesoDisciplinario $record): string =>
                        $record->trabajador->cargo ?? ''
                    ),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: $user->role !== 'admin'),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'gray' => 'apertura',
                        'warning' => ['traslado', 'descargos_pendientes'],
                        'info' => ['descargos_realizados', 'analisis_juridico'],
                        'primary' => ['pendiente_gerencia', 'sancion_definida'],
                        'success' => ['notificado'],
                        'danger' => ['impugnado'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'apertura' => 'Apertura',
                        'traslado' => 'Traslado',
                        'descargos_pendientes' => 'Descargos Pendientes',
                        'descargos_realizados' => 'Descargos Realizados',
                        'analisis_juridico' => 'Análisis Jurídico',
                        'pendiente_gerencia' => 'Pendiente Gerencia',
                        'sancion_definida' => 'Sanción Definida',
                        'notificado' => 'Notificado',
                        'impugnado' => 'Impugnado',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('abogado.name')
                    ->label('Abogado')
                    ->toggleable(isToggledHiddenByDefault: $user->role === 'abogado'),

                Tables\Columns\TextColumn::make('fecha_ocurrencia')
                    ->label('Fecha Hechos')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->url(fn (ProcesoDisciplinario $record): string =>
                        route('filament.admin.resources.proceso-disciplinarios.edit', $record)
                    ),
            ]);
    }
}
