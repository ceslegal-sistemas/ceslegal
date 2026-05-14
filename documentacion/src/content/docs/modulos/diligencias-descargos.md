---
title: Diligencias de Descargos
description: Modulo de gestion de diligencias de descargos con formulario publico, preguntas generadas por IA y temporizador
---

## Descripcion General

El modulo de **Diligencias de Descargos** gestiona la audiencia donde el trabajador presenta su version de los hechos. Es uno de los componentes mas complejos del sistema, ya que involucra:

- Creacion automatica de la diligencia al enviar la citacion.
- Token de acceso temporal con vencimiento de 6 dias.
- Formulario publico Livewire accesible por el trabajador sin autenticacion.
- Preguntas generadas por inteligencia artificial (Google Gemini).
- Temporizador de 45 minutos para completar el formulario.
- Subida de archivos de evidencia.
- Generacion automatica del acta de descargos.

La diligencia se crea automaticamente cuando el cliente envia la citacion desde el modulo de [Procesos Disciplinarios](/modulos/procesos-disciplinarios/). No se crea manualmente.

## Caracteristicas Principales

### Token de Acceso Temporal

Cuando se envia la citacion, se genera un token criptografico de 64 caracteres usando `bin2hex(random_bytes(32))`. Este token tiene las siguientes restricciones:

- **Vencimiento**: 6 dias desde la generacion.
- **Acceso por fecha**: Solo se puede acceder en la fecha programada para la diligencia.
- **Acceso habilitado**: Un flag booleano que permite desactivar el acceso manualmente.

```php
public function generarTokenAcceso(): string
{
    $this->token_acceso = bin2hex(random_bytes(32));
    $this->token_expira_en = now()->addDays(6);
    $this->save();
    return $this->token_acceso;
}

public function tokenEsValido(): bool
{
    if (!$this->token_acceso || !$this->token_expira_en) return false;
    if (now()->greaterThan($this->token_expira_en)) return false;
    if (!$this->acceso_habilitado) return false;
    return true;
}
```

### Preguntas del Formulario

Las preguntas se organizan en tres bloques:

**1. Preguntas iniciales estandar (10)**

Son preguntas fijas definidas en `IADescargoService::PREGUNTAS_INICIALES`:

1. "Va a asistir acompaniado(a) por alguien?"
2. "Que relacion tiene esa persona con usted?"
3. "Para que empresa trabaja usted?"
4. "Cual es su cargo en la empresa?"
5. "Que tareas realiza en ese cargo?"
6. "Conoce el reglamento interno de la empresa?"
7. "Quien es su jefe directo?"
8. "Usted cumple con las funciones de su cargo?"
9. "Sigue las instrucciones que le da su jefe?"
10. "Sabe por que fue citado(a) a estos descargos?"

**2. Preguntas generadas por IA (dinamicas)**

Basadas en los hechos del proceso, articulos legales y sanciones del reglamento incumplidas. Se generan al enviar la citacion (hasta 1 inicial) y durante la diligencia (hasta 1 por respuesta). El limite maximo total es **30 preguntas**.

Las preguntas de IA siguen principios de lenguaje claro:
- Oraciones cortas y directas
- Palabras sencillas, sin jerga juridica
- Neutrales y no sugestivas
- Relevantes para el caso

**3. Preguntas de cierre estandar (3)**

Definidas en `IADescargoService::PREGUNTAS_CIERRE`:

1. "Le aviso esta situacion a su jefe directo?"
2. "Ha estado antes en descargos?"
3. "Sabe que no cumplir con sus obligaciones de trabajo puede traerle sanciones?"

### Temporizador de 45 Minutos

Al acceder al formulario por primera vez, se inicia un timer de 45 minutos:

```php
public function iniciarTimer()
{
    if (!$this->primer_acceso_en) {
        $this->update([
            'primer_acceso_en' => Carbon::now('America/Bogota'),
            'tiempo_limite' => Carbon::now('America/Bogota')->addMinutes(45),
            'tiempo_expirado' => false,
        ]);
    }
}
```

