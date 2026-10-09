<?php

/*
 * Precios de planes: ajustar en .env o directamente aquí.
 * Fórmula anual: precio_mensual_cop × 12 × 0.85 (15% descuento)
 *
 * Básico   $29.000/mes   → anual $295.800
 * Pro      $59.000/mes   → anual $601.800
 * Firma    $99.000/mes   → anual $1.009.800
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Disclaimer jurídico - Formulario de descargos (Ley 1581/2012, Art. 29 CN, Art. 115 CST)
    |--------------------------------------------------------------------------
    | Plantilla con marcadores: :nombre :cedula :empresa
    | Se reemplazan en FormularioDescargos.php con los datos del trabajador/empresa.
    */
    'disclaimer_descargos' => 'AUTORIZACIÓN DE DATOS PERSONALES Y DECLARACIÓN DE IDENTIDAD

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

    /*
    |--------------------------------------------------------------------------
    | Disclaimer jurídico - Autorizador/citante de la empresa (Ley 1581/2012)
    |--------------------------------------------------------------------------
    | Mismo texto que antes vivía hardcodeado en webcam-autorizador.blade.php
    | (reusado en Emitir Sanción, Crear Proceso Disciplinario y Aceptación de
    | RIT Mejorado) - centralizado 2026-10-03 tras la auditoría de disclaimers.
    | Sin marcadores: el autorizador no se identifica por nombre en este texto.
    */
    'disclaimer_autorizador' => 'AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES: Esta diligencia se realizará a través de medios digitales, electrónicos y/o virtuales, por lo cual autorizo que mi dirección IP, la fecha y hora exactas de cada acción, el canal de verificación utilizado, las fotografías tomadas en el desarrollo de la diligencia y en general el tratamiento de mis datos personales sean tratados conforme a la Ley 1581 de 2012 y demás normas que la adicionen, modifiquen y/o complementen.',

    /*
    |--------------------------------------------------------------------------
    | Declaraciones del trabajador en la socialización del RIT (2 fases)
    |--------------------------------------------------------------------------
    | Plantillas con marcador: :empresa. Centralizadas 2026-10-03 (antes
    | hardcodeadas en socializacion-rit.blade.php). Deliberadamente distintas:
    | Fase 1 (Publicación) NUNCA dice "entendí" (Andrés Sarmiento, reunión
    | 2026-09-29: "no me importa si lo entendió, solo que vea la publicación"),
    | Fase 2 (Socialización) sí exige la declaración fuerte de comprensión.
    */
    // Marcadores **negrita** (mismo convenio ya usado para renderizar el
    // texto del RIT, ver mi-reglamento-interno.blade.php/socializacion-rit.blade.php:
    // preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '<strong>$1</strong>', ...)).
    'disclaimer_rit_publicacion' => '**Declaro que fui informado(a)** de la publicación del Reglamento Interno de Trabajo de **:empresa**. Si tengo alguna objeción, la haré directamente ante la empresa.',

    'disclaimer_rit_socializacion' => '**Declaro que leí y entendí** el Reglamento Interno de Trabajo de **:empresa**.',

    /*
    |--------------------------------------------------------------------------
    | Declaración del admin al "Culminar Socialización del RIT" (manual)
    |--------------------------------------------------------------------------
    | Botón manual pedido por Andrés Sarmiento (reunión 2026-10-03): el admin
    | cierra el proceso de socialización declarando bajo su responsabilidad
    | que ya notificó a todos los trabajadores. Plantilla con marcador: :empresa.
    */
    'disclaimer_culminacion_socializacion' => 'Declaro, bajo mi responsabilidad, que **notifiqué a todos los trabajadores** de **:empresa** sobre el Reglamento Interno de Trabajo vigente, que la **socialización fue aceptada y realizada**, y que **conservaré las evidencias** correspondientes a disposición de las autoridades competentes.',

    /*
    |--------------------------------------------------------------------------
    | Escalamiento a RRHH si el trabajador insiste en "no entendí" el RIT
    |--------------------------------------------------------------------------
    | Pedido de Andrés Sarmiento (reunión 2026-10-03). Plantilla con
    | marcador: :empresa. Primer aviso = advertencia suave, sigue en el
    | proceso. Segundo aviso = bloqueo definitivo + correo a RRHH.
    */
    'aviso_no_comprendio_primera_vez' => 'Ya te hemos explicado el Reglamento Interno de Trabajo de :empresa de forma didáctica: con un video, un resumen en lenguaje sencillo y el documento completo con los cambios resaltados. Te recomendamos repasar ese material antes de continuar.',

    'mensaje_no_comprendio_bloqueado' => "Estimado(a) :trabajador,\n\nLe informamos que el día :fecha se le hizo entrega del video y del documento digital con la actualización del Reglamento Interno de Trabajo de la empresa :empresa, en cumplimiento de la normativa vigente.\n\nCon el fin de garantizar su comprensión y resolver cualquier duda específica, lo(a) invitamos a radicar por escrito sus inquietudes o los puntos específicos que no comprende ante el área de Talento Humano de su empresa, o a solicitar una breve sesión aclaratoria con dicha área.\n\nAgradecemos su colaboración para dar por cerrado este proceso de divulgación obligatoria.",

    /*
    |--------------------------------------------------------------------------
    | Confirmación intermedia antes del 2do "no entendí" (pedido de Andrés
    | Sarmiento, reunión 2026-10-05): evita que un clic accidental dispare
    | el correo a RRHH - el trabajador debe confirmar de forma consciente.
    |--------------------------------------------------------------------------
    */
    'aviso_no_comprendio_confirmacion' => 'Ya te entregamos, de manera didáctica, un video, un resumen en lenguaje sencillo y el Reglamento Interno de Trabajo completo de :empresa con los cambios resaltados. Al confirmar, estás indicando que, a pesar de ese material, definitivamente no comprendes el Reglamento. Esto es necesario para continuar tu relación laboral, por lo que tu caso será remitido al área de Recursos Humanos de tu empresa. Si en realidad sí entendiste, selecciona "Cancelar" y continúa con el proceso.',

    /*
    |--------------------------------------------------------------------------
    | URL de compra / suscripción del Reglamento Interno de Trabajo
    |--------------------------------------------------------------------------
    */
    'rit_purchase_url' => env('RIT_PURCHASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | PayU Colombia - pasarela de pagos
    |--------------------------------------------------------------------------
    */
    'payu' => [
        'api_key'     => env('PAYU_API_KEY', ''),
        'api_login'   => env('PAYU_API_LOGIN', ''),
        'merchant_id' => env('PAYU_MERCHANT_ID', ''),
        'account_id'  => env('PAYU_ACCOUNT_ID', ''),
        'sandbox'     => env('PAYU_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Planes de suscripción
    |--------------------------------------------------------------------------
    | precio_anual_cop = precio_mensual_cop × 12 × 0.85 (15 % dto. anual)
    */
    'planes' => [
        'basico' => [
            'nombre'             => 'Básico',
            'trial_dias'         => 7,
            'precio_mensual_cop' => (int) env('PRECIO_BASICO_MENSUAL', 29000),
            'precio_anual_cop'   => (int) env('PRECIO_BASICO_ANUAL',   295800),
            'incluye_rit'        => true,
        ],
        'pro' => [
            'nombre'             => 'Pro',
            'trial_dias'         => 0,
            'precio_mensual_cop' => (int) env('PRECIO_PRO_MENSUAL', 59000),
            'precio_anual_cop'   => (int) env('PRECIO_PRO_ANUAL',   601800),
            'incluye_rit'        => true,
        ],
        'firma' => [
            'nombre'             => 'Firma',
            'trial_dias'         => 0,
            'precio_mensual_cop' => (int) env('PRECIO_FIRMA_MENSUAL', 99000),
            'precio_anual_cop'   => (int) env('PRECIO_FIRMA_ANUAL',   1009800),
            'incluye_rit'        => true,
        ],
    ],
];
