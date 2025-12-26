<?php

namespace App\Filament\Admin\Widgets;

use App\Models\TerminoLegal;
use App\Services\TerminoLegalService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Carbon\Carbon;

class ExpiringTermsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $terminoService = app(TerminoLegalService::class);

        return $table
            ->query(
                TerminoLegal::query()
                    ->where('estado', 'activo')
                    ->orderBy('fecha_vencimiento', 'asc')
            )
            ->heading('Términos Legales Activos')
            ->columns([
                Tables\Columns\TextColumn::make('proceso_id')
                    ->label('Proceso')
                    ->formatStateUsing(function (TerminoLegal $record): string {
                        if ($record->proceso_tipo === 'proceso_disciplinario') {
                            $proceso = \App\Models\ProcesoDisciplinario::find($record->proceso_id);
                            return $proceso ? $proceso->codigo : "PD-{$record->proceso_id}";
                        } else {
                            $solicitud = \App\Models\SolicitudContrato::find($record->proceso_id);
                            return $solicitud ? $solicitud->codigo : "SC-{$record->proceso_id}";
                        }
                    })
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('proceso_tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'proceso_disciplinario' => 'Proceso Disciplinario',
                        'contrato' => 'Contrato',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'proceso_disciplinario' => 'danger',
                        'contrato' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('termino_tipo')
                    ->label('Término')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'traslado_descargos' => 'Traslado Descargos',
                        'impugnacion' => 'Impugnación',
                        'analisis_juridico' => 'Análisis Jurídico',
                        'respuesta_gerencia' => 'Respuesta Gerencia',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Fecha Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Fecha Vencimiento')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->color(function (TerminoLegal $record) use ($terminoService): string {
                        $diasRestantes = $terminoService->calcularDiasHabilesRestantes(
                            Carbon::parse($record->fecha_vencimiento)
                        );

                        if ($diasRestantes <= 0) return 'danger';
                        if ($diasRestantes <= 2) return 'warning';
                        return 'success';
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('dias_transcurridos')
                    ->label('Días Transcurridos')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('dias_restantes')
                    ->label('Días Restantes')
                    ->alignCenter()
                    ->state(function (TerminoLegal $record) use ($terminoService): int {
                        return $terminoService->calcularDiasHabilesRestantes(
                            Carbon::parse($record->fecha_vencimiento)
                        );
                    })
                    ->badge()
                    ->color(function ($state): string {
                        if ($state <= 0) return 'danger';
                        if ($state <= 2) return 'warning';
                        return 'success';
                    }),

                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->colors([
                        'primary' => 'activo',
                        'danger' => 'vencido',
                        'gray' => 'cerrado',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'activo' => 'Activo',
                        'vencido' => 'Vencido',
                        'cerrado' => 'Cerrado',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('proceso_tipo')
                    ->label('Tipo de Proceso')
                    ->options([
                        'proceso_disciplinario' => 'Proceso Disciplinario',
                        'contrato' => 'Contrato',
                    ]),

                Tables\Filters\SelectFilter::make('termino_tipo')
                    ->label('Tipo de Término')
                    ->options([
                        'traslado_descargos' => 'Traslado Descargos',
                        'impugnacion' => 'Impugnación',
                        'analisis_juridico' => 'Análisis Jurídico',
                        'respuesta_gerencia' => 'Respuesta Gerencia',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_proceso')
                    ->label('Ver Proceso')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (TerminoLegal $record): ?string {
                        if ($record->proceso_tipo === 'proceso_disciplinario') {
                            return route('filament.admin.resources.proceso-disciplinarios.edit', $record->proceso_id);
                        } else {
                            return route('filament.admin.resources.solicitud-contratos.edit', $record->proceso_id);
                        }
                    })
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('fecha_vencimiento', 'asc');
    }
}
