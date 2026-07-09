<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\ProcesoDisciplinario;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Reporte "Sanciones Emitidas": historial sancionatorio, agrupable por trabajador.
 * Muestra solo procesos con una sanción real aplicada (excluye "no aplica sanción").
 * El alcance por empresa lo dan el rol y el scope global (cliente ve su empresa).
 */
class SancionesEmitidas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Sanciones Emitidas';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Sanciones Emitidas';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.sanciones-emitidas';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin', 'abogado', 'cliente', 'bufete'], true);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = ProcesoDisciplinario::query()
            ->whereNotNull('tipo_sancion')
            ->where('tipo_sancion', '!=', 'no_sancion')
            ->with(['trabajador', 'empresa']);

        // El cliente solo ve su empresa (igual que en el historial de descargos).
        if ($user?->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }

        return $table
            ->query($query)
            ->defaultSort('updated_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('trabajador.nombre_completo')
                    ->label('Trabajador'),
                Tables\Grouping\Group::make('empresa.razon_social')
                    ->label('Empresa'),
            ])
            ->defaultGroup('trabajador.nombre_completo')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('trabajador.numero_documento')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tipo_sancion')
                    ->label('Sanción')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'llamado_atencion' => 'Llamado de Atención',
                        'suspension'       => 'Suspensión Laboral',
                        'multa'            => 'Multa',
                        'terminacion'      => 'Terminación de Contrato',
                        default            => $state ?? '—',
                    })
                    ->color(fn($state) => match ($state) {
                        'llamado_atencion' => 'info',
                        'suspension'       => 'warning',
                        'multa'            => 'gray',
                        'terminacion'      => 'danger',
                        default            => 'gray',
                    }),

                Tables\Columns\TextColumn::make('dias_suspension')
                    ->label('Días')
                    ->formatStateUsing(fn($state) => $state ? $state . ' día' . ($state > 1 ? 's' : '') : '—')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Fecha de sanción')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_sancion')
                    ->label('Tipo de sanción')
                    ->options([
                        'llamado_atencion' => 'Llamado de Atención',
                        'suspension'       => 'Suspensión Laboral',
                        'multa'            => 'Multa',
                        'terminacion'      => 'Terminación de Contrato',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver proceso')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn(ProcesoDisciplinario $record) => ProcesoDisciplinarioResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin sanciones emitidas')
            ->emptyStateDescription('Aún no hay procesos con una sanción aplicada.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
