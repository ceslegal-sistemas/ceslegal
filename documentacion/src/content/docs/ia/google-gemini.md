---
title: Google Gemini
description: Integración de CES Legal con la API de Google Gemini para funcionalidades de Inteligencia Artificial
---

## Descripcion General

CES Legal utiliza **Google Gemini** (modelo `gemini-2.5-flash`) como proveedor principal de inteligencia artificial para asistir en los procesos disciplinarios laborales. La integracion permite:

- **Generacion de preguntas** para diligencias de descargos.
- **Analisis de sanciones** basado en los hechos, descargos e historial del trabajador.
- **Seguimiento de preguntas dinamicas** segun las respuestas del trabajador.

La arquitectura de servicios es flexible: el sistema soporta multiples proveedores de IA (OpenAI, Anthropic, Gemini) y puede cambiar entre ellos mediante configuracion, sin modificar codigo fuente.

---

## Configuracion

### Variables de entorno

```env
IA_PROVIDER=gemini
GEMINI_API_KEY=tu_api_key_de_google
GEMINI_MODEL=gemini-2.5-flash
GEMINI_MAX_TOKENS=2048
```

### Archivo de configuracion

La configuracion se encuentra en `config/services.php` bajo la clave `ia`:

```php
// config/services.php
'ia' => [
    'provider' => env('IA_PROVIDER', 'gemini'),

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
    ],
],
```

Los servicios de IA resuelven el proveedor activo en tiempo de ejecucion:

```php
$this->provider = config('services.ia.provider', 'openai');
$this->config = config("services.ia.{$this->provider}", []);
```

---

## Endpoint de la API

La comunicacion con Google Gemini se realiza mediante la API REST de **Generative Language**:

```
POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={apiKey}
```

| Componente | Valor |
|------------|-------|
| **Host** | `generativelanguage.googleapis.com` |
| **Version** | `v1beta` |
| **Accion** | `generateContent` |
| **Autenticacion** | API key como query parameter |

---

## Parametros de generacion

Los parametros enviados a Gemini controlan el comportamiento y la calidad de las respuestas:

| Parametro | Valor | Descripcion |
|-----------|-------|-------------|
| `temperature` | `0.7` (preguntas) / `0.3` (sanciones) | Controla la creatividad de la respuesta. Valores mas bajos producen respuestas mas deterministas. |
| `topP` | `0.95` | Nucleus sampling. Considera tokens cuya probabilidad acumulada alcanza el 95%. |
| `topK` | `40` | Limita la seleccion a los 40 tokens mas probables (usado en analisis de sanciones). |
| `maxOutputTokens` | `2048` (configurable, hasta `8192`) | Numero maximo de tokens en la respuesta generada. |

### Ejemplo de payload enviado

```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "Eres un abogado laboral experto en procesos disciplinarios en Colombia..."
        }
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.7,
    "maxOutputTokens": 2048,
    "topP": 0.95,
    "topK": 40
  }
}
```

### Ejemplo de respuesta recibida

```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "PREGUNTA_1: ¿Sabia que debia hacer eso?\nPREGUNTA_2: ..."
          }
        ]
      },
      "finishReason": "STOP"
    }
  ]
}
```

---

## Como funciona la llamada a Gemini

El flujo completo de una llamada a la API de Gemini dentro de CES Legal sigue estos pasos:

```
1. El servicio (IADescargoService o IAAnalisisSancionService) construye el prompt
2. Se resuelve el proveedor configurado: config('services.ia.provider')
3. Se obtiene la API key y modelo: config('services.ia.gemini')
4. Se construye la URL del endpoint con el modelo y la API key
5. Se envia una peticion HTTP POST con el prompt y generationConfig
6. Se valida que la respuesta contenga candidates[0].content.parts[0].text
7. Se verifica el finishReason para detectar truncamiento
8. Se registra la trazabilidad de la llamada
9. Se parsea la respuesta y se retorna al flujo de negocio
```

### Implementacion en codigo

```php
protected function llamarGemini(string $prompt): string
{
    $apiKey = $this->config['api_key'];
    $model = $this->config['model'];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->timeout(30)->post($url, [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => "Eres un abogado laboral experto en procesos disciplinarios en Colombia. "
                                . "Respondes de forma concisa y profesional pero entendible para cualquier persona.\n\n"
                                . $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => $this->config['max_tokens'],
            'topP' => 0.95,
        ],
    ]);

    if (!$response->successful()) {
        throw new \Exception("Error en API Gemini: " . $response->body());
    }

    $responseData = $response->json();

    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new \Exception("Respuesta de Gemini sin contenido valido");
    }

    return $responseData['candidates'][0]['content']['parts'][0]['text'];
}
```

---

## Manejo de errores

### Errores HTTP

