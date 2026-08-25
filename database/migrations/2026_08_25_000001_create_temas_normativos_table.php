<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temas_normativos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $ahora = now();
        $temas = [
            ['Jornada laboral y horas extras', 'Duración de la jornada, horas extras diurnas/nocturnas, trabajo suplementario, recargos por trabajo dominical o festivo.'],
            ['Descansos y vacaciones', 'Descanso dominical remunerado, días de descanso obligatorio, vacaciones anuales remuneradas y su liquidación.'],
            ['Salario y prestaciones sociales', 'Definición y pago del salario, viáticos, prima de servicios, cesantías, intereses a las cesantías, auxilio de transporte.'],
            ['Régimen disciplinario y sanciones', 'Procedimiento disciplinario interno, tipos de sanciones (llamado de atención, suspensión), debido proceso antes de sancionar.'],
            ['Faltas graves y justas causas de terminación', 'Catálogo de faltas graves del trabajador y del empleador, justas causas para terminar el contrato sin indemnización.'],
            ['Terminación del contrato de trabajo', 'Formas de terminación, preaviso, indemnización por despido sin justa causa, no renovación de contratos a término fijo.'],
            ['Seguridad y Salud en el Trabajo (SG-SST)', 'Obligaciones del Sistema de Gestión de Seguridad y Salud en el Trabajo, prevención de accidentes y enfermedades laborales.'],
            ['Acoso laboral y comité de convivencia', 'Prevención, modalidades y procedimiento de queja por acoso laboral (mobbing), funcionamiento del comité de convivencia laboral.'],
            ['Acoso sexual laboral', 'Prevención, definición y procedimiento de denuncia de acoso sexual en el entorno de trabajo.'],
            ['Protección a la mujer embarazada y lactancia', 'Fuero de maternidad, estabilidad laboral reforzada durante el embarazo, permisos de lactancia.'],
            ['Protección a personas con discapacidad', 'Estabilidad laboral reforzada por condición de salud o discapacidad, ajustes razonables en el puesto de trabajo.'],
            ['Fuero sindical y libertad sindical', 'Derecho de asociación sindical, protección de representantes sindicales, negociación colectiva.'],
            ['Teletrabajo y trabajo remoto/híbrido', 'Regulación del teletrabajo, trabajo en casa y modalidades remotas o híbridas de prestación del servicio.'],
            ['Menores de edad y aprendices SENA', 'Requisitos para contratar menores de edad, contrato de aprendizaje, cuota de aprendices.'],
            ['Período de prueba', 'Duración máxima del período de prueba, efectos y terminación durante ese período.'],
            ['Licencias (maternidad, paternidad, luto, calamidad)', 'Licencia de maternidad y paternidad, licencia por luto, permisos por calamidad doméstica.'],
            ['Seguridad social (EPS/pensión/ARL)', 'Afiliación y aportes a salud, pensión y riesgos laborales.'],
            ['SARLAFT', 'Sistema de Administración del Riesgo de Lavado de Activos y Financiación del Terrorismo aplicado a la relación laboral.'],
            ['Protección de datos personales', 'Tratamiento, autorización y protección de datos personales del trabajador (habeas data).'],
            ['Confidencialidad y propiedad intelectual', 'Cláusulas de confidencialidad, propiedad intelectual e industrial sobre desarrollos del trabajador.'],
            ['Discriminación e igualdad de trato', 'Prohibición de discriminación por género, raza, orientación sexual, religión u otra condición protegida.'],
            ['Uso de tecnología y redes sociales', 'Políticas de uso de correo, internet, equipos de cómputo y redes sociales dentro y fuera del horario laboral.'],
            ['Reforma laboral / normativa general nueva', 'Cambios estructurales amplios a la legislación laboral (ej. reformas al Código Sustantivo del Trabajo) que pueden afectar varios temas a la vez.'],
            ['COPASST', 'Comité Paritario de Seguridad y Salud en el Trabajo: conformación, funciones y periodicidad.'],
            ['Modalidades de contratación', 'Tipos de contrato de trabajo: término fijo, indefinido, obra o labor, ocasional o transitorio.'],
            ['Procedimiento de descargos', 'Citación a descargos, derecho de defensa del trabajador antes de una sanción o despido.'],
            ['Contratación y vinculación laboral', 'Requisitos de vinculación, documentos de ingreso, examen médico de ingreso, período de inducción.'],
        ];

        DB::table('temas_normativos')->insert(array_map(
            fn (array $tema) => [
                'nombre'      => $tema[0],
                'descripcion' => $tema[1],
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            $temas
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('temas_normativos');
    }
};
