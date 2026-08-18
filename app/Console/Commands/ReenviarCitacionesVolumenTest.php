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
 * fecha, impidiendo probar el flujo hoy mismo. Los procesos que este comando
 * encuentra en un momento dado YA SON un lote completo de "hoy" o de "mañana"
 * (creados con test:seed-descargos-volumen en corridas separadas por día,
 * por persona) - todos los que aparezcan en una misma corrida de este
 * comando reciben la MISMA fecha, no se dividen entre sí.
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
        {--fecha= : Fecha (Y-m-d) para fecha_descargos_programada de TODOS los procesos encontrados. Por defecto, hoy}
        {--dry-run : Solo lista los procesos que se reenviarían, sin disparar nada}
        {--force : No pedir confirmación antes de reenviar}';

    protected $description = 'Reenvía la citación (PDF + correo, sin gastar cuota extra de IA) de los procesos de la empresa ficticia de pruebas QA, tras corregir el link roto por APP_URL.';

    public function handle(): int
    {
        $empresa = Empresa::where('nit', SeedDescargosVolumenTest::NIT_EMPRESA_TEST)->first();

        if (!$empresa) {
            $this->error('No existe la empresa ficticia de pruebas (NIT ' . SeedDescargosVolumenTest::NIT_EMPRESA_TEST . '). Nada que reenviar.');
            return self::FAILURE;
        }

        $procesos = ProcesoDisciplinario::with('trabajador')
            ->where('empresa_id', $empresa->id)
            ->get();

        if ($procesos->isEmpty()) {
            $this->warn('La empresa ficticia de pruebas no tiene procesos disciplinarios. Nada que reenviar.');
            return self::SUCCESS;
        }

        $fecha = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::today();

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

        if (!$this->option('force') && !$this->confirm("¿Actualizar la fecha de descargos a {$fecha->toDateString()} y reenviar la citación real (PDF + correo) a los {$procesos->count()} procesos listados arriba?")) {
            $this->comment('Cancelado.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($procesos->count());
        foreach ($procesos as $proceso) {
            $proceso->update([
                'fecha_descargos_programada' => $fecha,
                'hora_descargos_programada'  => '00:00:00',
            ]);
            GenerarYEnviarCitacionJob::dispatch($proceso, $proceso->abogado_id);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Se encolaron {$procesos->count()} reenvíos de citación.");
        $this->warn('Los jobs quedaron en la cola "gemini" - requiere `php artisan queue:work --queue=gemini` corriendo para procesarse.');

        return self::SUCCESS;
    }
}
