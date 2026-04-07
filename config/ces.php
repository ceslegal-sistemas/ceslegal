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
            'nombre'           => 'Básico',
            'trial_dias'       => 7,
            'precio_mensual_cop'  => (int) env('PRECIO_BASICO_MENSUAL', 29000),
            'precio_anual_cop'    => (int) env('PRECIO_BASICO_ANUAL',   295800),
        ],
        'pro' => [
            'nombre'           => 'Pro',
            'trial_dias'       => 0,
            'precio_mensual_cop'  => (int) env('PRECIO_PRO_MENSUAL', 59000),
            'precio_anual_cop'    => (int) env('PRECIO_PRO_ANUAL',   601800),
        ],
        'firma' => [
            'nombre'           => 'Firma',
            'trial_dias'       => 0,
            'precio_mensual_cop'  => (int) env('PRECIO_FIRMA_MENSUAL', 99000),
            'precio_anual_cop'    => (int) env('PRECIO_FIRMA_ANUAL',   1009800),
        ],
    ],
];
