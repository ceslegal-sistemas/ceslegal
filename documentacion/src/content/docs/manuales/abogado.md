---
title: Manual del Abogado
description: Guia completa para el usuario con rol abogado en CES Legal
---

## Descripcion del Rol

El **Abogado** (rol `abogado`) es el profesional juridico encargado de gestionar los procesos disciplinarios asignados. Su trabajo incluye la revision de casos, programacion de diligencias de descargos, generacion de preguntas con asistencia de IA, emision de sanciones y generacion de documentos legales.

### Capacidades del Abogado

- Ver y gestionar procesos disciplinarios asignados
- Programar y realizar diligencias de descargos (virtual)
- Generar preguntas con asistencia de Inteligencia Artificial (Google Gemini)
- Revisar y aprobar preguntas generadas por IA
- Generar documentos legales (citaciones, actas, sanciones)
- Enviar citaciones y notificaciones por correo electronico
- Gestionar disponibilidad de horarios para diligencias
- Monitorear estado de lectura de correos enviados
- Analizar sanciones con asistencia de IA

---

## Acceso al Sistema

### Paso 1: Iniciar Sesion

1. Abra el navegador y acceda a: `https://su-dominio.com/admin`
2. Ingrese su correo electronico y contrasena
3. Haga clic en **Iniciar Sesion**

<!-- ![Login del abogado](/screenshots/abogado-login.png) -->

### Paso 2: Dashboard

Al ingresar, vera el dashboard con:
- Resumen de procesos asignados por estado
- Lista de procesos pendientes que requieren atencion
- Calendario de proximas diligencias programadas

<!-- ![Dashboard del abogado](/screenshots/abogado-dashboard.png) -->

---

## Gestion de Procesos Disciplinarios

### Ver Procesos Asignados

1. Haga clic en **Historial de Descargos** en el menu lateral
2. Vera la tabla con todos los procesos (filtrados segun permisos)
3. Cada proceso muestra:
   - Codigo (ej: `PD-2026-001`)
   - Trabajador involucrado
   - Empresa
   - Estado actual con badge de color
   - Fechas relevantes

### Estados del Proceso

El proceso disciplinario pasa por los siguientes estados:

```
Apertura --> Descargos Pendientes --> Descargos Realizados --> Sancion Emitida --> Cerrado
                                  --> Descargos No Realizados --> Archivado
```

| Estado | Color | Significado |
|--------|-------|-------------|
| Apertura | Azul | Proceso recien creado |
| Descargos Pendientes | Amarillo | Citacion enviada, esperando diligencia |
| Descargos Realizados | Verde | Trabajador asistio y respondio |
| Descargos No Realizados | Naranja | Trabajador no asistio |
| Sancion Emitida | Rojo | Se emitio sancion disciplinaria |
| Impugnacion Realizada | Morado | Trabajador presento recurso |
| Cerrado | Gris | Proceso finalizado |
| Archivado | Gris claro | Proceso cerrado sin sancion |

---

## Programacion de Diligencias de Descargos

### Paso 1: Crear Diligencia

1. Desde el proceso en estado **Apertura**, use la accion correspondiente para programar descargos
2. O vaya a **Gestion Laboral** > **Descargos** > **Crear Diligencia de Descargo**
3. Complete el formulario:

| Campo | Descripcion |
|-------|-------------|
| Proceso Disciplinario | Seleccione el proceso (busqueda por codigo o trabajador) |
| Modalidad | Virtual - El trabajador responde por internet |
| Fecha de Acceso | Dia en que el trabajador podra acceder al formulario |
| Nombre del Acompanante | Si el trabajador trae acompanante (opcional) |
| Relacion del Acompanante | Cargo o relacion (opcional) |
| Observaciones | Notas generales (opcional) |

4. Haga clic en **Crear**

<!-- ![Crear diligencia](/screenshots/abogado-crear-diligencia.png) -->

### Modalidades de Descargos

| Modalidad | Descripcion |
|-----------|-------------|
| **Virtual** | El trabajador recibe un enlace unico y responde desde su casa. Tiene un timer de 45 minutos. |

