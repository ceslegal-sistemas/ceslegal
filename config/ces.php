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
    | Disclaimer jurídico — Formulario de descargos (Ley 1581/2012, Art. 29 CN, Art. 115 CST)
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
    | URL de compra / suscripción del Reglamento Interno de Trabajo
    |--------------------------------------------------------------------------
    */
    'rit_purchase_url' => env('RIT_PURCHASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | PayU Colombia — pasarela de pagos
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
