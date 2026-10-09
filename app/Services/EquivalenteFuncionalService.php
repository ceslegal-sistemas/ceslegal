<?php

namespace App\Services;

use App\Models\AceptacionReglamentoInterno;
use App\Models\AuditoriaRIT;
use App\Models\AutorizacionReglamentoInterno;
use App\Models\CulminacionSocializacionRit;
use App\Models\DiligenciaDescargo;
use App\Models\Empresa;
use App\Models\EventoEquivalenteFuncional;
use App\Models\ProcesoDisciplinario;
use App\Models\Trabajador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Trazabilidad unificada del "equivalente funcional de firma" (pedido de
 * Andrés Sarmiento, reunión 2026-10-05, ampliado 2026-10-09): una sola línea de
 * tiempo con CADA momento en que alguien aceptó o autorizó algo en el sistema
 * (sanción, descargos, RIT...), con la evidencia que quedó: selfie de
 * verificación, IP, dispositivo, consentimiento, huella del texto, etc.
 *
 * No hay una tabla única de evidencias: cada proceso guarda la suya. Este
 * servicio las une al leer con un UNION ALL (nada se copia ni se duplica) y
 * devuelve un Builder sobre ese resultado, para que la tabla nativa de Filament
 * pueda buscar, filtrar, ordenar, agrupar y paginar en SQL como con cualquier
 * modelo. Cada fuente aporta las mismas columnas (ver COLUMNAS).
 *
 * Alcance: cada usuario ve solo lo de las empresas a las que tiene acceso. Los
 * modelos con ScopedToBufeteOrEmpresa se filtran solos; el resto se acota con
 * una subconsulta a Empresa/Trabajador/ProcesoDisciplinario, que ya traen ese scope.
 */
class EquivalenteFuncionalService
{
    /** Columnas que TODA fuente debe devolver, en este orden (un UNION exige la misma forma). */
    private const COLUMNAS = [
        'uid', 'tipo', 'id_origen', 'fecha', 'proceso_clave', 'proceso_extra',
        'ref_proceso', 'ref_trabajador', 'actor_nombre', 'actor_cargo',
        'sujeto_nombre', 'sujeto_documento', 'empresa_nombre', 'ip', 'dispositivo',
        'tiene_selfie', 'd1', 'd2', 'd3',
    ];

    /** Tipos de evento: clave => etiqueta. */
    public const TIPOS = [
        'sancion_autorizacion' => 'Autorización de sanción',
        'sancion_exoneracion' => 'Aceptación de decisión distinta a la recomendada',
        'descargos_citacion' => 'Citación a descargos',
        'descargos_aceptacion' => 'Aceptación del trabajador para rendir descargos',
        'descargos_cierre' => 'Verificación al finalizar los descargos',
        'rit_autorizacion' => 'Autorización del Reglamento Interno',
        'rit_aceptacion' => 'Aceptación del Reglamento por el trabajador',
        'rit_culminacion' => 'Culminación de la socialización del Reglamento',
        'rit_auditoria' => 'Declaración de autoridad en la auditoría del Reglamento',
    ];

    /** Qué significa cada columna d1/d2/d3 según el tipo de evento (null = no se usa). */
    private const ETIQUETAS_DETALLE = [
        'sancion_autorizacion' => ['Decisión autorizada', 'Consentimiento de datos (Ley 1581)', null],
        'sancion_exoneracion' => ['Decisión tomada', 'Recomendación del sistema', 'Razón de la decisión distinta'],
        'descargos_citacion' => ['Estado del proceso', null, null],
        'descargos_aceptacion' => ['Aceptó el aviso de datos', 'Verificó su identidad por código (OTP)', 'Canal del código'],
        'descargos_cierre' => [null, null, null],
        'rit_autorizacion' => ['Consentimiento de datos (Ley 1581)', 'Huella del texto autorizado', null],
        'rit_aceptacion' => ['Quiz de comprensión', 'Huella del texto aceptado', null],
        'rit_culminacion' => ['Huella del texto', null, null],
        'rit_auditoria' => ['Documento del responsable', 'Declaró tener autoridad para aceptar', null],
    ];

    public function tipos(): array
    {
        return self::TIPOS;
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        return self::TIPOS[$tipo] ?? ($tipo ?? '-');
    }

