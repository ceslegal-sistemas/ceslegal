<?php

namespace App\Services;

use App\Models\AceptacionReglamentoInterno;
use App\Models\AuditoriaRIT;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\CulminacionSocializacionRit;
use App\Models\DiligenciaDescargo;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Trazabilidad unificada del "equivalente funcional de firma" (pedido de
 * Andrés Sarmiento, reunión 2026-10-05, ampliado 2026-10-09): una sola línea de
 * tiempo con CADA momento en que alguien aceptó o autorizó algo en el sistema
 * (sanción, descargos, RIT...), con la evidencia que quedó: selfie de
 * verificación, IP, dispositivo, consentimiento, huella del texto, etc.
 *
 * No hay una tabla única de evidencias: cada proceso guarda la suya. Este
 * servicio las normaliza al leer (nada se copia ni se duplica) a filas con la
 * misma forma. Cada "fuente" es un tipo de evento y define cómo consultarla,
 * cómo ordenarla por fecha y cómo convertirla a fila.
 *
 * Alcance: cada usuario ve solo lo de las empresas a las que tiene acceso (los
 * modelos con ScopedToBufeteOrEmpresa se filtran solos; el resto por empresa_id).
 */
class EquivalenteFuncionalService
{
    /** @var array<int, int>|null */
    private ?array $empresaIds = null;

    /** Tipos de evento, en el orden en que se muestran en el filtro. */
    public function tipos(): array
    {
        return collect($this->fuentes())->map(fn ($f) => $f['label'])->all();
    }

    /**
     * @param array{tipo?: string, buscar?: string, desde?: ?string, hasta?: ?string, solo_selfie?: bool} $filtros
     * @return array{items: Collection<int, array<string, mixed>>, total: int}
     */
    public function consultar(array $filtros, int $pagina = 1, int $porPagina = 15): array
    {
        $tipo = $filtros['tipo'] ?? 'todos';
        $hasta = max(1, $pagina) * $porPagina;

        $filas = collect();
        $total = 0;

        foreach ($this->fuentes() as $clave => $fuente) {
            if ($tipo !== 'todos' && $tipo !== $clave) {
                continue;
            }

            $consulta = $this->consultaFiltrada($fuente, $filtros);
            $total += (clone $consulta)->count();

            // Para paginar entre fuentes se toman, de cada una, las `pagina * porPagina`
            // más recientes: la fila N global siempre está entre ellas.
            $consulta->orderByRaw($fuente['fecha_sql'] . ' desc')->limit($hasta)->get()
                ->each(fn ($registro) => $filas->push($this->fila($clave, $fuente, $registro)));
        }

        $items = $filas->sortByDesc(fn ($f) => $f['fecha']?->getTimestamp() ?? 0)
            ->slice(($pagina - 1) * $porPagina, $porPagina)
            ->values();

        return ['items' => $items, 'total' => $total];
    }

    /** @return array{disco: ?string, ruta: string}|null  Selfie de un evento, solo si el usuario puede verla. */
    public function fotoDe(string $tipo, int $id): ?array
    {
        $fuente = $this->fuentes()[$tipo] ?? null;
        if (!$fuente || empty($fuente['foto'])) {
            return null;
        }

        $registro = ($fuente['consulta'])()->whereKey($id)->first();
        $ruta = $registro ? ($fuente['foto'])($registro) : null;

        return $ruta ? ['disco' => $fuente['disco'] ?? null, 'ruta' => $ruta] : null;
    }

    /** Resumen legible del resultado del quiz: "5/5 correctas, 7 intentos". */
    public static function resumenQuiz(?array $quiz): string
    {
        if (empty($quiz)) {
            return 'Sin quiz';
        }

        $aciertos = 0;
        $intentos = 0;
        foreach ($quiz as $pregunta) {
            $lista = $pregunta['intentos'] ?? [];
            $intentos += count($lista);
            if (!empty($lista) && !empty(end($lista)['correcta'])) {
                $aciertos++;
            }
        }

        return $aciertos . '/' . count($quiz) . ' correctas, ' . $intentos . ' intentos';
    }

    // ─── Fuentes ─────────────────────────────────────────────────────────────

