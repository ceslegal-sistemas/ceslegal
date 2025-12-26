# Cómo Usar Google Gemini API

## ¿Por qué Gemini?

Google Gemini es una excelente alternativa a OpenAI y Anthropic porque:

- ✅ **Gratis**: Ofrece un generoso plan gratuito
- ✅ **Rápido**: Gemini 1.5 Flash es muy veloz
- ✅ **Potente**: Gemini 1.5 Pro compite con GPT-4
- ✅ **Fácil de configurar**: Solo necesitas una API key
- ✅ **Sin límites de rate**: Más permisivo que OpenAI en el plan gratuito

## Paso 1: Obtener tu API Key de Gemini

1. Ve a [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Inicia sesión con tu cuenta de Google
3. Haz click en "Get API Key"
4. Haz click en "Create API Key"
5. Copia la API key generada

## Paso 2: Configurar en tu Proyecto

Edita el archivo `.env` y agrega tu API key:

```env
IA_PROVIDER=gemini
GEMINI_API_KEY=tu-api-key-aqui
```

¡Eso es todo! El sistema ya está configurado para usar Gemini.

## Modelos Disponibles

Puedes cambiar el modelo en `.env`:

### Gemini 1.5 Flash (Recomendado)
- **Modelo**: `gemini-1.5-flash`
- **Velocidad**: Muy rápido
- **Costo**: Gratis (hasta 15 requests/minuto)
- **Uso recomendado**: Producción diaria

```env
GEMINI_MODEL=gemini-1.5-flash
```

### Gemini 1.5 Pro
- **Modelo**: `gemini-1.5-pro`
- **Velocidad**: Moderado
- **Costo**: Gratis (hasta 2 requests/minuto)
- **Uso recomendado**: Análisis complejos

```env
GEMINI_MODEL=gemini-1.5-pro
```

### Gemini 1.0 Pro
- **Modelo**: `gemini-1.0-pro`
- **Velocidad**: Rápido
- **Costo**: Gratis (hasta 60 requests/minuto)
- **Uso recomendado**: Desarrollo/testing

```env
GEMINI_MODEL=gemini-1.0-pro
```

## Configuración Completa en .env

```env
# Usar Gemini como proveedor principal
IA_PROVIDER=gemini

# Google Gemini
GEMINI_API_KEY=AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
GEMINI_MODEL=gemini-1.5-flash
GEMINI_MAX_TOKENS=1000
```

## Límites del Plan Gratuito

| Modelo | Requests/Minuto | Requests/Día | Tokens/Minuto |
|--------|-----------------|--------------|---------------|
| Gemini 1.5 Flash | 15 | 1,500 | 1,000,000 |
| Gemini 1.5 Pro | 2 | 50 | 32,000 |
| Gemini 1.0 Pro | 60 | - | 120,000 |

Para tu caso de uso (generar 5 preguntas por proceso):
- **Gemini 1.5 Flash**: Hasta 1,500 procesos por día GRATIS
- **Gemini 1.5 Pro**: Hasta 50 procesos por día GRATIS

## Comparación con Otros Proveedores

| Característica | Gemini Flash | OpenAI GPT-4 | Anthropic Claude |
|----------------|--------------|--------------|------------------|
| Costo (plan gratuito) | Gratis | $0.03/1K tokens | $0.015/1K tokens |
| Velocidad | Muy rápido | Moderado | Rápido |
| Requests/min (gratis) | 15 | 0 (requiere pago) | 5 |
| Calidad | Excelente | Excelente | Excelente |
| Contexto | 1M tokens | 128K tokens | 200K tokens |

## Cambiar entre Proveedores

Puedes cambiar de proveedor fácilmente en `.env`:

### Para usar Gemini:
```env
IA_PROVIDER=gemini
GEMINI_API_KEY=tu-key-aqui
```

### Para usar OpenAI:
```env
IA_PROVIDER=openai
OPENAI_API_KEY=tu-key-aqui
```

### Para usar Anthropic (Claude):
```env
IA_PROVIDER=anthropic
ANTHROPIC_API_KEY=tu-key-aqui
```

## Verificar que Funciona

1. Configura tu API key en `.env`
2. Ve a **Descargos** en Filament
3. Selecciona una diligencia sin preguntas
4. Click en **"Generar Preguntas IA"**
5. Deberías ver una notificación: "5 preguntas generadas con IA"

O al enviar una citación desde **Procesos Disciplinarios**, el sistema automáticamente generará las preguntas con Gemini.

## Solución de Problemas

### Error: "API key inválida"

**Causa**: La API key no es correcta o no está activada

**Solución**:
1. Ve a [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Verifica que la API key esté activa
3. Copia y pega de nuevo en `.env`
4. Limpia caché: `php artisan config:clear`

### Error: "Límite de rate excedido"

**Causa**: Has superado los requests por minuto

**Solución**:
1. Espera 1 minuto
2. Si es recurrente, considera cambiar a `gemini-1.0-pro` (60 rpm)
3. O implementa un sistema de cola

### Error: "Respuesta sin contenido válido"

**Causa**: Gemini bloqueó la respuesta por filtros de seguridad

**Solución**:
1. Verifica que los hechos del proceso no contengan contenido inapropiado
2. Revisa los logs en `storage/logs/laravel.log`
3. Prueba con otro modelo (gemini-1.5-pro es menos restrictivo)

## Recursos Adicionales

- [Documentación oficial de Gemini API](https://ai.google.dev/docs)
- [Google AI Studio](https://aistudio.google.com/)
- [Precios de Gemini](https://ai.google.dev/pricing)
- [Límites de rate](https://ai.google.dev/gemini-api/docs/quota-limits)

## Recomendación Final

Para tu caso de uso (sistema de descargos disciplinarios):

**Recomendamos Gemini 1.5 Flash porque**:
- Es completamente gratis
- Es muy rápido (respuesta en 1-2 segundos)
- Tiene un límite generoso (1,500 procesos/día)
- Produce resultados de alta calidad
- No requiere tarjeta de crédito

**Configuración recomendada**:
```env
IA_PROVIDER=gemini
GEMINI_API_KEY=tu-key-aqui
GEMINI_MODEL=gemini-1.5-flash
GEMINI_MAX_TOKENS=1000
```

¡Listo! Ahora tu sistema generará preguntas inteligentes usando Google Gemini completamente gratis.
