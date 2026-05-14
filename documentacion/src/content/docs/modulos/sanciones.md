---
title: Sanciones
description: Modulo de emision y gestion de sanciones disciplinarias con analisis y generacion documental asistidos por IA
---

## Descripcion General

El modulo de **Sanciones** gestiona la decision disciplinaria que se aplica a un trabajador tras la evaluacion del proceso. CES Legal integra inteligencia artificial en dos momentos clave: el analisis de la sancion apropiada (`IAAnalisisSancionService`) y la generacion del documento de sancion con lenguaje claro (`DocumentGeneratorService`).

La sancion se emite desde el modulo de [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) una vez que la diligencia de descargos ha sido completada (o el trabajador no se presento). El proceso completo es atomico: se ejecuta dentro de una transaccion de base de datos para garantizar que, si falla algun paso, no quede en estado inconsistente.

## Caracteristicas Principales

### Tipos de Sancion

CES Legal soporta tres tipos de sancion conforme al Codigo Sustantivo del Trabajo colombiano:

| Tipo                    | Codigo             | Descripcion                                          |
| ----------------------- | ------------------ | ---------------------------------------------------- |
| **Llamado de Atencion** | `llamado_atencion` | Amonestacion escrita por faltas leves                |
| **Suspension**          | `suspension`       | Suspension del contrato sin remuneracion (1-60 dias) |
| **Terminacion**         | `terminacion`      | Terminacion del contrato de trabajo con justa causa  |

### Analisis con IA (IAAnalisisSancionService)

Al emitir la sancion, la IA realiza un analisis automatizado que evalua:

**Entrada del analisis:**

- Hechos del caso actual
- Sanciones del reglamento interno incumplidas
- Descargos del trabajador (preguntas y respuestas)
- Historial de procesos disciplinarios previos del trabajador

**Salida del analisis (JSON):**

```json
{
    "gravedad": "leve|grave",
    "nivel_gravedad": "ninguno|bajo|alto",
    "es_reincidencia": true,
    "justificacion": "Explicacion de la clasificacion",
    "sanciones_disponibles": ["llamado_atencion", "suspension"],
    "sancion_recomendada": "suspension",
    "dias_suspension_sugeridos": [1, 2, 3, 5, 8],
    "razonamiento_legal": "Basado en el CST y reglamento",
    "consideraciones_especiales": "Historial, atenuantes, agravantes"
}
```

**Criterios de clasificacion:**

| Gravedad | Nivel   | Sanciones Disponibles            | Dias de Suspension |
| -------- | ------- | -------------------------------- | ------------------ |
| Leve     | Ninguno | Solo llamado de atencion         | N/A                |
| Grave    | Bajo    | Llamado de atencion o suspension | 1-8 dias           |
| Grave    | Alto    | Suspension o terminacion         | 8-60 dias          |

**Factores evaluados:**

- Reincidencia (cantidad de procesos previos).
- Gravedad de los hechos segun el CST.
- Calidad de los descargos presentados.
- Antecedentes disciplinarios.
- Circunstancias atenuantes o agravantes.

### Generacion de Documento de Sancion

El documento se genera con IA usando principios de **lenguaje claro** definidos en el prompt del `DocumentGeneratorService`:

- Oraciones cortas (maximo 25 palabras).
- Voz activa ("decidimos" en vez de "fue decidido").
- Palabras simples, sin jerga legal.
- Trato directo al trabajador ("usted").
- Sin frases como "por medio de la presente".

**Estructura del documento:**

1. Encabezado con datos de la empresa
2. Datos del destinatario (trabajador)
3. Asunto
4. Hechos que motivaron la decision
5. Por que estos hechos son importantes
6. Resumen de los descargos del trabajador
7. Decision tomada
8. Consecuencias practicas
9. Base legal y sanciones del reglamento
10. Derechos de impugnacion (3 dias habiles)
11. Firma del representante legal

### Proceso Completo de Emision

El metodo `generarYEnviarSancion()` ejecuta atomicamente:

```
1. Genera documento HTML con IA (Google Gemini)
2. Convierte HTML a PDF (LibreOffice o Dompdf)
3. Guarda el documento en tabla `documentos`
4. Crea/actualiza registro en tabla `sanciones`
5. Envia correo al trabajador con PDF adjunto y pixel tracking
6. Actualiza estado del proceso a `sancion_emitida`
7. Registra en timeline (documento generado + notificacion)
```

Si cualquier paso falla, la transaccion hace rollback completo.

### Manejo de Trabajadores sin Descargos

Si el trabajador no respondio al formulario de descargos, el documento de sancion incluye automaticamente:

- Mencion explicita de que se envio la citacion.
- Se le brindo la oportunidad de presentar su version.
- No ejercio su derecho de defensa.
- Se garantizo el debido proceso.

### Sanciones Laborales del Reglamento

El sistema utiliza un catalogo de sanciones laborales (`SancionLaboral`) que representan las infracciones tipificadas en el reglamento interno de trabajo de cada empresa:

