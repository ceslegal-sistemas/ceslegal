<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\AceptacionReglamentoInterno;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\HistoricoVideoDidacticoRit;
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
 * - Aceptación de trabajadores: la evidencia de cada trabajador que aceptó el
 *   RIT en la socialización (IP, dispositivo, selfie de verificación, resultado
 *   del quiz, huella del texto y acta PDF) - AceptacionReglamentoInterno.
 * - Videos del Reglamento: histórico append-only de videos didácticos
 *   reemplazados (HistoricoVideoDidacticoRit, item 3 de la misma reunión).
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

    /** @var 'todos'|'sanciones'|'rit'|'descargos'|'trabajadores'|'videos' */
    public string $seccion = 'todos';

    // Filtros de la trazabilidad completa (sección 'todos').
    public string $tipoEvento = 'todos';
    public string $buscar = '';
    public ?string $desde = null;
    public ?string $hasta = null;
    public bool $soloSelfie = false;
    public int $pagina = 1;

    private const POR_PAGINA = 15;

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

    /** Un solo filtro cambia: se vuelve a la primera página. */
    public function updatedTipoEvento(): void { $this->pagina = 1; }
    public function updatedBuscar(): void { $this->pagina = 1; }
    public function updatedDesde(): void { $this->pagina = 1; }
    public function updatedHasta(): void { $this->pagina = 1; }
    public function updatedSoloSelfie(): void { $this->pagina = 1; }

    public function irAPagina(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['tipoEvento', 'buscar', 'desde', 'hasta', 'soloSelfie', 'pagina']);
    }

    /** Ver todos los eventos de un mismo proceso (p. ej. "Proceso DES-001" o "RIT #3"). */
    public function filtrarPorProceso(string $clave): void
    {
        $this->reset(['tipoEvento', 'desde', 'hasta', 'soloSelfie']);
        $this->buscar = $clave;
        $this->pagina = 1;
    }

    /** @return array{items: \Illuminate\Support\Collection, total: int, pagina: int, paginas: int, tipos: array<string, string>} */
    public function eventos(): array
    {
        $servicio = app(\App\Services\EquivalenteFuncionalService::class);

        $resultado = $servicio->consultar([
            'tipo' => $this->tipoEvento,
            'buscar' => $this->buscar,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'solo_selfie' => $this->soloSelfie,
        ], $this->pagina, self::POR_PAGINA);

        $paginas = max(1, (int) ceil($resultado['total'] / self::POR_PAGINA));

        return $resultado + [
            'pagina' => min($this->pagina, $paginas),
            'paginas' => $paginas,
            'tipos' => $servicio->tipos(),
        ];
    }

    public function table(Table $table): Table
    {
        return match ($this->seccion) {
            'rit' => $this->tablaAutorizacionRit($table),
            'descargos' => $this->tablaDescargos($table),
            'trabajadores' => $this->tablaAceptacionTrabajadores($table),
            'videos' => $this->tablaVideosHistoricos($table),
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

    private function tablaVideosHistoricos(Table $table): Table
    {
        $query = HistoricoVideoDidacticoRit::query()->with('empresa');
        $this->scopearPorEmpresaDelCliente($query);

        return $table
            ->query($query)
            ->defaultSort('archivado_en', 'desc')
            ->description('Videos didácticos que fueron reemplazados por una regeneración posterior. El video vigente se ve en "Mi Reglamento Interno".')
            ->columns([
                Tables\Columns\TextColumn::make('empresa.razon_social')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reglamento_interno_id')
                    ->label('RIT')
                    ->formatStateUsing(fn($state) => "RIT #{$state}"),

                Tables\Columns\TextColumn::make('capitulos')
                    ->label('Capítulos')
                    ->getStateUsing(fn(HistoricoVideoDidacticoRit $record) => count($record->capitulos ?? [])),

                Tables\Columns\TextColumn::make('generado_en')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('archivado_en')
                    ->label('Reemplazado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('texto_rit_hash')
                    ->label('Huella del texto del RIT')
                    ->limit(12)
                    ->tooltip(fn(HistoricoVideoDidacticoRit $record) => $record->texto_rit_hash)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_video')
                    ->label('Ver video')
                    ->icon('heroicon-o-play-circle')
                    ->color('gray')
                    ->modalHeading('Video didáctico reemplazado')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn(HistoricoVideoDidacticoRit $record) => view(
                        'filament.admin.pages.partials.videos-rit-historico',
                        ['historico' => $record]
                    )),
            ])
            ->emptyStateHeading('Sin videos históricos')
            ->emptyStateDescription('Aún no se ha reemplazado ningún video didáctico del Reglamento.')
            ->emptyStateIcon('heroicon-o-play-circle');
    }

    private function tablaAceptacionTrabajadores(Table $table): Table
    {
        // whereHas('trabajador') aplica el scope de bufete/empresa de Trabajador
        // (AceptacionReglamentoInterno no tiene scope propio): cada usuario ve
        // solo las aceptaciones de los trabajadores a los que tiene acceso.
        $query = AceptacionReglamentoInterno::query()
            ->whereHas('trabajador')
            ->with(['trabajador.empresa', 'reglamentoInterno']);

        return $table
            ->query($query)
            ->defaultSort('aceptado_en', 'desc')
            ->groups([
                Tables\Grouping\Group::make('trabajador_id')
                    ->label('Trabajador')
                    ->getTitleFromRecordUsing(fn(AceptacionReglamentoInterno $record) => $record->trabajador?->nombre_completo ?? 'Trabajador'),
                Tables\Grouping\Group::make('reglamento_interno_id')
                    ->label('Versión del Reglamento')
                    ->getTitleFromRecordUsing(fn(AceptacionReglamentoInterno $record) => 'RIT #' . $record->reglamento_interno_id . ($record->reglamentoInterno?->version ? ' (versión ' . $record->reglamentoInterno->version . ')' : '')),
            ])
            ->defaultGroup('trabajador_id')
            ->columns([
                Tables\Columns\TextColumn::make('trabajador.nombre_completo')
                    ->label('Trabajador')
                    ->weight('bold')
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
                        $query->whereHas('trabajador', fn($q) => $q
                            ->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('numero_documento', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('trabajador.numero_documento')
                    ->label('Documento')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('trabajador.empresa.razon_social')
                    ->label('Empresa')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reglamento_interno_id')
                    ->label('RIT')
                    ->formatStateUsing(fn($state, AceptacionReglamentoInterno $record) => 'RIT #' . $state . ($record->reglamentoInterno?->version ? ' (v' . $record->reglamentoInterno->version . ')' : '')),

                Tables\Columns\TextColumn::make('aceptado_en')
                    ->label('Fecha de aceptación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_aceptacion')
                    ->label('Dirección IP')
                    ->searchable()
                    ->placeholder('Sin registro')
                    ->copyable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Dispositivo y navegador')
                    ->limit(40)
                    ->tooltip(fn(AceptacionReglamentoInterno $record) => $record->user_agent)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('foto_aceptacion_path')
                    ->label('Selfie')
                    ->boolean()
                    ->getStateUsing(fn(AceptacionReglamentoInterno $record) => !empty($record->foto_aceptacion_path)),

                Tables\Columns\TextColumn::make('quiz_resultado')
                    ->label('Quiz de comprensión')
                    ->getStateUsing(function (AceptacionReglamentoInterno $record) {
                        $preguntas = $record->quiz_resultado ?? [];
                        if (empty($preguntas)) {
                            return 'Sin quiz';
                        }
                        $aciertos = 0;
                        $intentos = 0;
                        foreach ($preguntas as $pregunta) {
                            $lista = $pregunta['intentos'] ?? [];
                            $intentos += count($lista);
                            if (!empty($lista) && !empty(end($lista)['correcta'])) {
                                $aciertos++;
                            }
                        }

                        return $aciertos . '/' . count($preguntas) . ' correctas, ' . $intentos . ' intentos';
                    }),

                Tables\Columns\TextColumn::make('texto_rit_hash')
                    ->label('Huella del texto aceptado')
                    ->limit(12)
                    ->tooltip(fn(AceptacionReglamentoInterno $record) => $record->texto_rit_hash)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_selfie')
                    ->label('Ver selfie')
                    ->icon('heroicon-o-camera')
                    ->color('gray')
                    ->visible(fn(AceptacionReglamentoInterno $record) => !empty($record->foto_aceptacion_path))
                    ->modalHeading(fn(AceptacionReglamentoInterno $record) => 'Selfie de verificación - ' . ($record->trabajador?->nombre_completo ?? ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn(AceptacionReglamentoInterno $record) => view(
                        'filament.admin.pages.partials.selfie-aceptacion-rit',
                        ['aceptacion' => $record]
                    )),

                Tables\Actions\Action::make('acta')
                    ->label('Acta PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn(AceptacionReglamentoInterno $record) => !empty($record->ruta_acta))
                    ->url(fn(AceptacionReglamentoInterno $record) => route('trabajador.acta-rit.descargar', [
                        'trabajador' => $record->trabajador_id,
                        'aceptacion' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin aceptaciones de trabajadores')
            ->emptyStateDescription('Aún ningún trabajador ha aceptado el Reglamento Interno en la socialización.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