- El timer se ejecuta en el frontend con JavaScript.
- Si el tiempo expira, el formulario se bloquea automaticamente.
- Las respuestas parciales se guardan.
- El metodo `tiempoRestante()` calcula los segundos restantes.

### Formulario Livewire

El formulario publico esta construido con Livewire 3 y presenta:

- Las preguntas una a una o en bloques.
- Campo de texto para cada respuesta con validacion de longitud minima (2 caracteres).
- Opcion de subir archivos de evidencia.
- Indicador visual del tiempo restante.
- Guardado automatico del progreso.

### Preguntas Dinamicas con IA

Cuando el trabajador responde una pregunta, el sistema puede generar automaticamente nuevas preguntas basadas en la respuesta:

```php
public function generarPreguntasDinamicas(
    PreguntaDescargo $preguntaRespondida,
    RespuestaDescargo $respuesta
): array {
    // Verifica limite maximo de 30 preguntas
    // Construye contexto con todas las preguntas y respuestas previas
    // Llama a Google Gemini para analizar la respuesta
    // Si detecta incongruencias o evasivas, genera nuevas preguntas
    // Maximo 1 pregunta dinamica por respuesta
}
```

La IA analiza:
- Inexactitudes en los argumentos del trabajador.
- Incongruencias entre respuestas.
- Evasivas o contradicciones.
- Relevancia para el caso disciplinario.

### Trazabilidad de IA

Cada llamada a la IA se registra en la tabla `trazabilidad_ia_descargos`:

| Campo | Descripcion |
|-------|-------------|
| `diligencia_descargo_id` | Diligencia asociada |
| `prompt_enviado` | Prompt completo enviado a la IA |
| `respuesta_recibida` | Respuesta recibida de la IA |
| `tipo` | Tipo de operacion (generacion_preguntas) |
| `metadata` | Provider, modelo, timestamp |

### Generacion del Acta de Descargos

Al completar la diligencia, el servicio `ActaDescargosService` genera automaticamente el acta que incluye:

- Datos del proceso y del trabajador.
- Fecha y modalidad de la diligencia.
- Todas las preguntas formuladas y sus respuestas.
- Evidencias aportadas.
- Observaciones del abogado.

## Modelo de Datos

### Tabla: `diligencias_descargos`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `proceso_id` | foreignId | Proceso disciplinario asociado |
| `fecha_diligencia` | datetime | Fecha programada de la diligencia |
| `lugar_diligencia` | string | Lugar (direccion o "virtual") |
| `lugar_especifico` | string | Lugar especifico dentro de la sede |
| `link_reunion` | string | Enlace de reunion virtual |
| `trabajador_asistio` | boolean | Si el trabajador asistio |
| `motivo_inasistencia` | text | Motivo si no asistio |
| `acompanante_nombre` | string | Nombre del acompanante |
| `acompanante_cargo` | string | Cargo del acompanante |
| `preguntas_formuladas` | json | Preguntas (legacy, ahora en tabla separada) |
| `respuestas` | json | Respuestas (legacy, ahora en tabla separada) |
| `pruebas_aportadas` | boolean | Si aporto pruebas |
| `descripcion_pruebas` | text | Descripcion de pruebas |
| `observaciones` | text | Observaciones del abogado |
| `acta_generada` | boolean | Si se genero el acta |
| `ruta_acta` | string | Ruta del archivo del acta |
| `archivos_evidencia` | json | Archivos subidos por el trabajador |
| `token_acceso` | string | Token de acceso temporal |
| `token_expira_en` | datetime | Fecha de vencimiento del token |
| `acceso_habilitado` | boolean | Si el acceso esta habilitado |
| `fecha_acceso_permitida` | date | Fecha en la que se permite acceder |
| `trabajador_accedio_en` | datetime | Fecha/hora del acceso del trabajador |
| `primer_acceso_en` | datetime | Primer acceso al formulario |
| `preguntas_completadas_en` | datetime | Primera vez que el trabajador llega a la seccion de feedback (aunque no finalice) |
| `tiempo_limite` | datetime | Hora limite para completar (45 min) |
| `tiempo_expirado` | boolean | Si el tiempo expiro |
| `ip_acceso` | string | IP desde donde accedio |

