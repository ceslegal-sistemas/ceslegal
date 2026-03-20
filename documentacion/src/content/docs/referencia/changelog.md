---
title: Changelog
description: Historial de cambios y versiones del sistema CES Legal
---

Todos los cambios notables del proyecto CES Legal se documentan en esta pagina. El formato esta basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el proyecto utiliza [Versionado Semantico](https://semver.org/lang/es/).

---

## [1.2.0] - 2026-03-20

### Cambiado

#### Feedback organico del trabajador (paso a paso)

- Las preguntas de feedback del trabajador ya no son un modal separado con estrellas; ahora aparecen **una a una** despues de responder todas las preguntas del formulario de descargos, con el mismo estilo y botones "Guardar y continuar".
- Se agregaron 5 preguntas especificas en lugar del antiguo campo de calificacion + sugerencia: experiencia general, algo confuso, que cambiaria, claridad de preguntas, completar sin ayuda.
- La barra de progreso del header incluye los 5 pasos de feedback en el conteo total, para que el avance se vea continuo.
- El sistema registra `preguntas_completadas_en` en `diligencias_descargos` la primera vez que el trabajador llega a la seccion de feedback, aunque no la finalice.
- El feedback se guarda al presionar "Enviar Descargos" (no en paso separado). Si el trabajador no responde ninguna pregunta, no se crea ningun registro.

#### Preguntas del trigger `primer_proceso` corregidas

- Las preguntas del formulario automatico del primer proceso (trigger `primer_proceso`) se actualizaron para reflejar los campos reales implementados: calificacion, dificultad en el proceso, facilidad de la citacion, mejora sugerida y si pudo completar sin ayuda.

### Corregido

- **Columna "Respondio" en `/admin/feedbacks`**: ya no muestra "Trabajador anonimo" para feedbacks de tipo `descargo_trabajador`. Ahora busca el nombre del trabajador a traves de la relacion `diligenciaDescargo → proceso → trabajador` con fallback a `procesoDisciplinario → trabajador`.

---

## [1.1.0] - 2026-03-18

### Agregado

#### Modulo de Feedback mejorado
- **NPS (Net Promoter Score)**: campo `nps_score` (0–10) con categorias automaticas: Promotor (9-10), Neutro (7-8), Detractor (0-6).
- **Triggers de contexto**: el sistema detecta el momento adecuado para solicitar feedback segun 4 disparadores: `primer_proceso` (primera vez del usuario), `post_diligencia` (despues de descargos realizados), `periodico` (cada 14 dias) y `hito` (cada 5 procesos completados).
- **Respuestas adicionales por trigger**: cada tipo de feedback incluye preguntas especificas guardadas como JSON en `respuestas_adicionales`.
- **Widget de estadisticas mejorado**: muestra calificacion promedio con grafico de distribucion, conteo separado de Trabajadores vs Clientes, NPS calculado (promotores - detractores) y porcentaje con comentarios.

### Cambiado

#### Feedback obligatorio al 100%
- Todos los campos del formulario de feedback son ahora obligatorios, incluyendo el campo de comentarios/sugerencias.
- El formulario del trabajador (`formulario-descargos`) deshabilita el boton de envio hasta que se complete tanto la calificacion como el comentario.
- Campos anteriormente opcionales convertidos a requeridos: `calidad_acta`, `sugerencia` (post_diligencia), `funcionalidad_faltante` (periodico), `sugerencia` (hito).

#### Reorganizacion del recurso Feedback
- Tabs de listado rediseñados: de filtros por calificacion (Excelentes/Buenos/Regulares) a filtros por tipo de respondente (**Trabajadores** / **Clientes** / Con comentario / Con NPS / Negativos), con conteo en cada tab.
- Nueva columna **"Respondio"**: muestra el nombre real del trabajador (via `diligenciaDescargo → proceso → trabajador`) o del usuario admin segun el tipo de feedback.
- Columna NPS con badge de color (verde Promotor, amarillo Neutro, rojo Detractor).
- Badge de tipo actualizado: "Admin" renombrado a **"Cliente"** para mayor claridad.
- Vista de detalle (`ViewFeedback`) reorganizada con secciones: quien respondio, calificacion + NPS lado a lado, comentario, respuestas adicionales (renderizadas como preguntas/respuestas), metadatos tecnicos.

---

## [1.0.0] - 2026-01-27

### Agregado

#### Gestion de Procesos Disciplinarios
- CRUD completo de procesos disciplinarios con formulario multipaso.
- Maquina de estados automatizada con transiciones validadas: `solicitud_recibida` -> `apertura_proceso` -> `citacion_descargos` -> `descargos_pendientes` -> `descargos_realizados` / `descargos_no_realizados` -> `analisis_pruebas` -> `decision_sancion` -> `notificacion_decision` -> `cierre_proceso`.
- Asignacion de abogados responsables con control de disponibilidad.
- Timeline completo de auditoria con registro de cada cambio de estado.
- Codigo unico auto-generado por proceso (formato `PD-YYYY-NNN`).
- Soporte para multiples tipos de evidencia (documentos, imagenes, audios).

#### Integracion con Inteligencia Artificial
- Integracion con **Google Gemini** (modelo `gemini-2.5-flash`) para generacion automatica de preguntas de descargos.
- Generacion de preguntas dinamicas basadas en los hechos, articulos incumplidos y respuestas previas del trabajador.
- Analisis juridico con recomendacion de tipo de sancion asistido por IA.
- Soporte configurable para proveedor de IA (`IA_PROVIDER`: `openai` / `gemini`).
- Trazabilidad completa de todas las interacciones con la IA (prompts, respuestas, tokens consumidos).

#### Generacion de Documentos
- Generacion de citaciones a descargos en formato PDF y Word (.docx).
- Generacion de actas de diligencia de descargos.
- Generacion de documentos de sancion.
- Interpolacion de variables dinamicas en plantillas (datos del trabajador, empresa, proceso).
- Conversion de Word a PDF mediante LibreOffice (headless) con fallback automatico a dompdf.

#### Notificaciones y Correo Electronico
- Envio de notificaciones por correo electronico via SMTP (Gmail).
- Tracking de apertura de correos con pixel de seguimiento.
- Notificaciones en tiempo real dentro del panel de administracion.
- Alertas automaticas por terminos legales proximos a vencer.
- Alertas de terminos legales vencidos.

#### Diligencia de Descargos
- Programacion de diligencias con multiples modalidades: presencial, virtual y telefonica.
- Formulario publico para el trabajador con token temporal de acceso (vigencia de 6 dias).
- Temporizador de 45 minutos para completar los descargos.
- Preguntas iniciales generadas por IA y preguntas dinamicas de seguimiento.
- Registro completo de respuestas con marca de tiempo.

#### Arquitectura Multi-tenant
- Aislamiento de datos por empresa mediante `empresa_id` en todos los modelos principales.
- Global Scopes para filtrado automatico de datos segun la empresa del usuario autenticado.
- Politicas (Policies) con validacion de pertenencia a empresa.

#### Roles y Permisos
- Integracion con **Filament Shield** para gestion granular de permisos.
- Tres roles predefinidos: **Administrador**, **Abogado** y **Cliente (RRHH)**.
- Permisos por recurso: ver, crear, editar, eliminar y acciones personalizadas.
- Panel de gestion de roles con matriz de permisos visual.

#### Tutorial Interactivo (Onboarding)
- Tour de bienvenida interactivo para nuevos usuarios.
- Guias contextuales por modulo (Procesos, Trabajadores, Descargos).
- Senalizacion visual de botones y acciones clave.

#### Catalogos y Datos Maestros
- Catalogo de **63 tipos de sancion laboral** colombiana precargados.
- Catalogo de **dias no habiles** de Colombia (festivos nacionales) actualizable por ano.
- Catalogo de tipos de contrato laboral.
- Catalogo de cargos y areas organizacionales.

#### Automatizacion
- Comando `procesos:actualizar-estados-descargos` ejecutado cada 5 minutos para deteccion automatica de descargos completados y vencidos.
- Comando `terminos:actualizar` ejecutado diariamente a las 8:00 AM para control de terminos legales.
- Scheduler de Laravel configurado con proteccion contra ejecucion superpuesta.

#### Infraestructura Tecnica
- Construido con **Laravel 12** y **Filament 3.2**.
- Base de datos **MySQL** con migraciones versionadas.
- Sistema de colas con driver `database` para procesamiento asincrono.
- Sesiones y cache en base de datos.
- Compilacion de assets con **Vite**.
- Zona horaria configurada para **America/Bogota**.

---

## Convenciones de Versionado

- **MAJOR** (X.0.0): Cambios incompatibles en la API o reestructuracion significativa.
- **MINOR** (0.X.0): Nueva funcionalidad compatible con versiones anteriores.
- **PATCH** (0.0.X): Correcciones de errores compatibles con versiones anteriores.

## Tipos de Cambios

- **Agregado** - para funcionalidad nueva.
- **Cambiado** - para cambios en funcionalidad existente.
- **Obsoleto** - para funcionalidad que sera removida proximamente.
- **Removido** - para funcionalidad removida.
- **Corregido** - para correcciones de errores.
- **Seguridad** - para correcciones de vulnerabilidades.

---

## Proximos Pasos

- [Introduccion](/inicio/introduccion/) - Conoce CES Legal
- [Variables de Entorno](/referencia/variables-entorno/) - Configuracion del sistema
- [Troubleshooting](/referencia/troubleshooting/) - Solucion de problemas comunes
