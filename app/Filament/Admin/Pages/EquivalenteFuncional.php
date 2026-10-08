<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\ProcesoDisciplinario;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Reporte "Equivalente Funcional" (pedido de Andrés Sarmiento, reunión
 * 2026-10-05, prioridad alta - "muy importante"): trazabilidad de cada
 * "equivalente funcional de firma" capturado por el sistema, para poder
 * demostrar (no solo decir) que el proceso correcto se siguió.
 *
 * Vive en Reportes (NO dentro de "Mi Reglamento Interno" - corrección
 * explícita de Andrés a Juan Pablo). 3 secciones, cada una buscable por el
 * nombre del funcionario que autorizó/citó:
 * - Sanciones/Incidentes: autorizador_* de ProcesoDisciplinario (Emitir Sanción).
 * - Autorización de RIT: AutorizacionReglamentoInterno (append-only, incluye
 *   todas las versiones/actualizaciones de cada RIT).
 * - Descargos: citante_* de ProcesoDisciplinario (citación inicial).
 *
 * Requisito explícito: si un mismo funcionario aprobó 3 RIT (original + 2
 * actualizaciones), debe ser fácil encontrar las 3 en un solo lugar - de
 * ahí que la columna autorizador_nombre/citante_nombre sea siempre
 * searchable y la tabla agrupe por ese campo.
 */
class EquivalenteFuncional extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Equivalente Funcional';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Equivalente Funcional';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.admin.pages.equivalente-funcional';

    /** @var 'sanciones'|'rit'|'descargos' */
    public string $seccion = 'sanciones';

    public static function shouldRegisterNavigation(): bool
    {
        // Mismo criterio que SancionesEmitidas.
        if (auth()->user()?->bufeteSinEmpresaActiva() || \App\Support\MenuRit::clienteSinRit()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_EquivalenteFuncional') ?? false;
    }

    public function cambiarSeccion(string $seccion): void
    {
        $this->seccion = $seccion;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return match ($this->seccion) {
            'rit' => $this->tablaAutorizacionRit($table),
            'descargos' => $this->tablaDescargos($table),
            default => $this->tablaSanciones($table),
        };
    }

    private function scopearPorEmpresaDelCliente(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $user = auth()->user();
        if ($user?->role === 'cliente' && $user->empresa_id) {
            $query->where('empresa_id', $user->empresa_id);
        }
    }

    private function tablaSanciones(Table $table): Table
    {
        $query = ProcesoDisciplinario::query()
            ->whereNotNull('autorizador_nombre')
            ->with(['trabajador', 'empresa']);
        $this->scopearPorEmpresaDelCliente($query);

        return $table
            ->query($query)
            ->defaultSort('updated_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('autorizador_nombre')->label('Funcionario que autorizó'),
            ])
            ->defaultGroup('autorizador_nombre')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('autorizador_nombre')
                    ->label('Funcionario que autorizó')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('autorizador_cargo')
                    ->label('Cargo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador sancionado')
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
                        $query->whereHas('trabajador', fn($q) => $q
                            ->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tipo_sancion')
                    ->label('Decisión')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'llamado_atencion' => 'Llamado de Atención',
                        'suspension'       => 'Suspensión Laboral',
                        'multa'            => 'Multa',
                        'terminacion'      => 'Terminación de Contrato',
                        'no_sancion'       => 'No aplica sanción',
                        default            => $state ?? '-',
                    }),

                Tables\Columns\IconColumn::make('foto_autorizador_path')
                    ->label('Foto')
                    ->boolean()
                    ->getStateUsing(fn($record) => !empty($record->foto_autorizador_path)),

                Tables\Columns\TextColumn::make('foto_autorizador_en')
                    ->label('Fecha de autorización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver proceso')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn(ProcesoDisciplinario $record) => ProcesoDisciplinarioResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin autorizaciones de sanciones')
            ->emptyStateDescription('Aún no hay procesos con una decisión autorizada.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    private function tablaAutorizacionRit(Table $table): Table
    {
        $query = AutorizacionReglamentoInterno::query()
            ->with(['reglamentoInterno', 'empresa']);
        $this->scopearPorEmpresaDelCliente($query);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('autorizador_nombre')->label('Funcionario que autorizó'),
            ])
            ->defaultGroup('autorizador_nombre')
            ->columns([
                Tables\Columns\TextColumn::make('autorizador_nombre')
                    ->label('Funcionario que autorizó')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('autorizador_cargo')
                    ->label('Cargo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reglamento_interno_id')
                    ->label('RIT')
                    ->formatStateUsing(fn($state) => "RIT #{$state}")
                    ->toggleable(),

                Tables\Columns\IconColumn::make('foto_autorizador_path')
                    ->label('Foto')
                    ->boolean()
                    ->getStateUsing(fn($record) => !empty($record->foto_autorizador_path)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de autorización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver Reglamento')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn(AutorizacionReglamentoInterno $record) => MiReglamentoInterno::getUrl())
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin autorizaciones de Reglamento Interno')
            ->emptyStateDescription('Aún ningún funcionario ha autorizado una versión del Reglamento Interno.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    private function tablaDescargos(Table $table): Table
    {
        $query = ProcesoDisciplinario::query()
            ->whereNotNull('citante_nombre')
            ->with(['trabajador', 'empresa']);
        $this->scopearPorEmpresaDelCliente($query);

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('citante_nombre')->label('Funcionario que citó'),
            ])
            ->defaultGroup('citante_nombre')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('citante_nombre')
                    ->label('Funcionario que citó')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('citante_cargo')
                    ->label('Cargo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador citado')
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
                        $query->whereHas('trabajador', fn($q) => $q
                            ->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado del proceso')
                    ->badge(),

                Tables\Columns\IconColumn::make('foto_citante_path')
                    ->label('Foto')
                    ->boolean()
                    ->getStateUsing(fn($record) => !empty($record->foto_citante_path)),

                Tables\Columns\TextColumn::make('foto_citante_en')
                    ->label('Fecha de citación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver proceso')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn(ProcesoDisciplinario $record) => ProcesoDisciplinarioResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin citaciones a descargos')
            ->emptyStateDescription('Aún no hay procesos con una citación registrada.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