### Tabla: `preguntas_descargos`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `diligencia_descargo_id` | foreignId | Diligencia asociada |
| `pregunta` | text | Texto de la pregunta |
| `orden` | integer | Orden de presentacion |
| `es_generada_por_ia` | boolean | Si fue generada por IA |
| `pregunta_padre_id` | foreignId (nullable) | Pregunta que origino esta (para dinamicas) |
| `estado` | string | Estado: activa, respondida |

### Tabla: `respuestas_descargos`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `pregunta_descargo_id` | foreignId | Pregunta asociada |
| `respuesta` | text | Texto de la respuesta |
| `respondido_en` | datetime | Fecha/hora de la respuesta |
| `archivos_adjuntos` | json | Archivos adjuntos |

## Relaciones con Otros Modulos

| Relacion | Tipo | Desde | Hacia | Descripcion |
|----------|------|-------|-------|-------------|
| `proceso` | BelongsTo | DiligenciaDescargo | ProcesoDisciplinario | Proceso padre |
| `preguntas` | HasMany | DiligenciaDescargo | PreguntaDescargo | Preguntas formuladas |
| `trazabilidadIA` | HasMany | DiligenciaDescargo | TrazabilidadIADescargo | Registro de llamadas a IA |
| `respuesta` | HasOne | PreguntaDescargo | RespuestaDescargo | Respuesta del trabajador |
| `preguntaPadre` | BelongsTo | PreguntaDescargo | PreguntaDescargo | Pregunta que la origino |
| `preguntasHijas` | HasMany | PreguntaDescargo | PreguntaDescargo | Preguntas derivadas |

## Notas de Uso

### Flujo Completo de una Diligencia

```
1. Abogado envia citacion desde el proceso
2. Sistema crea DiligenciaDescargo con token (6 dias)
3. Sistema genera preguntas (estandar + IA + cierre)
4. Trabajador recibe correo con enlace (si es virtual)
5. Trabajador accede al formulario publico
6. Se inicia timer de 45 minutos
7. Trabajador responde preguntas
8. IA genera preguntas dinamicas segun respuestas
9. Trabajador puede subir evidencias
10. Al completar, se genera acta automaticamente
11. Estado del proceso cambia a descargos_realizados
12. Se notifica al abogado y al cliente
```

### Validacion del Acceso

El sistema valida tres condiciones antes de permitir acceso:

1. **Token valido**: No vencido y acceso habilitado.
2. **Fecha correcta**: Solo se permite acceder en la fecha programada.
3. **Tiempo no expirado**: Los 45 minutos no han transcurrido.

### Zona Horaria

Todos los tiempos se manejan en zona horaria `America/Bogota` (UTC-5):

```php
'primer_acceso_en' => Carbon::now('America/Bogota'),
'tiempo_limite' => Carbon::now('America/Bogota')->addMinutes(45),
```

### Proveedores de IA Soportados

El servicio `IADescargoService` soporta multiples proveedores configurables:

| Proveedor | Modelo | Configuracion |
|-----------|--------|---------------|
| Google Gemini | Configurable | `services.ia.gemini` |
| OpenAI | Configurable | `services.ia.openai` |
| Anthropic | Configurable | `services.ia.anthropic` |

El proveedor activo se configura en `services.ia.provider`.

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Modulo principal
- [Sanciones](/modulos/sanciones/) - Emision de sanciones
- [Documentos](/modulos/documentos/) - Generacion de documentos