    /**
     * Cada fuente: label, rol (de quien deja la evidencia), consulta (Builder ya
     * acotado al usuario), fecha_sql (misma fecha que usa 'fecha', para ordenar y
     * filtrar en SQL), buscar (closure Builder+término), fila (closure modelo ->
     * datos), y opcionalmente foto/disco.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fuentes(): array
    {
        $etiquetaSancion = fn (?string $t) => match ($t) {
            'llamado_atencion' => 'Llamado de Atención',
            'suspension' => 'Suspensión Laboral',
            'multa' => 'Multa',
            'terminacion' => 'Terminación de Contrato',
            'no_sancion' => 'No aplica sanción',
            default => $t ?? '-',
        };

        $buscarProceso = function (Builder $q, string $t) {
            $q->where(function ($w) use ($t) {
                $w->where('codigo', 'like', "%{$t}%")
                    ->orWhere('autorizador_nombre', 'like', "%{$t}%")
                    ->orWhere('citante_nombre', 'like', "%{$t}%")
                    ->orWhereHas('trabajador', fn ($x) => $x->where('nombres', 'like', "%{$t}%")
                        ->orWhere('apellidos', 'like', "%{$t}%")
                        ->orWhere('numero_documento', 'like', "%{$t}%"));
            });
        };

        $buscarDiligencia = function (Builder $q, string $t) {
            $q->where(function ($w) use ($t) {
                $w->where('ip_acceso', 'like', "%{$t}%")
                    ->orWhere('disclaimer_ip', 'like', "%{$t}%")
                    ->orWhereHas('proceso', fn ($p) => $p->where('codigo', 'like', "%{$t}%")
                        ->orWhereHas('trabajador', fn ($x) => $x->where('nombres', 'like', "%{$t}%")
                            ->orWhere('apellidos', 'like', "%{$t}%")
                            ->orWhere('numero_documento', 'like', "%{$t}%")));
            });
        };

        return [
            'sancion_autorizacion' => [
                'label' => 'Autorización de sanción',
                'rol' => 'Autorizador',
                'consulta' => fn () => ProcesoDisciplinario::query()->whereNotNull('autorizador_nombre')->with(['trabajador', 'empresa']),
                'fecha_sql' => 'COALESCE(foto_autorizador_en, updated_at)',
                'buscar' => $buscarProceso,
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_autorizador_path'),
                'foto' => fn ($m) => $m->foto_autorizador_path,
                'disco' => 'public',
                'fila' => fn (ProcesoDisciplinario $m) => [
                    'fecha' => $m->foto_autorizador_en ?? $m->updated_at,
                    'proceso' => 'Proceso ' . $m->codigo,
                    'proceso_clave' => (string) $m->codigo,
                    'actor' => $m->autorizador_nombre,
                    'actor_detalle' => $m->autorizador_cargo,
                    'sujeto' => $m->trabajador?->nombre_completo,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => $m->disclaimer_datos_autorizador_ip,
                    'dispositivo' => null,
                    'selfie' => !empty($m->foto_autorizador_path),
                    'detalle' => array_filter([
                        'Decisión autorizada' => $etiquetaSancion($m->tipo_sancion),
                        'Consentimiento de datos (Ley 1581)' => $m->disclaimer_datos_autorizador_en?->format('d/m/Y H:i'),
                    ]),
                    'url_proceso' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('view', ['record' => $m]),
                    'url_acta' => null,
                ],
            ],

            'sancion_exoneracion' => [
                'label' => 'Aceptación de decisión distinta a la recomendada',
                'rol' => 'Autorizador',
                'consulta' => fn () => ProcesoDisciplinario::query()->where('exoneracion_aceptada', true)->with(['trabajador', 'empresa']),
                'fecha_sql' => 'COALESCE(exoneracion_aceptada_en, updated_at)',
                'buscar' => $buscarProceso,
                'con_selfie' => fn (Builder $q) => $q->whereRaw('1 = 0'),
                'fila' => fn (ProcesoDisciplinario $m) => [
                    'fecha' => $m->exoneracion_aceptada_en ?? $m->updated_at,
                    'proceso' => 'Proceso ' . $m->codigo,
                    'proceso_clave' => (string) $m->codigo,
                    'actor' => $m->autorizador_nombre,
                    'actor_detalle' => $m->autorizador_cargo,
                    'sujeto' => $m->trabajador?->nombre_completo,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => $m->exoneracion_ip,
                    'dispositivo' => null,
                    'selfie' => false,
                    'detalle' => array_filter([
                        'Decisión tomada' => $etiquetaSancion($m->tipo_sancion),
                        'Recomendación del sistema' => $etiquetaSancion($m->sancion_ia_recomendada),
                        'Razón de la decisión distinta' => $m->razon_divergencia,
                    ]),
                    'url_proceso' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('view', ['record' => $m]),
                    'url_acta' => null,
                ],
            ],

            'descargos_citacion' => [
                'label' => 'Citación a descargos',
                'rol' => 'Funcionario que cita',
                'consulta' => fn () => ProcesoDisciplinario::query()->whereNotNull('citante_nombre')->with(['trabajador', 'empresa']),
                'fecha_sql' => 'COALESCE(foto_citante_en, created_at)',
                'buscar' => $buscarProceso,
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_citante_path'),
                'foto' => fn ($m) => $m->foto_citante_path,
                'disco' => 'public',
                'fila' => fn (ProcesoDisciplinario $m) => [
                    'fecha' => $m->foto_citante_en ?? $m->created_at,
                    'proceso' => 'Proceso ' . $m->codigo,
                    'proceso_clave' => (string) $m->codigo,
                    'actor' => $m->citante_nombre,
                    'actor_detalle' => $m->citante_cargo,
                    'sujeto' => $m->trabajador?->nombre_completo,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => null,
                    'dispositivo' => null,
                    'selfie' => !empty($m->foto_citante_path),
                    'detalle' => array_filter(['Estado del proceso' => $m->estado]),
                    'url_proceso' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('view', ['record' => $m]),
                    'url_acta' => null,
                ],
            ],

            'descargos_aceptacion' => [
                'label' => 'Aceptación del trabajador para rendir descargos',
                'rol' => 'Trabajador',
                'consulta' => fn () => DiligenciaDescargo::query()
                    ->where(fn ($q) => $q->whereNotNull('disclaimer_aceptado_en')->orWhereNotNull('foto_inicio_path'))
                    ->whereHas('proceso')
                    ->with(['proceso.trabajador', 'proceso.empresa']),
                'fecha_sql' => 'COALESCE(disclaimer_aceptado_en, foto_inicio_en, updated_at)',
                'buscar' => $buscarDiligencia,
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_inicio_path'),
                'foto' => fn ($m) => $m->foto_inicio_path,
                'disco' => null,
                'fila' => fn (DiligenciaDescargo $m) => [
                    'fecha' => $m->disclaimer_aceptado_en ?? $m->foto_inicio_en ?? $m->updated_at,
                    'proceso' => 'Proceso ' . $m->proceso?->codigo,
                    'proceso_clave' => (string) $m->proceso?->codigo,
                    'actor' => $m->proceso?->trabajador?->nombre_completo,
                    'actor_detalle' => $m->proceso?->trabajador?->cargo,
                    'sujeto' => $m->proceso?->trabajador?->nombre_completo,
                    'empresa' => $m->proceso?->empresa?->razon_social,
                    'ip' => $m->disclaimer_ip ?: $m->ip_acceso,
                    'dispositivo' => null,
                    'selfie' => !empty($m->foto_inicio_path),
                    'detalle' => array_filter([
                        'Aceptó el aviso de datos' => $m->disclaimer_aceptado_en?->format('d/m/Y H:i'),
                        'Verificó su identidad por código (OTP)' => $m->otp_verificado_en
                            ? $m->otp_verificado_en->format('d/m/Y H:i') . ($m->otp_canal ? ' por ' . $m->otp_canal : '')
                            : null,
                    ]),
                    'url_proceso' => $m->proceso
                        ? \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('view', ['record' => $m->proceso])
                        : null,
                    'url_acta' => null,
                ],
            ],

            'descargos_cierre' => [
                'label' => 'Verificación al finalizar los descargos',
                'rol' => 'Trabajador',
                'consulta' => fn () => DiligenciaDescargo::query()->whereNotNull('foto_fin_path')->whereHas('proceso')
                    ->with(['proceso.trabajador', 'proceso.empresa']),
                'fecha_sql' => 'COALESCE(foto_fin_en, updated_at)',
                'buscar' => $buscarDiligencia,
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_fin_path'),
                'foto' => fn ($m) => $m->foto_fin_path,
                'disco' => null,
                'fila' => fn (DiligenciaDescargo $m) => [
                    'fecha' => $m->foto_fin_en ?? $m->updated_at,
                    'proceso' => 'Proceso ' . $m->proceso?->codigo,
                    'proceso_clave' => (string) $m->proceso?->codigo,
                    'actor' => $m->proceso?->trabajador?->nombre_completo,
                    'actor_detalle' => $m->proceso?->trabajador?->cargo,
                    'sujeto' => $m->proceso?->trabajador?->nombre_completo,
                    'empresa' => $m->proceso?->empresa?->razon_social,
                    'ip' => $m->ip_acceso,
                    'dispositivo' => null,
                    'selfie' => true,
                    'detalle' => [],
                    'url_proceso' => $m->proceso
                        ? \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('view', ['record' => $m->proceso])
                        : null,
                    'url_acta' => null,
                ],
            ],

            'rit_autorizacion' => [
                'label' => 'Autorización del Reglamento Interno',
                'rol' => 'Autorizador',
                'consulta' => fn () => AutorizacionReglamentoInterno::query()->whereIn('empresa_id', $this->empresaIds())->with(['empresa', 'reglamentoInterno']),
                'fecha_sql' => 'COALESCE(foto_autorizador_en, created_at)',
                'buscar' => fn (Builder $q, string $t) => $q->where(fn ($w) => $w->where('autorizador_nombre', 'like', "%{$t}%")
                    ->orWhere('disclaimer_datos_autorizador_ip', 'like', "%{$t}%")),
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_autorizador_path'),
                'foto' => fn ($m) => $m->foto_autorizador_path,
                'disco' => 'public',
                'fila' => fn (AutorizacionReglamentoInterno $m) => [
                    'fecha' => $m->foto_autorizador_en ?? $m->created_at,
                    'proceso' => 'RIT #' . $m->reglamento_interno_id . ($m->reglamentoInterno?->version ? ' (versión ' . $m->reglamentoInterno->version . ')' : ''),
                    'proceso_clave' => 'RIT #' . $m->reglamento_interno_id,
                    'actor' => $m->autorizador_nombre,
                    'actor_detalle' => $m->autorizador_cargo,
                    'sujeto' => null,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => $m->disclaimer_datos_autorizador_ip,
                    'dispositivo' => null,
                    'selfie' => !empty($m->foto_autorizador_path),
                    'detalle' => array_filter([
                        'Consentimiento de datos (Ley 1581)' => $m->disclaimer_datos_autorizador_en?->format('d/m/Y H:i'),
                        'Huella del texto autorizado' => $m->texto_rit_hash,
                    ]),
                    'url_proceso' => \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(),
                    'url_acta' => null,
                ],
            ],

            'rit_aceptacion' => [
                'label' => 'Aceptación del Reglamento por el trabajador',
                'rol' => 'Trabajador',
                'consulta' => fn () => AceptacionReglamentoInterno::query()->whereHas('trabajador')->with(['trabajador.empresa', 'reglamentoInterno']),
                'fecha_sql' => 'aceptado_en',
                'buscar' => fn (Builder $q, string $t) => $q->where(fn ($w) => $w->where('ip_aceptacion', 'like', "%{$t}%")
                    ->orWhereHas('trabajador', fn ($x) => $x->where('nombres', 'like', "%{$t}%")
                        ->orWhere('apellidos', 'like', "%{$t}%")
                        ->orWhere('numero_documento', 'like', "%{$t}%"))),
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_aceptacion_path'),
                'foto' => fn ($m) => $m->foto_aceptacion_path,
                'disco' => 'local',
                'fila' => fn (AceptacionReglamentoInterno $m) => [
                    'fecha' => $m->aceptado_en,
                    'proceso' => 'RIT #' . $m->reglamento_interno_id . ($m->reglamentoInterno?->version ? ' (versión ' . $m->reglamentoInterno->version . ')' : ''),
                    'proceso_clave' => 'RIT #' . $m->reglamento_interno_id,
                    'actor' => $m->trabajador?->nombre_completo,
                    'actor_detalle' => $m->trabajador?->cargo,
                    'sujeto' => $m->trabajador?->nombre_completo,
                    'empresa' => $m->trabajador?->empresa?->razon_social,
                    'ip' => $m->ip_aceptacion,
                    'dispositivo' => $m->user_agent,
                    'selfie' => !empty($m->foto_aceptacion_path),
                    'detalle' => array_filter([
                        'Quiz de comprensión' => self::resumenQuiz($m->quiz_resultado),
                        'Huella del texto aceptado' => $m->texto_rit_hash,
                    ]),
                    'url_proceso' => null,
                    'url_acta' => $m->ruta_acta
                        ? route('trabajador.acta-rit.descargar', ['trabajador' => $m->trabajador_id, 'aceptacion' => $m->id])
                        : null,
                ],
            ],

            'rit_culminacion' => [
                'label' => 'Culminación de la socialización del Reglamento',
                'rol' => 'Administrador de la empresa',
                'consulta' => fn () => CulminacionSocializacionRit::query()->whereIn('empresa_id', $this->empresaIds())->with(['empresa', 'user', 'reglamentoInterno']),
                'fecha_sql' => 'declarado_en',
                'buscar' => fn (Builder $q, string $t) => $q->where(fn ($w) => $w->where('ip', 'like', "%{$t}%")
                    ->orWhereHas('user', fn ($x) => $x->where('name', 'like', "%{$t}%"))),
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('foto_admin_path'),
                'foto' => fn ($m) => $m->foto_admin_path,
                'disco' => 'public',
                'fila' => fn (CulminacionSocializacionRit $m) => [
                    'fecha' => $m->declarado_en,
                    'proceso' => 'RIT #' . $m->reglamento_interno_id,
                    'proceso_clave' => 'RIT #' . $m->reglamento_interno_id,
                    'actor' => $m->user?->name,
                    'actor_detalle' => null,
                    'sujeto' => null,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => $m->ip,
                    'dispositivo' => $m->user_agent,
                    'selfie' => !empty($m->foto_admin_path),
                    'detalle' => array_filter(['Huella del texto' => $m->texto_rit_hash]),
                    'url_proceso' => \App\Filament\Admin\Pages\MiReglamentoInterno::getUrl(),
                    'url_acta' => null,
                ],
            ],

            'rit_auditoria' => [
                'label' => 'Declaración de autoridad en la auditoría del Reglamento',
                'rol' => 'Responsable',
                'consulta' => fn () => AuditoriaRIT::query()->whereNotNull('responsable_nombre')->whereIn('empresa_id', $this->empresaIds())->with(['empresa']),
                'fecha_sql' => 'COALESCE(autoridad_declarada_at, updated_at)',
                'buscar' => fn (Builder $q, string $t) => $q->where(fn ($w) => $w->where('responsable_nombre', 'like', "%{$t}%")
                    ->orWhere('responsable_documento', 'like', "%{$t}%")),
                'con_selfie' => fn (Builder $q) => $q->whereNotNull('responsable_foto_path'),
                'foto' => fn ($m) => $m->responsable_foto_path,
                'disco' => 'public',
                'fila' => fn (AuditoriaRIT $m) => [
                    'fecha' => $m->autoridad_declarada_at ?? $m->updated_at,
                    'proceso' => 'Auditoría del RIT #' . $m->id,
                    'proceso_clave' => 'Auditoría del RIT #' . $m->id,
                    'actor' => $m->responsable_nombre,
                    'actor_detalle' => $m->responsable_cargo,
                    'sujeto' => null,
                    'empresa' => $m->empresa?->razon_social,
                    'ip' => null,
                    'dispositivo' => null,
                    'selfie' => !empty($m->responsable_foto_path),
                    'detalle' => array_filter([
                        'Documento' => $m->responsable_documento,
                        'Declaró tener autoridad para aceptar' => $m->autoridad_declarada ? 'Sí' : null,
                    ]),
                    'url_proceso' => \App\Filament\Admin\Pages\AuditarRIT::getUrl(),
                    'url_acta' => null,
                ],
            ],
        ];
    }

    // ─── Apoyo ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $fuente */
    private function consultaFiltrada(array $fuente, array $filtros): Builder
    {
        $consulta = ($fuente['consulta'])();
        $fecha = $fuente['fecha_sql'];

        if (filled($filtros['buscar'] ?? null)) {
            ($fuente['buscar'])($consulta, trim($filtros['buscar']));
        }
        if (filled($filtros['desde'] ?? null)) {
            $consulta->whereRaw("{$fecha} >= ?", [Carbon::parse($filtros['desde'])->startOfDay()]);
        }
        if (filled($filtros['hasta'] ?? null)) {
            $consulta->whereRaw("{$fecha} <= ?", [Carbon::parse($filtros['hasta'])->endOfDay()]);
        }
        if (!empty($filtros['solo_selfie'])) {
            ($fuente['con_selfie'])($consulta);
        }

        return $consulta;
    }

    /** @param array<string, mixed> $fuente */
    private function fila(string $clave, array $fuente, $registro): array
    {
        return ($fuente['fila'])($registro) + [
            'id' => $registro->getKey(),
            'tipo' => $clave,
            'tipo_etiqueta' => $fuente['label'],
            'rol' => $fuente['rol'],
            'url_selfie' => (!empty($fuente['foto']) && ($fuente['foto'])($registro))
                ? route('equivalente-funcional.selfie', ['tipo' => $clave, 'id' => $registro->getKey()])
                : null,
        ];
    }

    /** @return array<int, int> Empresas a las que el usuario tiene acceso (el scope de Empresa ya lo resuelve). */
    private function empresaIds(): array
    {
        return $this->empresaIds ??= Empresa::query()->pluck('id')->all();
    }

    /** Ruta absoluta de un archivo de selfie (o null si no existe). */
    public function rutaAbsoluta(?string $disco, string $ruta): ?string
    {
        $almacen = $disco ? Storage::disk($disco) : Storage::disk();

        return $almacen->exists($ruta) ? $almacen->path($ruta) : null;
    }
}