:::tip[Programacion con Anticipacion]
La diligencia debe programarse con al menos **5 dias habiles** de anticipacion para cumplir con los requisitos legales colombianos.
:::

### Paso 2: Generar Token de Acceso

Al crear la diligencia virtual, el sistema genera automaticamente:
- Un **token de acceso** unico de 64 caracteres
- Una **fecha de expiracion** de 6 dias
- El **acceso habilitado** para la modalidad virtual

---

## Generacion de Citaciones

### Paso 1: Generar Documento de Citacion

1. En la tabla de procesos, busque el proceso en estado apropiado
2. Haga clic en la accion **Generar Citacion**
3. El sistema genera un documento PDF/DOCX con:
   - Datos de la empresa
   - Datos del trabajador
   - Hechos descritos
   - Articulos legales incumplidos
   - Sanciones aplicables
   - Fecha, hora y modalidad de la diligencia
   - Derechos del trabajador

### Paso 2: Enviar Citacion por Email

1. Una vez generada la citacion, use la accion **Enviar Citacion**
2. El correo se envia al email del trabajador registrado en el sistema
3. El sistema crea automaticamente un registro de **Email Tracking**
4. El estado del proceso cambia a **Descargos Pendientes**

<!-- ![Enviar citacion](/screenshots/abogado-enviar-citacion.png) -->

### Paso 3: Monitorear Lectura

Despues de enviar la citacion, puede monitorear si el trabajador la leyo:

| Indicador | Significado |
|-----------|-------------|
| Gris - Pendiente | Correo aun no procesado |
| Amarillo - Entregado | Llego al servidor de correo del trabajador |
| Verde - Leido (N) | El trabajador abrio el correo N veces |

---

## Re-enviar una Citacion

Si el trabajador dice que no recibio el correo o necesita que se lo envien de nuevo:

1. En la tabla de procesos, busque el proceso en estado **Descargos Pendientes**
2. Haga clic en la accion **Re-enviar Citacion**
3. El sistema envia nuevamente el correo al trabajador con la citacion adjunta
4. Se crea un nuevo registro de seguimiento para este envio

:::note[No modifica la diligencia]
Re-enviar la citacion no cambia la fecha programada ni genera un documento nuevo. Solo reenvía el correo con la citacion ya existente.
:::

---

## Reprogramar una Diligencia de Descargos

Cuando el trabajador no pudo acceder en la fecha prevista o necesita una nueva fecha de acceso:

1. Vaya a **Gestion Laboral > Descargos**
2. Busque la diligencia correspondiente
3. Haga clic en la accion **Regenerar Token de Acceso**
4. El sistema genera un nuevo enlace valido por 6 dias
5. Notifique al trabajador el nuevo enlace usando **Re-enviar Citacion** en el proceso, o copielo directamente

:::caution[El enlace anterior queda invalido]
Cuando se regenera el token, el enlace que tenia el trabajador deja de funcionar. Asegurese de avisarle el nuevo enlace antes de la nueva fecha.
:::

---

## Ver el Enlace de Acceso del Trabajador

Para obtener la URL directa que el trabajador debe abrir para responder los descargos:

1. En **Gestion Laboral > Descargos**, busque la diligencia
2. Haga clic en la accion **Ver Link de Acceso**
3. Se muestra la URL completa
4. Copiela y enviela al trabajador por WhatsApp, mensaje de texto u otro canal

:::tip[Util cuando el correo no llega]
Si el trabajador no recibio el correo, esta opcion le permite enviarle el enlace directamente por otro medio de comunicacion.
:::

---

## Preguntas con Asistencia de IA

### Descripcion

CES Legal utiliza **Google Gemini** para generar preguntas de descargos basadas en los hechos del proceso. El abogado revisa y aprueba las preguntas antes de que sean presentadas al trabajador.

### Paso 1: Generar Preguntas

1. En la lista de diligencias de descargos, busque la diligencia correspondiente
2. Haga clic en el boton **Generar Preguntas IA** (icono de estrella)
3. Confirme la generacion en el modal
4. El sistema genera un conjunto de preguntas:
   - **Preguntas estandar** (13 preguntas fijas del proceso legal)
   - **Preguntas de IA** (2 preguntas contextuales basadas en los hechos)
   - **Pregunta de cierre** (cierre formal del descargo)

