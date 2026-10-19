<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'rekognition' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI'),
    ],

    'elevenlabs' => [
        'api_key'  => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'pNInz6obpgDQGcFmaJgB'),
    ],

    'whatsapp' => [
        'phone_number_id'      => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id'  => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token'         => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'ces_legal_whatsapp'),
        'api_version'          => env('WHATSAPP_API_VERSION', 'v20.0'),
        // App Secret de la app de Meta (Configuración básica de la app en
        // developers.facebook.com) - NO es el access token. Se usa solo
        // para verificar la firma X-Hub-Signature-256 del webhook y
        // confirmar que un evento realmente vino de Meta.
        'app_secret'           => env('WHATSAPP_APP_SECRET'),
        // Nombre de la plantilla Authentication aprobada por Meta para el
        // OTP - configurable porque si Meta exige resometerla con otro
        // nombre, se cambia sin tocar código.
        'plantilla_otp'        => env('WHATSAPP_PLANTILLA_OTP', 'otp_verificacion'),
    ],

    // Ruta de prueba de carga /__cap-test (oculta). Apagada salvo en el entorno de
    // pruebas: CAP_TEST_ENABLED=true + CAP_TEST_TOKEN=<secreto>  → optimize:clear.
    'cap_test' => [
        'enabled' => env('CAP_TEST_ENABLED', false),
        'token'   => env('CAP_TEST_TOKEN', ''),
    ],

    // n8n consulta este contexto por HTTP (no al revés - ver rutas/web.php,
    // AsistentePanelContextoController) desde el workflow que atiende el
    // widget de Chatwoot embebido en el panel 'empresa'.
    'asistente_panel' => [
        'secret' => env('ASISTENTE_PANEL_SECRET'),
    ],

    'chatwoot' => [
        'website_token' => env('CHATWOOT_WEBSITE_TOKEN'),
        'base_url' => env('CHATWOOT_BASE_URL', 'https://contactenos.ceslegal.co'),
    ],

    'ia' => [
        'provider' => env('IA_PROVIDER', 'gemini'),
        // Registro opt-in de tokens de Gemini (para el reporte de costos).
        // Activar con IA_TOKEN_LOG=true y luego `php artisan optimize:clear`.
        'token_log' => env('IA_TOKEN_LOG', false),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 2048),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2048),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 2048),
            // Modelo de video (2026-09-29) - probado y confirmado accesible con esta
            // cuenta (curl directo a /v1beta/interactions, respuesta "completed").
            // Consume ordenes de magnitud mas tokens que el modelo de texto - ver
            // RitVideoDidacticoService, uso deliberadamente manual (nunca automatico).
            'model_video' => env('GEMINI_MODEL_VIDEO', 'gemini-omni-1.1-flash'),
            // 360p por defecto para mantener el costo bajo mientras se mide el gasto
            // real en https://ai.studio/spend (pedido explicito del usuario,
            // 2026-09-29) - subir a 720p/1080p es un simple cambio de env, sin tocar
            // codigo, una vez se confirme que el costo es aceptable.
            'video_resolution' => env('GEMINI_VIDEO_RESOLUTION', '360p'),

            // Rediseño 2026-10-14 (guion previo + profundidad): el guion se
            // escribe con un modelo de TEXTO antes de gastar en video - por
            // defecto el mismo modelo de texto de arriba (GEMINI_MODEL), pero
            // puede fijarse aparte si conviene uno distinto para el guion.
            'model_guion' => env('GEMINI_MODEL_GUION'),
            // Palanca de costo MAS directa de este flujo: cada clip extra es
            // una llamada de video pagada aparte (ver RitVideoDidacticoService).
            // Un RIT con varios temas de importancia 3 puede acercarse a este
            // tope - bajarlo reduce el gasto maximo por RIT a costa de menos
            // profundidad en los temas criticos.
            'video_max_clips' => env('GEMINI_VIDEO_MAX_CLIPS', 60),
            // Palabras objetivo por clip de ~10s de narracion hablada.
            'video_palabras_clip' => env('GEMINI_VIDEO_PALABRAS_CLIP', 24),
            // video_presentador/video_escenario (ficha fija del presentador y el
            // escenario, repetida en todos los clips de una serie) NO se listan
            // aqui a proposito: RitVideoDidacticoService las resuelve con
            // config('services.ia.gemini.video_presentador', self::FICHA_...) -
            // ese fallback solo se activa si la CLAVE no existe en este arreglo.
            // Si se agregan aqui con env() sin valor, env() devuelve null, la
            // clave SI existe (con valor null) y el fallback del codigo deja de
            // aplicarse - el prompt terminaria con "null" en vez de la ficha
            // real. Para personalizarlas, usar las env GEMINI_VIDEO_PRESENTADOR/
            // GEMINI_VIDEO_ESCENARIO solo si TAMBIEN se les pone aqui un valor
            // por defecto no nulo.
        ],
    ],

];