Si la respuesta de la API no es exitosa (`!$response->successful()`), se lanza una excepcion con el cuerpo de la respuesta. Los servicios que llaman a Gemini capturan esta excepcion y:

1. Registran el error en el log de Laravel.
2. Retornan un resultado vacio o valores por defecto, segun el contexto.
3. No interrumpen el flujo del usuario.

```php
try {
    $respuestaIA = $this->llamarIA($prompt);
    // Procesar respuesta...
} catch (\Exception $e) {
    Log::error('Error al generar preguntas dinamicas con IA', [
        'pregunta_id' => $preguntaRespondida->id,
        'error' => $e->getMessage(),
    ]);
    return [];
}
```

### Respuesta sin contenido valido

Si la respuesta de Gemini no contiene la estructura esperada (`candidates[0].content.parts[0].text`), se lanza una excepcion con el mensaje `"Respuesta de Gemini sin contenido valido"`.

### Deteccion de respuestas truncadas

Cuando `finishReason` es `MAX_TOKENS`, significa que la respuesta fue cortada por alcanzar el limite de tokens. El sistema registra un warning en el log:

```php
$finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';

if ($finishReason === 'MAX_TOKENS') {
    Log::warning('Respuesta de Gemini truncada por limite de tokens', [
        'finish_reason' => $finishReason,
        'max_tokens' => $this->config['max_tokens'],
        'respuesta_parcial' => substr($responseData['candidates'][0]['content']['parts'][0]['text'], 0, 200),
    ]);
}
```

### Timeout

Las peticiones HTTP tienen un timeout de **30 segundos** para la generacion de preguntas y **60 segundos** para el analisis de sanciones. Si se excede el timeout, Laravel HTTP Client lanza una excepcion que es capturada por el bloque `try/catch`.

---

## Rate Limiting y consideraciones

### Limites de la API de Google

Google Gemini aplica limites de uso segun el plan contratado:

| Plan | Solicitudes por minuto (RPM) | Tokens por minuto (TPM) |
|------|------------------------------|-------------------------|
| **Gratuito** | 15 RPM | 1,000,000 TPM |
| **Pay-as-you-go** | 1,000 RPM | 4,000,000 TPM |

### Consideraciones en CES Legal

- **Uso tipico**: Cada proceso disciplinario genera entre 1 y 3 llamadas a la API (preguntas iniciales, preguntas dinamicas, analisis de sancion).
- **No se implementa rate limiting del lado del cliente**: El volumen actual de procesos no requiere control de tasa. Si el sistema escala, se recomienda implementar un middleware o queue.
- **Costos**: El modelo `gemini-2.5-flash` es de bajo costo. Se recomienda monitorear el consumo desde la consola de Google Cloud.
- **Reintentos**: Actualmente no se implementan reintentos automaticos. Si la llamada falla, el sistema registra el error y continua sin IA.
- **Fallback**: En caso de error, `IAAnalisisSancionService` retorna opciones por defecto que permiten al abogado tomar la decision manualmente.

---

## Proveedores alternativos

El sistema esta preparado para cambiar de proveedor modificando unicamente la variable de entorno `IA_PROVIDER`:

| Proveedor | Variable | Modelo por defecto |
|-----------|----------|--------------------|
| **Gemini** | `IA_PROVIDER=gemini` | `gemini-2.5-flash` |
| **OpenAI** | `IA_PROVIDER=openai` | `gpt-4` |
| **Anthropic** | `IA_PROVIDER=anthropic` | `claude-3-5-sonnet-20241022` |

Cada proveedor tiene su propio metodo de llamada (`llamarGemini`, `llamarOpenAI`, `llamarAnthropic`) dentro de `IADescargoService`, y el metodo `llamarIA` actua como dispatcher:

```php
protected function llamarIA(string $prompt): string
{
    if ($this->provider === 'gemini') {
        return $this->llamarGemini($prompt);
    }
    if ($this->provider === 'openai') {
        return $this->llamarOpenAI($prompt);
    }
    if ($this->provider === 'anthropic') {
        return $this->llamarAnthropic($prompt);
    }
    throw new \Exception("Proveedor de IA no soportado: {$this->provider}");
}
```

---

## Archivos relacionados

| Archivo | Descripcion |
|---------|-------------|
| `config/services.php` | Configuracion de proveedores de IA |
| `app/Services/IADescargoService.php` | Servicio de generacion de preguntas |
| `app/Services/IAAnalisisSancionService.php` | Servicio de analisis de sanciones |
| `app/Models/TrazabilidadIADescargo.php` | Modelo de trazabilidad de llamadas IA |

## Proximos pasos

- [Generacion de Preguntas](/ia/generacion-preguntas/) - Como se generan las preguntas con IA
- [Analisis de Sanciones](/ia/analisis-sanciones/) - Como la IA sugiere sanciones
- [Trazabilidad](/ia/trazabilidad/) - Registro de auditoría de llamadas a IA