<!-- ![Generar preguntas IA](/screenshots/abogado-generar-preguntas-ia.png) -->

### Paso 2: Revisar Preguntas Generadas

1. Acceda a la vista de la diligencia para ver todas las preguntas
2. Las preguntas generadas por IA se identifican como tal
3. Puede:
   - Aprobar las preguntas tal como estan
   - Editar el texto de las preguntas
   - Eliminar preguntas no pertinentes
   - Agregar preguntas adicionales manualmente

:::caution[Revision Obligatoria]
Las preguntas generadas por IA **siempre deben ser revisadas** por el abogado antes de ser presentadas al trabajador. La IA es una herramienta de asistencia, no un reemplazo del criterio juridico profesional.
:::

### Trazabilidad de IA

El sistema registra automaticamente:
- Fecha y hora de la generacion
- Prompt enviado a la IA
- Respuesta recibida
- Preguntas finales aprobadas
- Usuario que solicito la generacion

Estos registros se guardan en la tabla `trazabilidad_ia_descargos` para auditoria.

---

## Realizacion de Descargos

### Descargos Virtuales

1. El trabajador accede al formulario mediante el enlace unico
2. Tiene **45 minutos** para responder todas las preguntas
3. Puede adjuntar archivos de evidencia
4. Al finalizar o al expirar el tiempo, las respuestas se guardan automaticamente

### Verificar Acceso del Trabajador

En la lista de diligencias puede ver:
- Si el trabajador accedio al formulario (`trabajador_accedio_en`)
- La IP desde la que accedio
- Cantidad de preguntas respondidas vs. total
- Si subio archivos de evidencia

### Generar Acta de Descargos

1. Una vez completados los descargos, haga clic en **Generar Acta**
2. El sistema genera un documento DOCX con:
   - Encabezado formal
   - Datos del proceso y las partes
   - Todas las preguntas formuladas
   - Todas las respuestas del trabajador
   - Pruebas aportadas (si las hay)
   - Firma y fecha

3. Descargue el acta con el boton **Descargar Acta**

<!-- ![Descargar acta](/screenshots/abogado-descargar-acta.png) -->

:::tip[Regenerar Acta]
Si necesita actualizar el acta (por ejemplo, despues de correciones), use la opcion **Regenerar Acta** en el menu de acciones. El acta anterior sera reemplazada.
:::

---

## Analisis y Emision de Sanciones

### Paso 1: Analisis con IA (Opcional)

1. Despues de los descargos, puede solicitar un **Analisis de Sancion con IA**
2. El sistema evalua:
   - Los hechos del proceso
   - Las respuestas del trabajador
   - Los articulos legales aplicables
   - Las sanciones del catalogo correspondientes
3. La IA sugiere un tipo de sancion y justificacion

### Paso 2: Decidir sobre la Sancion

Despues de los descargos, tiene dos opciones:

**Opcion A - Emitir Sancion:**
1. Seleccione el tipo de sancion (llamado de atencion, suspension, terminacion)
2. Especifique los dias de suspension (si aplica)
3. Redacte la motivacion legal
4. Genere el documento de sancion

**Opcion B - Archivar el Proceso:**
1. Si los hechos no se comprobaron o no ameritan sancion
2. Seleccione la opcion **Archivar**
3. Indique el motivo del archivo:
   - Hechos no comprobados
   - Falta no amerita sancion
   - Prescripcion del proceso
   - Otros motivos justificados

### Paso 3: Notificar la Sancion

1. Una vez generado el documento de sancion, use la accion **Enviar Sancion**
2. El correo se envia al trabajador con el documento adjunto
3. Se crea un registro de tracking para monitorear la lectura
4. El estado cambia a **Sancion Emitida**

---

## Registrar una Impugnacion

Cuando el trabajador presenta un recurso de reposicion o apelacion contra la sancion emitida:

