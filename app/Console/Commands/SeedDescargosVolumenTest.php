<?php

namespace App\Console\Commands;

use App\Jobs\GenerarYEnviarCitacionJob;
use App\Models\Empresa;
use App\Models\ProcesoDisciplinario;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prueba de volumen end-to-end (2026-08-18, pedido explícito del usuario):
 * crea procesos disciplinarios ficticios sobre una empresa/trabajadores
 * ficticios, distribuidos entre 3 correos reales para monitoreo, y dispara
 * el pipeline REAL de citación (IA + PDF + correo) para cada uno.
 *
 * IMPORTANTE - esto NO entrena ni le da "memoria" a la IA: el RAG de este
 * sistema se ancla en textos legales (articulos_legales, jurisprudencia),
 * nunca en el historial de casos procesados. Esto es solo una prueba de
 * carga/QA del sistema, confirmado explícitamente con el usuario.
 *
 * Riesgos ya advertidos y aceptados por el usuario: (a) los 3 correos
 * reales indicados SÍ recibirán citaciones reales de descargos por hechos
 * ficticios, (b) el volumen de llamadas a Gemini consume la MISMA cuota
 * compartida con producción real.
 *
 * Idempotente en la empresa/RIT ficticios (firstOrCreate) - se puede correr
 * varias veces (ej. "hoy" y "mañana" con la mitad de cada lote) sin
 * duplicar la empresa/RIT base.
 */
class SeedDescargosVolumenTest extends Command
{
    protected $signature = 'test:seed-descargos-volumen
        {--lorena=0 : Cantidad de casos con correo a admin@ceslegal.co}
        {--william=0 : Cantidad de casos con correo a wleal@ceslegal.co}
        {--yo=0 : Cantidad de casos con correo a superadmin@ceslegal.co}
        {--sin-citacion : Crea los procesos pero NO dispara GenerarYEnviarCitacionJob (sin IA ni correo)}';

    protected $description = 'Prueba de volumen end-to-end: crea procesos disciplinarios ficticios distribuidos entre 3 correos reales y dispara la citación real (IA + PDF + correo) para cada uno.';

    /** Público para que ReenviarCitacionesVolumenTest use el mismo identificador de la empresa ficticia. */
    public const NIT_EMPRESA_TEST = '900000001-1';

    /** @var array<int, array{cargo: string, hecho: string}> */
    private const ESCENARIOS = [
        ['cargo' => 'Auxiliar de Bodega', 'hecho' => 'incumplimiento reiterado del horario de ingreso a la jornada laboral, presentando retardos no justificados en los registros de control de acceso'],
        ['cargo' => 'Operario de Producción', 'hecho' => 'ausentismo injustificado durante la jornada laboral sin previo aviso ni autorización del superior inmediato'],
        ['cargo' => 'Auxiliar Administrativo', 'hecho' => 'uso indebido de los recursos informáticos de la empresa para fines ajenos a sus funciones durante el horario laboral'],
        ['cargo' => 'Vendedor', 'hecho' => 'incumplimiento de los protocolos de atención al cliente establecidos en el reglamento interno, generando quejas reiteradas'],
        ['cargo' => 'Cajero', 'hecho' => 'inconsistencias reiteradas en el manejo y cuadre de caja al finalizar el turno, sin la debida justificación'],
        ['cargo' => 'Conductor', 'hecho' => 'uso del vehículo de la empresa para fines personales no autorizados fuera del horario y funciones asignadas'],
        ['cargo' => 'Auxiliar de Mantenimiento', 'hecho' => 'negligencia en la revisión de los equipos asignados, ocasionando un daño material evitable'],
        ['cargo' => 'Recepcionista', 'hecho' => 'abandono del puesto de trabajo en varias ocasiones sin autorización durante la jornada laboral'],
        ['cargo' => 'Asistente de Logística', 'hecho' => 'incumplimiento de los procedimientos de registro de inventario, generando diferencias no justificadas'],
        ['cargo' => 'Técnico de Soporte', 'hecho' => 'desacato a instrucciones directas impartidas por el supervisor en relación con la atención de tickets prioritarios'],
        ['cargo' => 'Auxiliar Contable', 'hecho' => 'retraso reiterado en la entrega de reportes financieros dentro de los plazos internos establecidos'],
        ['cargo' => 'Supervisor de Turno', 'hecho' => 'incumplimiento de los protocolos de seguridad industrial durante la operación del área a su cargo'],
        ['cargo' => 'Auxiliar de Servicios Generales', 'hecho' => 'conducta inapropiada con un compañero de trabajo durante la jornada laboral, según reporte de testigos'],
        ['cargo' => 'Mensajero', 'hecho' => 'incumplimiento de las rutas y tiempos de entrega asignados sin justificación válida'],
        ['cargo' => 'Auxiliar de Calidad', 'hecho' => 'omisión en el reporte de no conformidades detectadas durante el proceso de inspección asignado'],
    ];

