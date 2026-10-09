<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\ProcesoDisciplinarioResource;
use App\Models\AceptacionReglamentoInterno;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\EventoEquivalenteFuncional;
use App\Services\EquivalenteFuncionalService;
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

    public function getConteos(): array
    {
        return app(EquivalenteFuncionalService::class)->conteos();
    }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Admin\Widgets\EquivalenteFuncionalStats::class];
    }

    public function table(Table $table): Table
    {
        return match ($this->seccion) {
            'rit' => $this->tablaAutorizacionRit($table),
            'descargos' => $this->tablaDescargos($table),
            'trabajadores' => $this->tablaAceptacionTrabajadores($table),
            'sanciones' => $this->tablaSanciones($table),
            'todos' => $this->tablaTrazabilidad($table),
            'videos' => $this->tablaVideosHistoricos($table),
            default => $this->tablaTrazabilidad($table),
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

    /**
     * Trazabilidad completa: cada aceptación o autorización, de cualquier proceso,
     * con su evidencia. Tabla nativa de Filament sobre la consulta unificada de
     * EquivalenteFuncionalService (buscar, filtrar, agrupar y paginar van en SQL).
     */
    private function tablaTrazabilidad(Table $table): Table
    {
        $servicio = app(EquivalenteFuncionalService::class);
        $color = fn ($state) => match (true) {
            str_starts_with((string) $state, 'sancion_') => 'danger',
            str_starts_with((string) $state, 'descargos_') => 'warning',
            default => 'primary',
        };

        return $table
            ->query(fn () => $servicio->consulta())
            ->defaultSort('fecha', 'desc')
            ->groups([
                Tables\Grouping\Group::make('proceso_clave')->label('Proceso')->collapsible(),
                Tables\Grouping\Group::make('actor_nombre')->label('Persona')->collapsible(),
                Tables\Grouping\Group::make('tipo')->label('Tipo de evento')->collapsible()
                    ->getTitleFromRecordUsing(fn (EventoEquivalenteFuncional $r) => EquivalenteFuncionalService::etiquetaTipo($r->tipo)),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Evento')
                    ->badge()
                    ->color($color)
                    ->formatStateUsing(fn ($state) => EquivalenteFuncionalService::etiquetaTipo($state))
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proceso_clave')
                    ->label('Proceso')
                    ->description(fn (EventoEquivalenteFuncional $r) => $r->proceso_extra)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('actor_nombre')
                    ->label('Quién')
                    ->description(fn (EventoEquivalenteFuncional $r) => $r->actor_cargo)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sujeto_nombre')
                    ->label('Trabajador')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sujeto_documento')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('empresa_nombre')
                    ->label('Empresa')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('selfie')
                    ->label('Selfie')
                    ->getStateUsing(fn (EventoEquivalenteFuncional $r) => $r->tiene_selfie
                        ? route('equivalente-funcional.selfie', ['tipo' => $r->tipo, 'id' => $r->id_origen])
                        : null)
                    ->square()
                    ->size(44)
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->url(fn (EventoEquivalenteFuncional $r) => $r->tiene_selfie
                        ? route('equivalente-funcional.selfie', ['tipo' => $r->tipo, 'id' => $r->id_origen])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('ip')
                    ->label('Dirección IP')
                    ->fontFamily('mono')
                    ->placeholder('Sin registro')
                    ->searchable()
                    ->copyable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de evento')
                    ->options($servicio->tipos())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('empresa_nombre')
                    ->label('Empresa')
                    ->visible(fn () => auth()->user()?->role !== 'cliente')
                    ->options(fn () => \App\Models\Empresa::query()->orderBy('razon_social')->pluck('razon_social', 'razon_social')->all()),

                Tables\Filters\TernaryFilter::make('tiene_selfie')
                    ->label('Selfie de verificación')
                    ->placeholder('Todos')
                    ->trueLabel('Con selfie')
                    ->falseLabel('Sin selfie')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('tiene_selfie', 1),
                        false: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('tiene_selfie', 0),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $q) => $q,
                    ),

                Tables\Filters\Filter::make('fecha')
                    ->label('Fecha')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('desde')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $q, array $data) => $q
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->where('fecha', '>=', \Illuminate\Support\Carbon::parse($d)->startOfDay()->toDateTimeString()))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->where('fecha', '<=', \Illuminate\Support\Carbon::parse($d)->endOfDay()->toDateTimeString())))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];
                        if ($data['desde'] ?? null) {
                            $indicadores[] = 'Desde ' . \Illuminate\Support\Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicadores[] = 'Hasta ' . \Illuminate\Support\Carbon::parse($data['hasta'])->format('d/m/Y');
                        }

                        return $indicadores;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detalle')
                    ->label('Ver detalle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (EventoEquivalenteFuncional $r) => EquivalenteFuncionalService::etiquetaTipo($r->tipo))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (EventoEquivalenteFuncional $r) => view(
                        'filament.admin.pages.partials.equivalente-funcional-detalle',
                        [
                            'evento' => $r,
                            'detalle' => app(EquivalenteFuncionalService::class)->detalleDe($r),
                            'selfie' => $r->tiene_selfie
                                ? route('equivalente-funcional.selfie', ['tipo' => $r->tipo, 'id' => $r->id_origen])
                                : null,
                        ]
                    )),

                Tables\Actions\Action::make('ver_proceso')
                    ->label('Ver proceso')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (EventoEquivalenteFuncional $r) => $this->urlProceso($r) !== null)
                    ->url(fn (EventoEquivalenteFuncional $r) => $this->urlProceso($r))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('acta')
                    ->label('Acta PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (EventoEquivalenteFuncional $r) => $r->tipo === 'rit_aceptacion' && filled($r->d3))
                    ->url(fn (EventoEquivalenteFuncional $r) => route('trabajador.acta-rit.descargar', [
                        'trabajador' => $r->ref_trabajador,
                        'aceptacion' => $r->id_origen,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin aceptaciones ni autorizaciones')
            ->emptyStateDescription('Aún no hay evidencia registrada con estos filtros.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    private function urlProceso(EventoEquivalenteFuncional $r): ?string
    {
        return match (true) {
            str_starts_with($r->tipo, 'sancion_'), str_starts_with($r->tipo, 'descargos_') => $r->ref_proceso
                ? ProcesoDisciplinarioResource::getUrl('view', ['record' => $r->ref_proceso])
                : null,
            $r->tipo === 'rit_auditoria' => \App\Filament\Admin\Pages\AuditarRIT::getUrl(),
            str_starts_with($r->tipo, 'rit_') => MiReglamentoInterno::getUrl(),
            default => null,
        };
    }
}