1. Busque el proceso en estado **Sancion Emitida**
2. Use la accion **Registrar Impugnacion**
3. Complete los datos:
   - **Tipo de recurso**: Reposicion (ante el mismo funcionario) o Apelacion (ante el superior)
   - **Fecha de presentacion**: Dia en que el trabajador entrego el recurso
   - **Argumentos**: Resumen de lo que plantea el trabajador
4. El estado del proceso cambia a **Impugnacion Realizada**

:::note[Plazo para impugnar]
Si el trabajador no presenta el recurso dentro del plazo del reglamento interno, puede cerrar el proceso directamente sin registrar impugnacion.
:::

---

## Resolver una Impugnacion y Cerrar el Proceso

### Si hay impugnacion

1. Busque el proceso en estado **Impugnacion Realizada**
2. Use la accion para resolver el recurso
3. Registre la decision: mantener la sancion o modificarla segun corresponda
4. El proceso pasa al estado **Cerrado**

### Si no hay impugnacion

1. Busque el proceso en estado **Sancion Emitida**
2. Use la accion **Cerrar Proceso**
3. El proceso pasa directamente al estado **Cerrado**

### Archivar sin sancion

Si despues de los descargos se decide no imponer ninguna sancion:

1. Busque el proceso en estado **Descargos Realizados** o **Descargos No Realizados**
2. Use la accion **Archivar**
3. Seleccione el motivo:
   - Hechos no comprobados
   - Falta no amerita sancion
   - Prescripcion del proceso
   - Otros (con descripcion justificada)
4. El proceso pasa al estado **Archivado**

:::tip[Diferencia entre Cerrado y Archivado]
**Cerrado**: el proceso termino con una sancion aplicada. **Archivado**: se decidio no sancionar. En ambos casos el proceso queda en modo consulta y todos los documentos siguen disponibles.
:::

---

## Gestion de Disponibilidad

### Descripcion

El modulo de **Disponibilidad del Abogado** permite registrar los horarios en que esta disponible para realizar diligencias de descargos.

### Registrar Disponibilidad

1. Vaya a **Disponibilidad de Abogados** (si tiene acceso al recurso)
2. Cree un nuevo registro con:

| Campo | Descripcion |
|-------|-------------|
| Abogado | Se autoselecciona (usted) |
| Fecha | Dia disponible |
| Hora Inicio | Hora de inicio del bloque |
| Hora Fin | Hora de fin del bloque |
| Tipo | Presencial, Virtual o Ambos |
| Disponible | Si esta libre o ya ocupado |
| Notas | Informacion adicional |

### Estados de la Disponibilidad

| Estado | Descripcion |
|--------|-------------|
| Disponible | Horario libre para asignar |
| Ocupado | Horario asignado a un proceso (se marca automaticamente) |

Cuando se programa una diligencia en un horario, el sistema marca automaticamente el bloque como **ocupado** y lo asocia al proceso. Si la diligencia se cancela, el horario se **libera** automaticamente.

---

## Monitoreo de Notificaciones

### Tracking de Correos

Desde cualquier proceso puede ver el estado de los correos enviados:

1. Busque la seccion de tracking en el detalle del proceso
2. Vera el estado de:
   - **Citacion**: Si fue enviada, entregada y leida
   - **Sancion**: Si fue notificada, entregada y leida

### Consulta API de Tracking

Para informacion detallada, el sistema dispone de un endpoint JSON:

```
GET /api/email-tracking/{procesoId}
```

Que retorna:
- Tipo de correo (citacion/sancion)
- Email del destinatario
- Fecha de envio
- Estado de lectura
- Veces abierto
- Tiempo hasta la primera apertura

---

## Generacion de Documentos

### Documentos que puede generar

| Documento | Formato | Descripcion |
|-----------|---------|-------------|
| Citacion a Descargos | PDF / DOCX | Notificacion formal al trabajador |
| Acta de Descargos | DOCX | Registro de preguntas y respuestas |
| Documento de Sancion | PDF / HTML | Resolucion de sancion disciplinaria |

### Descargar Documentos

Los documentos generados se pueden descargar desde:
1. Las acciones de la tabla de procesos
2. Las acciones de la tabla de diligencias
3. Las URLs directas:
   - `/descargar/citacion/{procesoId}`
   - `/descargar/acta/{diligenciaId}`
   - `/descargar/sancion/{procesoId}`

