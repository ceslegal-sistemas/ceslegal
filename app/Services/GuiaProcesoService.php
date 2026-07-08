<?php

namespace App\Services;

use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\User;
use App\Support\EmpresaActiva;
use Carbon\Carbon;

/**
 * Calcula el estado de la "Guía del proceso" para el dashboard: en qué paso va el
 * usuario y cuál es la acción siguiente. Toda la lógica testeable vive aquí; el
 * widget solo renderiza.
 */
class GuiaProcesoService
{
    /** Empresa objetivo según el rol (cliente = su empresa; bufete = la activa). */
    public function empresaObjetivo(User $user): ?Empresa
    {
        if ($user->esAbogadoDeBufete()) {
            $id = EmpresaActiva::id();

            return $id ? Empresa::find($id) : null;
        }

        return $user->empresa;
    }

    /** ¿El bufete todavía no tiene ninguna empresa? (gate de onboarding) */
    public function bufeteSinEmpresas(User $user): bool
    {
        return $user->esAbogadoDeBufete()
            && ! Empresa::query()->withoutGlobalScope('bufeteOrEmpresa')
                ->where('bufete_id', $user->bufete_id)->exists();
    }

    /**
     * Datos de la guía para un usuario. Devuelve un arreglo con:
     * estado ('sin_empresa'|'sin_seleccion'|'ok'|'oculto'), empresa, pasos,
     * paso_actual, accion y listos (para sancionar).
     */
    public function para(User $user): array
    {
        // Staff interno: la guía no aplica.
        if (in_array($user->role, ['super_admin', 'abogado'], true)) {
            return ['estado' => 'oculto'];
        }

        if ($this->bufeteSinEmpresas($user)) {
            return [
                'estado' => 'sin_empresa',
                'accion' => [
                    'label' => 'Crear mi primera empresa',
                    'url' => \App\Filament\Admin\Resources\EmpresaResource::getUrl('create'),
                ],
            ];
        }

        $empresa = $this->empresaObjetivo($user);

        if (! $empresa) {
            // Bufete con empresas pero sin ninguna seleccionada en el topbar.
            return ['estado' => 'sin_seleccion'];
        }

        return $this->paraEmpresa($empresa) + ['estado' => 'ok', 'empresa' => $empresa];
    }

    /** Cálculo del roadmap para una empresa concreta. */
    public function paraEmpresa(Empresa $empresa): array
    {
        // ── RIT (base: no la versión mejora_ia) ──
        $rit = ReglamentoInterno::query()
            ->where('empresa_id', $empresa->id)
            ->where('fuente', '!=', 'mejora_ia')
            ->orderByDesc('activo')
            ->orderByDesc('updated_at')
            ->first();
        $tieneRit = $rit !== null;
        $ritSubido = $rit && $rit->fuente === 'subido';
        $ritAuditado = AuditoriaRIT::where('empresa_id', $empresa->id)->exists();

        // ── Descargos ──
        $base = ProcesoDisciplinario::query()->where('empresa_id', $empresa->id);
        $total = (clone $base)->count();
        $esperando = (clone $base)->where('estado', 'descargos_pendientes')->count();

        // ── Listos para sancionar (mismo criterio que la acción "Emitir sanción") ──
        $listos = (clone $base)
            ->whereIn('estado', ['descargos_realizados', 'descargos_no_realizados'])
            ->with('trabajador')
            ->get()
            ->filter(function (ProcesoDisciplinario $p) {
                if (empty($p->trabajador?->email)) {
                    return false;
                }
                if ($p->estado === 'descargos_realizados') {
                    return true;
                }

                return $p->fecha_descargos_programada
                    && Carbon::parse($p->fecha_descargos_programada)->isPast();
            })
            ->map(fn (ProcesoDisciplinario $p) => [
                'codigo' => $p->codigo,
                'trabajador' => $p->trabajador?->nombre_completo ?? 'Trabajador',
            ])
            ->values()
            ->all();

        // ── Pasos ──
        $pasos = [
            ['clave' => 'cuenta', 'label' => 'Cuenta creada', 'estado' => 'done'],
            ['clave' => 'rit', 'label' => 'Reglamento Interno', 'estado' => $tieneRit ? 'done' : 'current'],
            ['clave' => 'descargo', 'label' => 'Crear descargo', 'estado' => $total > 0 ? 'done' : ($tieneRit ? 'current' : 'pending')],
            ['clave' => 'diligencia', 'label' => 'Descargos del trabajador', 'estado' => $this->estadoDiligencia($total, $esperando, count($listos))],
            ['clave' => 'sancion', 'label' => 'Emitir sanción', 'estado' => count($listos) > 0 ? 'current' : 'pending'],
        ];

        // ── Acción siguiente ──
        $accion = $this->accionSiguiente($tieneRit, $ritSubido, $total, $esperando, $listos);

        return [
            'rit' => ['tiene' => $tieneRit, 'subido' => $ritSubido, 'auditado' => $ritAuditado],
            'contadores' => ['total' => $total, 'esperando' => $esperando, 'listos' => count($listos)],
            'pasos' => $pasos,
            'listos' => $listos,
            'accion' => $accion,
            'sancion_url' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('index'),
        ];
    }

    private function estadoDiligencia(int $total, int $esperando, int $listos): string
    {
        if ($listos > 0) {
            return 'done';
        }
        if ($esperando > 0) {
            return 'current';
        }

        return 'pending';
    }

    private function accionSiguiente(bool $tieneRit, bool $ritSubido, int $total, int $esperando, array $listos): array
    {
        if (! $tieneRit) {
            return [
                'tipo' => 'primary',
                'label' => 'Construir o subir mi Reglamento Interno',
                'url' => route('filament.admin.pages.mi-reglamento-interno'),
                'nota' => 'Si sube su reglamento, el sistema lo audita automáticamente. Si lo construye con IA, no necesita auditoría.',
            ];
        }

        if ($total === 0) {
            return [
                'tipo' => 'primary',
                'label' => 'Crear el primer descargo',
                'url' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('create'),
                'nota' => 'Si el trabajador no existe, puede crearlo en el mismo formulario.',
            ];
        }

        if (count($listos) > 0) {
            $n = count($listos);

            return [
                'tipo' => 'sancion',
                'label' => $n === 1 ? 'Emitir la sanción' : "Emitir sanciones ({$n})",
                'url' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('index'),
                'nota' => 'En el listado, use el botón "Emitir Sanción" de cada descargo.',
            ];
        }

        if ($esperando > 0) {
            return [
                'tipo' => 'info',
                'label' => 'Esperando la diligencia del trabajador',
                'url' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('index'),
                'nota' => 'El trabajador recibió la citación; cuando realice sus descargos, aquí aparecerá el botón para emitir la sanción.',
            ];
        }

        return [
            'tipo' => 'info',
            'label' => 'Todo al día',
            'url' => \App\Filament\Admin\Resources\ProcesoDisciplinarioResource::getUrl('index'),
            'nota' => 'No hay acciones pendientes por ahora.',
        ];
    }
}
