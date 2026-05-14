<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_textos', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->string('grupo', 50)->default('general')->index();
            $table->string('descripcion', 255);
            $table->longText('valor');
            $table->timestamps();
        });

        // Semilla inicial — disclaimer de descargos
        DB::table('configuraciones_textos')->insert([
            'clave'       => 'disclaimer_descargos',
            'grupo'       => 'descargos',
            'descripcion' => 'Texto de autorización de datos y declaración de identidad que lee y acepta el trabajador antes de iniciar la diligencia de descargos.',
            'valor'       => 'AUTORIZACIÓN DE DATOS PERSONALES Y DECLARACIÓN DE IDENTIDAD

Yo, :nombre, declaro bajo la gravedad del juramento lo siguiente:

1. IDENTIDAD: Soy :nombre, la persona citada a esta diligencia de descargos, identificada con la cédula de ciudadanía N.º :cedula, en la cual participé libre, voluntaria y conscientemente.

2. VERACIDAD: Declaro que la información suministrada en la presente diligencia de descargos es veraz, completa y corresponde fielmente a los hechos por los cuales se me cita.

3. CAPACIDAD: Asisto a esta diligencia de manera libre, voluntaria y consciente.

4. DEBIDO PROCESO: He tenido la oportunidad de ejercer mi derecho de defensa, contradicción y doble instancia.

5. CONOCIMIENTO PREVIO: Declaro conocer integralmente el Reglamento Interno de Trabajo de la empresa :empresa, el cual fue debidamente socializado por el Empleador, por lo que reconozco su contenido, alcance y obligatoriedad.

6. AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES: Esta diligencia de descargos se realizará a través de medios digitales, electrónicos y/o virtuales, por lo cual autorizo que mi dirección IP, la fecha y hora exactas de cada acción, el canal de verificación utilizado, las fotografías tomadas en el desarrollo de la diligencia y en general el tratamiento de mis datos personales sean tratados conforme a la Ley 1581 de 2012 y demás normas que la adicionen, modifiquen y/o complementen.

7. ADVERTENCIA DE LEGALIDAD: Cualquier manifestación falsa, inexacta o engañosa, así como la suplantación de mi identidad durante el desarrollo de esta diligencia, podrá acarrear consecuencias adversas de carácter legal, disciplinario y/o penal, de conformidad con lo dispuesto en la legislación colombiana y las normas internas del empleador.

Esta declaración hace parte integral del acta de descargos y tendrá valor probatorio en caso de controversia. Declaro que actúo en nombre propio y que la información registrada corresponde a mi identidad y voluntad.

Al marcar la casilla de aceptación manifiesto haber leído, entendido y aceptado el contenido del mismo en su integralidad.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Placeholder para términos y condiciones (futuro)
        DB::table('configuraciones_textos')->insert([
            'clave'       => 'terminos_condiciones',
            'grupo'       => 'legal',
            'descripcion' => 'Términos y condiciones generales de uso de la plataforma CES Legal.',
            'valor'       => 'Términos y condiciones pendientes de redacción.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_textos');
    }
};