---

## Modulo de Feedback

### Cuando aparece el formulario

Al usar el listado de procesos, puede aparecer un formulario automatico para conocer su opinion. No tiene boton de cerrar: debe completarlo para continuar usando la plataforma.

| Momento              | Que lo dispara                                        |
| -------------------- | ----------------------------------------------------- |
| Primera experiencia  | La primera vez que tiene procesos en curso            |
| Despues de descargos | Un proceso llego al estado "Descargos Realizados"     |
| Periodico            | Han pasado 14 dias desde el ultimo feedback           |
| Por hitos            | Completo 5, 10 o 15 procesos                          |

### Como completar el formulario

1. Seleccione su calificacion de 1 a 5 estrellas
2. Responda las preguntas del formulario
3. Escriba un comentario o sugerencia en el campo de texto
4. Haga clic en **Enviar**

Todos los campos son obligatorios. El boton de envio no se activa hasta completar todo, incluyendo el comentario.

### Consultar el feedback recibido

Para revisar las opiniones de clientes y trabajadores:

1. En el menu lateral, vaya a **Administracion > Feedback**
2. Use las pestanas para filtrar:
   - **Trabajadores**: Feedback al terminar sus descargos virtuales
   - **Clientes**: Feedback de usuarios del panel de administracion
   - **Con comentario**: Solo los que tienen texto escrito
   - **Con NPS**: Los que incluyen puntaje de recomendacion (0-10)
   - **Negativos**: Calificaciones de 1 o 2 estrellas
3. Haga clic en un registro para ver el detalle completo: calificacion, comentario, preguntas respondidas y datos del usuario

---

## Problemas Comunes y Soluciones

### Las preguntas de IA no se generan

**Posibles causas:**
1. Los hechos del proceso estan vacios o muy cortos
2. La API de Google Gemini no esta configurada o no responde
3. Ya se generaron preguntas previamente (el boton desaparece si ya hay preguntas)

**Solucion:** Verifique que el proceso tiene hechos detallados. Si el problema persiste, contacte al administrador para verificar la configuracion de la API.

### El trabajador no puede acceder al formulario

**Verificar:**
1. Que el token de acceso no haya expirado (validez de 6 dias)
2. Que la fecha actual sea igual a la `fecha_acceso_permitida`
3. Que el campo `acceso_habilitado` este en `true`
4. Si el token expiro, use la accion **Regenerar Token**

### El acta no se genera correctamente

**Posibles causas:**
1. No hay respuestas del trabajador aun
2. Errores de permisos en el directorio de almacenamiento

**Solucion:** Verifique que el trabajador haya respondido al menos una pregunta. Si el problema persiste, intente **Regenerar Acta** o contacte al administrador.

### No puedo ver el tracking de un correo

**Posibles causas:**
1. El correo aun no ha sido procesado
2. El cliente de correo del trabajador bloquea imagenes externas
3. El trabajador no ha abierto el correo

**Solucion:** Espere un tiempo prudencial. Si despues de varios dias el estado sigue en "Pendiente", es probable que el email sea incorrecto o el servidor de correo bloquee el tracking.

---

## Archivos Relacionados

```
app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php
app/Filament/Admin/Resources/DiligenciaDescargoResource.php
app/Filament/Admin/Resources/DisponibilidadAbogadoResource.php
app/Services/IADescargoService.php
app/Services/IAAnalisisSancionService.php
app/Services/DocumentGeneratorService.php
app/Services/ActaDescargosService.php
app/Models/ProcesoDisciplinario.php
app/Models/DiligenciaDescargo.php
app/Models/DisponibilidadAbogado.php
```

## Proximos Pasos

- [Manual del Administrador](/manuales/administrador/) - Guia para el rol de administrador
- [Manual del Cliente](/manuales/cliente/) - Guia para el rol de cliente (RRHH)
- [Generacion de Preguntas con IA](/ia/generacion-preguntas/) - Documentacion tecnica de la IA
- [Analisis de Sanciones con IA](/ia/analisis-sanciones/) - Documentacion del analisis automatizado
