<?php

namespace App\Console\Commands;

use App\Jobs\GenerarYEnviarCitacionJob;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Reenvío de citaciones (2026-08-18) tras el bug de APP_URL en producción:
 * el primer corrido de test:seed-descargos-volumen generó y envió las
 * citaciones con un link roto (dominio incorrecto en ese momento). Este
 * comando NO crea procesos nuevos - solo vuelve a disparar
 * GenerarYEnviarCitacionJob para los procesos YA CREADOS de la empresa
 * ficticia de pruebas, ahora que el link se genera con el dominio correcto.
 *
 * También corrige fecha_descargos_programada antes de reenviar: quedó fijada
 * (aleatoria, 3-8 días desde la fecha del corrido original) varios días en
 * el futuro, y DiligenciaDescargo::puedeAccederHoy() BLOQUEA el formulario
 * hasta ese día exacto (app/Models/DiligenciaDescargo.php:140-168). Sin este
 * ajuste el link llegaría pero el formulario seguiría bloqueado hasta esa
 * fecha, impidiendo probar el flujo hoy mismo.
 *
 * Por defecto SOLO toca los procesos cuya fecha_descargos_programada no
 * coincide todavía con --fecha (o con hoy) - así una misma empresa de
 * prueba puede acumular varios lotes creados en días distintos sin que
 * cada corrida reenvíe también los lotes de días anteriores que ya
 * quedaron corregidos (--todos fuerza incluirlos igual).
 *
 * 2026-08-19: además de "el link llegó roto" (requiere reenviar correo),
 * apareció un segundo caso - el link SÍ llega bien pero
 * test:seed-descargos-volumen puso una fecha mala y el correo YA SE ENVIÓ
 * con esa fecha en el PDF. Reenviar el correo de nuevo sería redundante;
 * --sin-reenviar corrige fecha_descargos_programada del proceso Y
 * fecha_acceso_permitida de su DiligenciaDescargo (el campo que de verdad
 * evalúa puedeAccederHoy()) sin tocar el correo ni regenerar el PDF.
 *
 * Seguro de re-ejecutar sin gastar cuota extra de Gemini: DocumentGeneratorService
 * ::generarYEnviarCitacion() reutiliza el token de acceso si ya existe y solo
 * genera preguntas con IA si la diligencia aún no tiene ninguna (ya las tiene
 * de la primera corrida) - solo se regenera el PDF (sin IA) y se reenvía el correo.
 *
 * Aislado por diseño a la empresa con NIT SeedDescargosVolumenTest::NIT_EMPRESA_TEST -
 * nunca toca procesos de empresas reales.
 */
class ReenviarCitacionesVolumenTest extends Command
{
    protected $signature = 'test:reenviar-citaciones-volumen
        {--fecha= : Fecha (Y-m-d) para fecha_descargos_programada de los procesos encontrados. Por defecto, hoy}
        {--todos : Incluye también los procesos cuya fecha ya coincide con --fecha (por defecto se saltan, ya no tienen nada que corregir)}
        {--sin-reenviar : Solo corrige la fecha (proceso + diligencia) sin reenviar el correo ni regenerar el PDF}
        {--dry-run : Solo lista los procesos que se afectarían, sin cambiar ni disparar nada}
        {--force : No pedir confirmación}';

    protected $description = 'Corrige fecha_descargos_programada (y opcionalmente reenvía la citación) de los procesos de la empresa ficticia de pruebas QA que aún no quedaron programados para la fecha correcta.';

    public function handle(): int
    {
        $empresa = Empresa::where('nit', SeedDescargosVolumenTest::NIT_EMPRESA_TEST)->first();

        if (!$empresa) {
            $this->error('No existe la empresa ficticia de pruebas (NIT ' . SeedDescargosVolumenTest::NIT_EMPRESA_TEST . '). Nada que hacer.');
            return self::FAILURE;
        }

        $fecha = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::today();

        $query = ProcesoDisciplinario::with(['trabajador', 'diligenciaDescargo'])
            ->where('empresa_id', $empresa->id);

        if (!$this->option('todos')) {
            $query->whereDate('fecha_descargos_programada', '!=', $fecha->toDateString());
        }

        $procesos = $query->get();

        if ($procesos->isEmpty()) {
            $this->warn('No hay procesos de prueba con fecha distinta a ' . $fecha->toDateString() . '. Nada que hacer (use --todos para incluir los que ya coinciden).');
            return self::SUCCESS;
        }

        $this->info("Se encontraron {$procesos->count()} procesos de prueba en \"{$empresa->razon_social}\":");
        $this->table(
            ['ID', 'Código', 'Trabajador', 'Correo de envío', 'Cargo', 'Fecha actual', 'Fecha nueva'],
            $procesos->map(fn (ProcesoDisciplinario $p) => [
                $p->id,
                $p->codigo,
                $p->trabajador?->nombre_completo,
                $p->trabajador?->email,
                $p->trabajador?->cargo,
                optional($p->fecha_descargos_programada)->toDateString(),
                $fecha->toDateString(),
            ])
        );

        if ($this->option('dry-run')) {
            $this->comment('--dry-run activo: no se disparó ningún job ni se cambió ninguna fecha.');
            return self::SUCCESS;
        }

        $sinReenviar = $this->option('sin-reenviar');
        $accion = $sinReenviar
            ? 'corregir SOLO la fecha (sin reenviar correo ni regenerar PDF)'
            : 'actualizar la fecha y reenviar la citación real (PDF + correo)';

        if (!$this->option('force') && !$this->confirm("¿Va a {$accion} de los {$procesos->count()} procesos listados arriba, con fecha {$fecha->toDateString()}?")) {
            $this->comment('Cancelado.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($procesos->count());
        foreach ($procesos as $proceso) {
            $proceso->update([
                'fecha_descargos_programada' => $fecha,
                'hora_descargos_programada'  => '00:00:00',
            ]);

            if ($sinReenviar) {
                // Sin esto la fecha del proceso queda corregida pero
                // DiligenciaDescargo::puedeAccederHoy() sigue bloqueando el
                // formulario - ese método compara contra fecha_acceso_permitida
                // de la diligencia, no contra el proceso directamente
                // (ver DocumentGeneratorService::generarYEnviarCitacion()).
                $proceso->diligenciaDescargo?->update([
                    'fecha_acceso_permitida' => $fecha->toDateString(),
                    'acceso_habilitado'      => true,
                ]);
            } else {
                GenerarYEnviarCitacionJob::dispatch($proceso, $proceso->abogado_id);
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if ($sinReenviar) {
            $this->info("Fecha corregida en {$procesos->count()} procesos (sin reenviar correo).");
        } else {
            $this->info("Se encolaron {$procesos->count()} reenvíos de citación.");
            $this->warn('Los jobs quedaron en la cola "gemini" - requiere `php artisan queue:work --queue=gemini` corriendo para procesarse.');
        }

        return self::SUCCESS;
    }
}