    /** Todos los eventos de trazabilidad que el usuario puede ver, como Builder de Eloquent. */
    public function consulta(): Builder
    {
        $union = null;
        foreach ($this->fuentes() as $tipo => $consulta) {
            $base = $consulta->toBase();
            $union = $union ? $union->unionAll($base) : $base;
        }

        $modelo = new EventoEquivalenteFuncional();

        return $modelo->newQuery()->fromSub($union, $modelo->getTable());
    }

    /** Total de registros por sección del reporte (null si esa consulta falla). */
    public function conteos(): array
    {
        $cliente = function (Builder $q) {
            $user = auth()->user();
            if ($user?->role === 'cliente' && $user->empresa_id) {
                $q->where('empresa_id', $user->empresa_id);
            }

            return $q;
        };
        $cuenta = function (\Closure $consulta): ?int {
            try {
                return $consulta()->count();
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        };

        return [
            'todos' => $cuenta(fn () => $this->consulta()),
            'con_selfie' => $cuenta(fn () => $this->consulta()->where('tiene_selfie', 1)),
            'sanciones' => $cuenta(fn () => $cliente(ProcesoDisciplinario::query()->whereNotNull('autorizador_nombre'))),
            'rit' => $cuenta(fn () => $cliente(AutorizacionReglamentoInterno::query())),
            'descargos' => $cuenta(fn () => $cliente(ProcesoDisciplinario::query()->whereNotNull('citante_nombre'))),
            'trabajadores' => $cuenta(fn () => AceptacionReglamentoInterno::query()->whereHas('trabajador')),
            'videos' => $cuenta(fn () => $cliente(\App\Models\HistoricoVideoDidacticoRit::query())),
        ];
    }

    /** @return array<string, string> etiqueta => valor, solo con lo que el evento realmente guardó. */
    public function detalleDe(EventoEquivalenteFuncional $evento): array
    {
        $detalle = [];
        $etiquetas = self::ETIQUETAS_DETALLE[$evento->tipo] ?? [null, null, null];

        foreach (['d1', 'd2', 'd3'] as $i => $columna) {
            $valor = $evento->{$columna};
            if (!filled($valor) || $etiquetas[$i] === null) {
                continue;
            }
            $detalle[$etiquetas[$i]] = $this->formatearDetalle($evento->tipo, $i, (string) $valor);
        }

        if (filled($evento->dispositivo)) {
            $detalle['Dispositivo y navegador'] = $evento->dispositivo;
        }

        return $detalle;
    }

    /** @return array{disco: ?string, ruta: string}|null  Selfie de un evento, solo si el usuario puede verla. */
    public function fotoDe(string $tipo, int $id): ?array
    {
        $origen = $this->origenesFoto()[$tipo] ?? null;
        if (!$origen) {
            return null;
        }

        [$consulta, $campo, $disco] = $origen;
        $ruta = ($consulta)()->whereKey($id)->value($campo);

        return $ruta ? ['disco' => $disco, 'ruta' => $ruta] : null;
    }

    /** Ruta absoluta de un archivo de selfie (o null si no existe). */
    public function rutaAbsoluta(?string $disco, string $ruta): ?string
    {
        $almacen = $disco ? Storage::disk($disco) : Storage::disk();

        return $almacen->exists($ruta) ? $almacen->path($ruta) : null;
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

    /** @return array<string, Builder> tipo => consulta ya acotada y con las COLUMNAS normalizadas */
    private function fuentes(): array
    {
        $empresas = fn () => Empresa::query()->select('id');
        $trabajadores = fn () => Trabajador::query()->select('id');
        $procesos = fn () => ProcesoDisciplinario::query()->select('id');

        $nombre = fn (string $a) => $this->concat(["{$a}.nombres", "' '", "{$a}.apellidos"]);
        $conSelfie = fn (string $campo) => "CASE WHEN {$campo} IS NULL OR {$campo} = '' THEN 0 ELSE 1 END";

        // ProcesoDisciplinario: base común de las tres fuentes de sanción/citación.
        $proceso = function () {
            return ProcesoDisciplinario::query()
                ->leftJoin('trabajadores as t', 't.id', '=', 'procesos_disciplinarios.trabajador_id')
                ->leftJoin('empresas as e', 'e.id', '=', 'procesos_disciplinarios.empresa_id');
        };
        $columnasProceso = fn (string $tipo, array $extra) => $extra + [
            'uid' => $this->concat(["'{$tipo}-'", 'procesos_disciplinarios.id']),
            'tipo' => "'{$tipo}'",
            'id_origen' => 'procesos_disciplinarios.id',
            'proceso_clave' => $this->concat(["'Proceso '", 'procesos_disciplinarios.codigo']),
            'ref_proceso' => 'procesos_disciplinarios.id',
            'ref_trabajador' => 'procesos_disciplinarios.trabajador_id',
            'sujeto_nombre' => $this->concat(['t.nombres', "' '", 't.apellidos']),
            'sujeto_documento' => 't.numero_documento',
            'empresa_nombre' => 'e.razon_social',
        ];

        // DiligenciaDescargo: el trabajador y la empresa salen del proceso al que pertenece.
        $diligencia = function () use ($procesos) {
            return DiligenciaDescargo::query()
                ->join('procesos_disciplinarios as p', 'p.id', '=', 'diligencias_descargos.proceso_id')
                ->leftJoin('trabajadores as t', 't.id', '=', 'p.trabajador_id')
                ->leftJoin('empresas as e', 'e.id', '=', 'p.empresa_id')
                ->whereIn('diligencias_descargos.proceso_id', $procesos());
        };
        $columnasDiligencia = fn (string $tipo, array $extra) => $extra + [
            'uid' => $this->concat(["'{$tipo}-'", 'diligencias_descargos.id']),
            'tipo' => "'{$tipo}'",
            'id_origen' => 'diligencias_descargos.id',
            'proceso_clave' => $this->concat(["'Proceso '", 'p.codigo']),
            'ref_proceso' => 'p.id',
            'ref_trabajador' => 'p.trabajador_id',
            'actor_nombre' => $this->concat(['t.nombres', "' '", 't.apellidos']),
            'actor_cargo' => 't.cargo',
            'sujeto_nombre' => $this->concat(['t.nombres', "' '", 't.apellidos']),
            'sujeto_documento' => 't.numero_documento',
            'empresa_nombre' => 'e.razon_social',
        ];

        $rit = fn (string $tabla) => $this->concat(["'RIT #'", "r.id"]);

        return [
            'sancion_autorizacion' => $this->seleccion(
                $proceso()->whereNotNull('procesos_disciplinarios.autorizador_nombre'),
                $columnasProceso('sancion_autorizacion', [
                    'fecha' => 'COALESCE(procesos_disciplinarios.foto_autorizador_en, procesos_disciplinarios.updated_at)',
                    'actor_nombre' => 'procesos_disciplinarios.autorizador_nombre',
                    'actor_cargo' => 'procesos_disciplinarios.autorizador_cargo',
                    'ip' => 'procesos_disciplinarios.disclaimer_datos_autorizador_ip',
                    'tiene_selfie' => $conSelfie('procesos_disciplinarios.foto_autorizador_path'),
                    'd1' => 'procesos_disciplinarios.tipo_sancion',
                    'd2' => $this->texto('procesos_disciplinarios.disclaimer_datos_autorizador_en'),
                ]),
            ),

            'sancion_exoneracion' => $this->seleccion(
                $proceso()->where('procesos_disciplinarios.exoneracion_aceptada', true),
                $columnasProceso('sancion_exoneracion', [
                    'fecha' => 'COALESCE(procesos_disciplinarios.exoneracion_aceptada_en, procesos_disciplinarios.updated_at)',
                    'actor_nombre' => 'procesos_disciplinarios.autorizador_nombre',
                    'actor_cargo' => 'procesos_disciplinarios.autorizador_cargo',
                    'ip' => 'procesos_disciplinarios.exoneracion_ip',
                    'tiene_selfie' => '0',
                    'd1' => 'procesos_disciplinarios.tipo_sancion',
                    'd2' => 'procesos_disciplinarios.sancion_ia_recomendada',
                    'd3' => $this->texto('procesos_disciplinarios.razon_divergencia'),
                ]),
            ),

            'descargos_citacion' => $this->seleccion(
                $proceso()->whereNotNull('procesos_disciplinarios.citante_nombre'),
                $columnasProceso('descargos_citacion', [
                    'fecha' => 'COALESCE(procesos_disciplinarios.foto_citante_en, procesos_disciplinarios.created_at)',
                    'actor_nombre' => 'procesos_disciplinarios.citante_nombre',
                    'actor_cargo' => 'procesos_disciplinarios.citante_cargo',
                    'tiene_selfie' => $conSelfie('procesos_disciplinarios.foto_citante_path'),
                    'd1' => 'procesos_disciplinarios.estado',
                ]),
            ),

            'descargos_aceptacion' => $this->seleccion(
                $diligencia()->where(fn ($q) => $q->whereNotNull('diligencias_descargos.disclaimer_aceptado_en')
                    ->orWhereNotNull('diligencias_descargos.foto_inicio_path')),
                $columnasDiligencia('descargos_aceptacion', [
                    'fecha' => 'COALESCE(diligencias_descargos.disclaimer_aceptado_en, diligencias_descargos.foto_inicio_en, diligencias_descargos.updated_at)',
                    'ip' => "COALESCE(NULLIF(diligencias_descargos.disclaimer_ip, ''), diligencias_descargos.ip_acceso)",
                    'tiene_selfie' => $conSelfie('diligencias_descargos.foto_inicio_path'),
                    'd1' => $this->texto('diligencias_descargos.disclaimer_aceptado_en'),
                    'd2' => $this->texto('diligencias_descargos.otp_verificado_en'),
                    'd3' => 'diligencias_descargos.otp_canal',
                ]),
            ),

            'descargos_cierre' => $this->seleccion(
                $diligencia()->whereNotNull('diligencias_descargos.foto_fin_path'),
                $columnasDiligencia('descargos_cierre', [
                    'fecha' => 'COALESCE(diligencias_descargos.foto_fin_en, diligencias_descargos.updated_at)',
                    'ip' => 'diligencias_descargos.ip_acceso',
                    'tiene_selfie' => '1',
                ]),
            ),

            'rit_autorizacion' => $this->seleccion(
                AutorizacionReglamentoInterno::query()
                    ->join('reglamentos_internos as r', 'r.id', '=', 'autorizaciones_reglamento_interno.reglamento_interno_id')
                    ->leftJoin('empresas as e', 'e.id', '=', 'autorizaciones_reglamento_interno.empresa_id')
                    ->whereIn('autorizaciones_reglamento_interno.empresa_id', $empresas()),
                [
                    'uid' => $this->concat(["'rit_autorizacion-'", 'autorizaciones_reglamento_interno.id']),
                    'tipo' => "'rit_autorizacion'",
                    'id_origen' => 'autorizaciones_reglamento_interno.id',
                    'fecha' => 'COALESCE(autorizaciones_reglamento_interno.foto_autorizador_en, autorizaciones_reglamento_interno.created_at)',
                    'proceso_clave' => $rit('r'),
                    'proceso_extra' => $this->concat(["'versión '", 'r.version']),
                    'actor_nombre' => 'autorizaciones_reglamento_interno.autorizador_nombre',
                    'actor_cargo' => 'autorizaciones_reglamento_interno.autorizador_cargo',
                    'empresa_nombre' => 'e.razon_social',
                    'ip' => 'autorizaciones_reglamento_interno.disclaimer_datos_autorizador_ip',
                    'tiene_selfie' => $conSelfie('autorizaciones_reglamento_interno.foto_autorizador_path'),
                    'd1' => $this->texto('autorizaciones_reglamento_interno.disclaimer_datos_autorizador_en'),
                    'd2' => 'autorizaciones_reglamento_interno.texto_rit_hash',
                ],
            ),

            'rit_aceptacion' => $this->seleccion(
                AceptacionReglamentoInterno::query()
                    ->join('trabajadores as t', 't.id', '=', 'aceptaciones_reglamento_interno.trabajador_id')
                    ->join('reglamentos_internos as r', 'r.id', '=', 'aceptaciones_reglamento_interno.reglamento_interno_id')
                    ->leftJoin('empresas as e', 'e.id', '=', 't.empresa_id')
                    ->whereIn('aceptaciones_reglamento_interno.trabajador_id', $trabajadores()),
                [
                    'uid' => $this->concat(["'rit_aceptacion-'", 'aceptaciones_reglamento_interno.id']),
                    'tipo' => "'rit_aceptacion'",
                    'id_origen' => 'aceptaciones_reglamento_interno.id',
                    'fecha' => 'aceptaciones_reglamento_interno.aceptado_en',
                    'proceso_clave' => $rit('r'),
                    'proceso_extra' => $this->concat(["'versión '", 'r.version']),
                    'ref_trabajador' => 't.id',
                    'actor_nombre' => $this->concat(['t.nombres', "' '", 't.apellidos']),
                    'actor_cargo' => 't.cargo',
                    'sujeto_nombre' => $this->concat(['t.nombres', "' '", 't.apellidos']),
                    'sujeto_documento' => 't.numero_documento',
                    'empresa_nombre' => 'e.razon_social',
                    'ip' => 'aceptaciones_reglamento_interno.ip_aceptacion',
                    'dispositivo' => $this->texto('aceptaciones_reglamento_interno.user_agent'),
                    'tiene_selfie' => $conSelfie('aceptaciones_reglamento_interno.foto_aceptacion_path'),
                    'd1' => $this->texto('aceptaciones_reglamento_interno.quiz_resultado'),
                    'd2' => 'aceptaciones_reglamento_interno.texto_rit_hash',
                    'd3' => 'aceptaciones_reglamento_interno.ruta_acta',
                ],
            ),

            'rit_culminacion' => $this->seleccion(
                CulminacionSocializacionRit::query()
                    ->join('reglamentos_internos as r', 'r.id', '=', 'culminaciones_socializacion_rit.reglamento_interno_id')
                    ->leftJoin('empresas as e', 'e.id', '=', 'culminaciones_socializacion_rit.empresa_id')
                    ->leftJoin('users as u', 'u.id', '=', 'culminaciones_socializacion_rit.user_id')
                    ->whereIn('culminaciones_socializacion_rit.empresa_id', $empresas()),
                [
                    'uid' => $this->concat(["'rit_culminacion-'", 'culminaciones_socializacion_rit.id']),
                    'tipo' => "'rit_culminacion'",
                    'id_origen' => 'culminaciones_socializacion_rit.id',
                    'fecha' => 'culminaciones_socializacion_rit.declarado_en',
                    'proceso_clave' => $rit('r'),
                    'proceso_extra' => $this->concat(["'versión '", 'r.version']),
                    'actor_nombre' => 'u.name',
                    'empresa_nombre' => 'e.razon_social',
                    'ip' => 'culminaciones_socializacion_rit.ip',
                    'dispositivo' => $this->texto('culminaciones_socializacion_rit.user_agent'),
                    'tiene_selfie' => $conSelfie('culminaciones_socializacion_rit.foto_admin_path'),
                    'd1' => 'culminaciones_socializacion_rit.texto_rit_hash',
                ],
            ),

            'rit_auditoria' => $this->seleccion(
                AuditoriaRIT::query()
                    ->leftJoin('empresas as e', 'e.id', '=', 'auditorias_rit.empresa_id')
                    ->whereNotNull('auditorias_rit.responsable_nombre')
                    ->whereIn('auditorias_rit.empresa_id', $empresas()),
                [
                    'uid' => $this->concat(["'rit_auditoria-'", 'auditorias_rit.id']),
                    'tipo' => "'rit_auditoria'",
                    'id_origen' => 'auditorias_rit.id',
                    'fecha' => 'COALESCE(auditorias_rit.autoridad_declarada_at, auditorias_rit.updated_at)',
                    'proceso_clave' => $this->concat(["'Auditoría del RIT #'", 'auditorias_rit.id']),
                    'actor_nombre' => 'auditorias_rit.responsable_nombre',
                    'actor_cargo' => 'auditorias_rit.responsable_cargo',
                    'empresa_nombre' => 'e.razon_social',
                    'tiene_selfie' => $conSelfie('auditorias_rit.responsable_foto_path'),
                    'd1' => 'auditorias_rit.responsable_documento',
                    'd2' => "CASE WHEN auditorias_rit.autoridad_declarada = 1 THEN 'Sí' ELSE NULL END",
                ],
            ),
        ];
    }

    /**
     * Selfie de cada tipo de evento: [consulta ya acotada al usuario, columna, disco].
     * Las selfies viven en discos distintos según el proceso que las capturó.
     *
     * @return array<string, array{0: \Closure, 1: string, 2: ?string}>
     */
    private function origenesFoto(): array
    {
        $empresas = fn () => Empresa::query()->select('id');

        return [
            'sancion_autorizacion' => [fn () => ProcesoDisciplinario::query(), 'foto_autorizador_path', 'public'],
            'descargos_citacion' => [fn () => ProcesoDisciplinario::query(), 'foto_citante_path', 'public'],
            'descargos_aceptacion' => [fn () => DiligenciaDescargo::query()->whereHas('proceso'), 'foto_inicio_path', null],
            'descargos_cierre' => [fn () => DiligenciaDescargo::query()->whereHas('proceso'), 'foto_fin_path', null],
            'rit_autorizacion' => [fn () => AutorizacionReglamentoInterno::query()->whereIn('empresa_id', $empresas()), 'foto_autorizador_path', 'public'],
            'rit_aceptacion' => [fn () => AceptacionReglamentoInterno::query()->whereHas('trabajador'), 'foto_aceptacion_path', 'local'],
            'rit_culminacion' => [fn () => CulminacionSocializacionRit::query()->whereIn('empresa_id', $empresas()), 'foto_admin_path', 'public'],
            'rit_auditoria' => [fn () => AuditoriaRIT::query()->whereIn('empresa_id', $empresas()), 'responsable_foto_path', 'public'],
        ];
    }

    // ─── Apoyo ───────────────────────────────────────────────────────────────

    /** Aplica COLUMNAS (con NULL en las que la fuente no aporta) y deja el scope del modelo intacto. */
    private function seleccion(Builder $consulta, array $columnas): Builder
    {
        $columnas += ['proceso_extra' => 'NULL', 'ref_proceso' => 'NULL', 'ref_trabajador' => 'NULL',
            'actor_nombre' => 'NULL', 'actor_cargo' => 'NULL', 'sujeto_nombre' => 'NULL',
            'sujeto_documento' => 'NULL', 'empresa_nombre' => 'NULL', 'ip' => 'NULL', 'dispositivo' => 'NULL',
            'd1' => 'NULL', 'd2' => 'NULL', 'd3' => 'NULL'];

        $partes = [];
        foreach (self::COLUMNAS as $columna) {
            $partes[] = $columnas[$columna] . ' as ' . $columna;
        }

        return $consulta->selectRaw(implode(', ', $partes));
    }

    /** CONCAT portable: MySQL usa CONCAT(), SQLite (pruebas) el operador ||. */
    private function concat(array $partes): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '(' . implode(' || ', $partes) . ')'
            : 'CONCAT(' . implode(', ', $partes) . ')';
    }

    /** Convierte a texto (fechas, JSON) para que el UNION no mezcle tipos entre fuentes. */
    private function texto(string $expresion): string
    {
        return 'CAST(' . $expresion . ' AS ' . (DB::connection()->getDriverName() === 'sqlite' ? 'TEXT' : 'CHAR') . ')';
    }

    private function formatearDetalle(string $tipo, int $posicion, string $valor): string
    {
        // Sanciones: el código interno se muestra con su nombre.
        if (in_array($tipo, ['sancion_autorizacion', 'sancion_exoneracion'], true) && $posicion <= 1 && !str_contains($valor, ':')) {
            $valor = match ($valor) {
                'llamado_atencion' => 'Llamado de Atención',
                'suspension' => 'Suspensión Laboral',
                'multa' => 'Multa',
                'terminacion' => 'Terminación de Contrato',
                'no_sancion' => 'No aplica sanción',
                default => $valor,
            };
        }

        if ($tipo === 'rit_aceptacion' && $posicion === 0) {
            return self::resumenQuiz(json_decode($valor, true) ?: null);
        }

        if ($tipo === 'descargos_aceptacion' && $posicion === 1) {
            return $valor; // fecha y hora del código verificado
        }

        return $valor;
    }
}
