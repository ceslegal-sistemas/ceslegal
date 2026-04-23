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
    | Disclaimer jurídico — Formulario de descargos (Art. 29 CN, Art. 115 CST)
    |--------------------------------------------------------------------------
    | Este texto se muestra al trabajador antes de diligenciar el formulario.
    | Puede ser personalizado por la empresa. Reemplazar con texto definitivo.
    */
    'disclaimer_descargos' => env('CES_DISCLAIMER_DESCARGOS', <<<'TEXT'
DECLARACIÓN DE IDENTIDAD Y GARANTÍAS PROCESALES

Yo, quien diligenció sus datos en el sistema, declaro bajo la gravedad del juramento:

1. IDENTIDAD: Soy la persona citada a esta diligencia de descargos, identificada con el número de cédula de ciudadanía registrado en el sistema, y estoy participando voluntaria y conscientemente en este proceso.

2. DERECHO DE DEFENSA (Art. 29 Constitución Política): Conozco que la Constitución me garantiza el derecho a ser oído, a presentar pruebas y a controvertir las que se alleguen en mi contra. Esta diligencia es precisamente el mecanismo para ejercer ese derecho.

3. PRESUNCIÓN DE INOCENCIA: Entiendo que soy inocente mientras no se me pruebe lo contrario mediante un proceso legalmente conducido, y que las respuestas que brinde serán evaluadas con criterio objetivo.

4. DERECHO A LOS DESCARGOS (Art. 115 Código Sustantivo del Trabajo): Conozco que la ley colombiana exige que antes de imponer una sanción disciplinaria se me debe conceder la oportunidad de ser escuchado. Esta es dicha oportunidad.

5. VERACIDAD: Entiendo que proporcionar información falsa o actuar en nombre de otra persona puede constituir el delito de fraude procesal tipificado en el artículo 453 del Código Penal colombiano, cuya pena puede ser de 4 a 8 años de prisión.

6. CONSENTIMIENTO DE EVIDENCIA DIGITAL: Acepto que, como medida de seguridad y para garantizar la integridad del proceso, el sistema registrará: mi dirección IP, la fecha y hora exactas de cada acción, el canal de verificación utilizado, y las fotografías tomadas al inicio y al final de esta diligencia.

Esta declaración hace parte integral del acta de descargos y tendrá valor probatorio en caso de controversia.
TEXT),

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