| Campo                 | Descripcion                               |
| --------------------- | ----------------------------------------- |
| `tipo_falta`          | leve o grave                              |
| `nombre_claro`        | Nombre legible de la falta                |
| `descripcion`         | Descripcion detallada                     |
| `tipo_sancion`        | llamado_atencion, suspension, terminacion |
| `dias_suspension_min` | Minimo de dias de suspension              |
| `dias_suspension_max` | Maximo de dias de suspension              |
| `activa`              | Si esta activa en el sistema              |
| `orden`               | Orden de presentacion                     |

## Modelo de Datos

### Tabla: `sanciones`

| Campo                           | Tipo      | Descripcion                               |
| ------------------------------- | --------- | ----------------------------------------- |
| `id`                            | bigint    | Identificador unico                       |
| `proceso_id`                    | foreignId | Proceso disciplinario asociado            |
| `tipo_sancion`                  | string    | llamado_atencion, suspension, terminacion |
| `dias_suspension`               | integer   | Dias de suspension (si aplica)            |
| `fecha_inicio_suspension`       | date      | Inicio de la suspension                   |
| `fecha_fin_suspension`          | date      | Fin de la suspension                      |
| `motivo_sancion`                | text      | Motivo (hechos del proceso)               |
| `fundamento_legal`              | text      | Fundamento legal y reglamentario          |
| `observaciones`                 | text      | Observaciones adicionales                 |
| `documento_generado`            | boolean   | Si se genero el documento                 |
| `ruta_documento`                | string    | Ruta del documento PDF                    |
| `fecha_notificacion_rrhh`       | datetime  | Fecha de notificacion a RRHH              |
| `fecha_notificacion_trabajador` | datetime  | Fecha de notificacion al trabajador       |
| `notificado_por`                | foreignId | Usuario que notifico                      |

### Tabla: `analisis_juridicos`

| Campo                      | Tipo      | Descripcion                      |
| -------------------------- | --------- | -------------------------------- |
| `id`                       | bigint    | Identificador unico              |
| `proceso_id`               | foreignId | Proceso disciplinario asociado   |
| `abogado_id`               | foreignId | Abogado que solicito el analisis |
| `fecha_analisis`           | datetime  | Fecha del analisis               |
| `analisis_hechos`          | text      | Analisis de los hechos           |
| `analisis_pruebas`         | text      | Analisis de las pruebas          |
| `analisis_normativo`       | text      | Analisis normativo               |
| `conclusion`               | text      | Conclusion del analisis          |
| `recomendacion`            | text      | Recomendacion de sancion         |
| `tipo_sancion_recomendada` | string    | Tipo de sancion recomendada      |
| `fundamento_legal`         | text      | Fundamento legal                 |
| `observaciones`            | text      | Observaciones adicionales        |

## Relaciones con Otros Modulos

| Relacion      | Tipo      | Modelo               | Descripcion                              |
| ------------- | --------- | -------------------- | ---------------------------------------- |
| `proceso`     | BelongsTo | ProcesoDisciplinario | Proceso disciplinario padre              |
| `impugnacion` | HasOne    | Impugnacion          | Impugnacion presentada contra la sancion |

### Relaciones Indirectas

- **Documento**: El documento PDF de la sancion se almacena en la tabla `documentos` como relacion polimorfica del proceso.
- **Email Tracking**: El correo de notificacion de sancion tiene un registro de tracking que permite saber si fue leido.
- **Timeline**: La emision de la sancion se registra en el timeline del proceso.

## Notas de Uso

### Permisos por Rol

- **super_admin**: Puede emitir sanciones en cualquier proceso.
- **abogado**: Puede emitir sanciones en los procesos asignados. Puede solicitar analisis con IA.
- **cliente**: Puede emitir sanciones directamente. Recibe notificacion cuando se emite una sancion en un proceso de su empresa.

### Conversion a PDF

El documento de sancion se genera como HTML y se convierte a PDF:

1. **LibreOffice (preferido)**: Conversion de alta calidad usando `soffice --headless`.
2. **Dompdf (fallback)**: Si LibreOffice no esta disponible, usa la libreria Dompdf de PHP.
3. **HTML (ultimo recurso)**: Si ambos fallan, se mantiene como HTML.

### Impugnacion

Tras recibir la sancion, el trabajador tiene **3 dias habiles** para presentar una impugnacion. Si impugna:

- El cliente crea el registro de impugnacion.
- El proceso pasa a estado `impugnacion_realizada`.
- El cliente puede resolver la impugnacion mediante analisis de IA y cerrar el proceso.

### Temperatura de la IA

El servicio de analisis usa una **temperatura de 0.3** (baja) para respuestas mas consistentes y predecibles. El servicio de generacion de documentos usa una **temperatura de 0.7** para permitir mayor naturalidad en la redaccion.

### Fallback en Caso de Error

Si la IA no puede completar el analisis, el sistema retorna opciones por defecto que incluyen todos los tipos de sancion disponibles, con una nota indicando que se requiere revision manual.

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Modulo principal
- [Documentos](/modulos/documentos/) - Generacion de documentos
- [Notificaciones](/modulos/notificaciones/) - Sistema de notificaciones