    private const NOMBRES = ['Carlos', 'María', 'Andrés', 'Luisa', 'Jorge', 'Camila', 'Diego', 'Valentina', 'Felipe', 'Daniela', 'Santiago', 'Paula', 'Julián', 'Natalia', 'Sebastián', 'Alejandra', 'Mateo', 'Isabella', 'Nicolás', 'Sofía'];
    private const APELLIDOS = ['Gómez', 'Rodríguez', 'Martínez', 'López', 'García', 'Hernández', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Rojas', 'Vargas', 'Castro', 'Ortiz', 'Moreno'];

    public function handle(): int
    {
        $lorena  = (int) $this->option('lorena');
        $william = (int) $this->option('william');
        $yo      = (int) $this->option('yo');
        $sinCitacion = (bool) $this->option('sin-citacion');

        if ($lorena + $william + $yo === 0) {
            $this->error('Debe indicar al menos --lorena=N, --william=N o --yo=N.');
            return self::FAILURE;
        }

        $empresa = $this->obtenerOCrearEmpresaTest();
        $this->obtenerOCrearRitTest($empresa);
        $abogado = User::where('role', 'abogado')->first();

        $lotes = [
            ['nombre' => 'Lorena Conde', 'email' => 'admin@ceslegal.co', 'cantidad' => $lorena],
            ['nombre' => 'William Leal', 'email' => 'wleal@ceslegal.co', 'cantidad' => $william],
            ['nombre' => 'Usted', 'email' => 'superadmin@ceslegal.co', 'cantidad' => $yo],
        ];

        $totalCreados = 0;
        $escenarioIdx = 0;

        foreach ($lotes as $lote) {
            if ($lote['cantidad'] <= 0) {
                continue;
            }

            $this->info("Creando {$lote['cantidad']} casos para {$lote['nombre']} ({$lote['email']})...");
            $bar = $this->output->createProgressBar($lote['cantidad']);

            for ($i = 0; $i < $lote['cantidad']; $i++) {
                $escenario = self::ESCENARIOS[$escenarioIdx % count(self::ESCENARIOS)];
                $escenarioIdx++;

                $trabajador = $this->crearTrabajadorTest($empresa, $escenario['cargo'], $lote['email']);
                $proceso    = $this->crearProcesoTest($empresa, $trabajador, $abogado, $escenario['hecho']);

                if (!$sinCitacion) {
                    GenerarYEnviarCitacionJob::dispatch($proceso, $abogado?->id);
                }

                $totalCreados++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        }

        $this->info("Total de procesos creados: {$totalCreados}");
        if ($sinCitacion) {
            $this->warn('--sin-citacion activo: NO se disparó GenerarYEnviarCitacionJob (sin IA, sin correos).');
        } else {
            $this->warn('Los jobs de citación quedaron en la cola "gemini" - requiere `php artisan queue:work --queue=gemini` corriendo para procesarse.');
        }

        return self::SUCCESS;
    }

    private function obtenerOCrearEmpresaTest(): Empresa
    {
        return Empresa::firstOrCreate(
            ['nit' => self::NIT_EMPRESA_TEST],
            [
                'razon_social'         => 'DISTRIBUIDORA QA DE PRUEBAS',
                'tipo_societario'      => 'S.A.S.',
                'direccion'            => 'Calle Ficticia 00-00',
                'telefono'             => '3000000000',
                'email_contacto'       => 'contacto-qa-test@example.com',
                'ciudad'               => 'Bogotá',
                'departamento'         => 'Cundinamarca',
                'representante_legal'  => 'Representante QA de Pruebas',
                'active'               => true,
                'dias_habiles'         => [1, 2, 3, 4, 5],
                'numero_empleados'     => 50,
            ]
        );
    }

    private function obtenerOCrearRitTest(Empresa $empresa): ReglamentoInterno
    {
        $existente = ReglamentoInterno::where('empresa_id', $empresa->id)->where('activo', true)->first();
        if ($existente) {
            return $existente;
        }

        ReglamentoInterno::where('empresa_id', $empresa->id)->update(['activo' => false]);

        return ReglamentoInterno::create([
            'empresa_id'         => $empresa->id,
            'nombre'             => 'RIT-QA-TEST — Reglamento Interno de Trabajo (empresa ficticia de pruebas)',
            'texto_completo'     => $this->textoRitTest(),
            'activo'             => true,
            'fuente'             => 'subido',
            'estado_generacion'  => 'completado',
        ]);
    }

    private function textoRitTest(): string
    {
        return <<<RIT
        REGLAMENTO INTERNO DE TRABAJO — DISTRIBUIDORA QA DE PRUEBAS (DOCUMENTO FICTICIO DE PRUEBAS)

        CAPÍTULO I. DISPOSICIONES GENERALES
        El presente Reglamento Interno de Trabajo regula las relaciones laborales entre DISTRIBUIDORA QA DE PRUEBAS y sus trabajadores, de conformidad con el Código Sustantivo del Trabajo.

        CAPÍTULO II. OBLIGACIONES DEL TRABAJADOR
        ARTÍCULO 1. Son obligaciones especiales del trabajador: cumplir puntualmente el horario de trabajo establecido, ejecutar personalmente las labores asignadas, cuidar los bienes y recursos de la empresa, observar las normas de seguridad industrial, y guardar rigurosa reserva sobre la información confidencial de la empresa.

        CAPÍTULO III. PROHIBICIONES AL TRABAJADOR
        ARTÍCULO 2. Se prohíbe al trabajador: abandonar el puesto de trabajo sin autorización, hacer uso indebido de los bienes y recursos de la empresa para fines personales, presentar retardos o inasistencias injustificadas, desacatar las órdenes e instrucciones impartidas por sus superiores, y adoptar conductas irrespetuosas con sus compañeros de trabajo.

        CAPÍTULO IV. PROCEDIMIENTO DISCIPLINARIO
        ARTÍCULO 3. Ante el presunto incumplimiento de las obligaciones o prohibiciones establecidas, la empresa citará al trabajador a una diligencia de descargos, garantizando su derecho de defensa y contradicción, previa a la aplicación de cualquier sanción disciplinaria.

        CAPÍTULO V. TABLA DE FALTAS Y SANCIONES
        ARTÍCULO 4. Las faltas se clasifican en leves, graves y muy graves:
        - Faltas leves (ej. retardos ocasionales no justificados): llamado de atención escrito.
        - Faltas graves (ej. ausentismo injustificado reiterado, uso indebido de recursos de la empresa, incumplimiento de protocolos de seguridad): suspensión del contrato de trabajo hasta por ocho (8) días.
        - Faltas muy graves (ej. abandono reiterado del puesto de trabajo, desacato reiterado a instrucciones directas, conducta irrespetuosa grave con compañeros de trabajo): terminación del contrato de trabajo con justa causa, conforme al Artículo 62 del CST.

        CAPÍTULO VI. DISPOSICIONES FINALES
        ARTÍCULO 5. El presente reglamento entra en vigencia a partir de su publicación y es de obligatorio cumplimiento para todos los trabajadores de la empresa.
        RIT;
    }

    private function crearTrabajadorTest(Empresa $empresa, string $cargo, string $emailNotificacion): Trabajador
    {
        $nombre   = self::NOMBRES[array_rand(self::NOMBRES)];
        $apellido = self::APELLIDOS[array_rand(self::APELLIDOS)];
        $sufijo   = strtoupper(substr(uniqid(), -5));

        return Trabajador::create([
            'empresa_id'      => $empresa->id,
            'tipo_documento'  => 'CC',
            'numero_documento' => (string) random_int(1000000000, 1099999999),
            'nombres'         => "{$nombre} (QA-TEST-{$sufijo})",
            'apellidos'       => $apellido,
            'cargo'           => $cargo,
            'fecha_ingreso'   => now()->subMonths(random_int(2, 36)),
            'email'           => $emailNotificacion,
            'active'          => true,
        ]);
    }

    private function crearProcesoTest(Empresa $empresa, Trabajador $trabajador, ?User $abogado, string $hecho): ProcesoDisciplinario
    {
        $fechaOcurrencia = now()->subDays(random_int(2, 10));
        $fechaDescargos  = now()->addDays(random_int(3, 8));
        while ($fechaDescargos->isWeekend()) {
            $fechaDescargos->addDay();
        }

        $proceso = ProcesoDisciplinario::create([
            'empresa_id'                  => $empresa->id,
            'trabajador_id'               => $trabajador->id,
            'abogado_id'                  => $abogado?->id,
            'hechos'                      => "Según los registros internos de la empresa, el trabajador {$trabajador->nombre_completo}, quien se desempeña en el cargo de {$trabajador->cargo}, presuntamente incurrió en {$hecho}, situación que se registró el " . $fechaOcurrencia->translatedFormat('d \d\e F \d\e Y') . '.',
            'fecha_ocurrencia'            => $fechaOcurrencia,
            'modalidad_descargos'         => 'virtual',
            'fecha_descargos_programada'  => $fechaDescargos,
            'hora_descargos_programada'   => '10:00:00',
            'citante_nombre'              => 'Sistema de Pruebas QA',
            'citante_cargo'               => 'Automatización de Pruebas',
        ]);

        $fotoPath = $this->guardarFotoPlaceholder("fotos-verificacion/citacion/{$proceso->id}");
        if ($fotoPath) {
            $proceso->update(['foto_citante_path' => $fotoPath, 'foto_citante_en' => now()]);
        }

        return $proceso;
    }

    /** Mismo patrón que HasVerificacionFotografica::guardarFotoVerificacion() - imagen placeholder 1x1, sin depender de un webcam real. */
    private function guardarFotoPlaceholder(string $directorio): ?string
    {
        $base64Pixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $imageData   = base64_decode($base64Pixel);

        $filename = uniqid() . '.png';
        Storage::disk('public')->makeDirectory($directorio);
        Storage::disk('public')->put("{$directorio}/{$filename}", $imageData);

        return "{$directorio}/{$filename}";
    }
}
