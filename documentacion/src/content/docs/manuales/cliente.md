---
title: Manual del Cliente
description: Guia completa para el usuario con rol cliente en CES Legal
---

## Descripcion del Rol

El **Cliente** (rol `cliente`) es el representante de la empresa que utiliza el sistema CES Legal para gestionar los procesos disciplinarios de sus trabajadores. Este rol tiene un alcance limitado a los datos de **su propia empresa**.

### Capacidades del Cliente

- Crear procesos disciplinarios para trabajadores de su empresa
- Gestionar trabajadores de su empresa (crear, editar, ver)
- Hacer seguimiento del estado de los procesos
- Descargar documentos generados (citaciones, actas, sanciones)
- Programar diligencias de descargos (con minimo 5 dias habiles de anticipacion)
- Monitorear el estado de lectura de correos enviados (citaciones y sanciones)
- Visualizar informacion de su empresa

### Restricciones del Rol

- Solo puede ver datos de la empresa asignada a su cuenta
- No puede gestionar usuarios ni roles
- No puede acceder al catalogo de sanciones laborales ni articulos legales
- No puede modificar datos de otras empresas

---

## Primer Acceso al Sistema

### Paso 1: Recibir Credenciales

El administrador del sistema le proporcionara:

- URL de acceso: `https://su-dominio.com/admin`
- Correo electronico
- Contrasena temporal

### Paso 2: Iniciar Sesion

1. Abra el navegador y acceda a la URL proporcionada
2. Ingrese su correo electronico y contrasena
3. Haga clic en **Iniciar Sesion**

<!-- ![Login del cliente](/screenshots/cliente-login.png) -->

### Paso 3: Tutorial de Onboarding

Al iniciar sesion por primera vez, el sistema le mostrara un **tour interactivo** que le guiara por las funcionalidades principales:

1. **Bienvenida**: Descripcion general del sistema
2. **Menu de navegacion**: Como navegar entre los modulos
3. **Crear Descargos**: Como iniciar un proceso disciplinario
4. **Historial de Descargos**: Como ver los procesos existentes
5. **Trabajadores**: Como gestionar el personal de su empresa

:::tip[Tour de Onboarding]
Si necesita volver a ver el tutorial en cualquier momento, busque la opcion de **Ayuda** o **Tutorial** en el panel. El tour le resaltara cada elemento de la interfaz paso a paso.
:::

<!-- ![Onboarding tour](/screenshots/cliente-onboarding.png) -->

---

## Dashboard del Cliente

Al iniciar sesion, vera el dashboard con informacion de **su empresa unicamente**:

- **Procesos activos**: Cantidad de procesos en curso
- **Procesos por estado**: Distribucion de procesos segun su estado actual
- **Ultimos procesos**: Lista de los procesos mas recientes

<!-- ![Dashboard del cliente](/screenshots/cliente-dashboard.png) -->

---

## Gestion de Trabajadores

### Ver Trabajadores de su Empresa

1. En el menu lateral, haga clic en **Trabajadores**
2. Vera la lista de trabajadores registrados de **su empresa**
3. La tabla muestra:
    - Nombre completo
    - Tipo y numero de documento
    - Empresa (su empresa)
    - Cargo
    - Email
    - Estado activo/inactivo
    - Cantidad de procesos disciplinarios

:::note[Filtrado Automatico]
Como cliente, solo vera los trabajadores de su empresa. No necesita aplicar filtros adicionales; el sistema filtra automaticamente por su empresa asignada.
:::

### Crear un Nuevo Trabajador

1. Haga clic en el boton **Crear Trabajador**
2. Complete el formulario:

**Seccion - Seleccione la Empresa:**

- La empresa se asigna automaticamente (su empresa)
- El campo aparece deshabilitado con el mensaje "Empresa asignada automaticamente"

**Seccion - Informacion Personal:**

| Campo                      | Descripcion                   | Requerido |
| -------------------------- | ----------------------------- | :-------: |
| Tipo de Documento          | CC, CE, TI o Pasaporte        |    Si     |
| Numero de Documento        | Numero unico del documento    |    Si     |
| Genero                     | Masculino o Femenino          |    Si     |
| Nombres                    | Nombres completos             |    Si     |
| Apellidos                  | Apellidos completos           |    Si     |
| Departamento de Nacimiento | Departamento colombiano       | Opcional  |
| Ciudad de Nacimiento       | Municipio (1,122 disponibles) | Opcional  |
| Correo Electronico         | Email del trabajador          |    Si     |

**Seccion - Informacion Laboral:**

| Campo               | Descripcion                                            | Requerido |
| ------------------- | ------------------------------------------------------ | :-------: |
| Cargo               | Lista de 36 cargos predefinidos + opcion personalizada |    Si     |
| Area / Departamento | Lista de 23 areas + opcion personalizada               |    No     |
| Trabajador Activo   | Toggle activo/inactivo                                 |    No     |

**Seccion - Datos de Contacto (Opcional):**

- Telefono / Celular
- Direccion de Residencia

3. Haga clic en **Crear**

<!-- ![Crear trabajador](/screenshots/cliente-crear-trabajador.png) -->

:::tip[Cargo Personalizado]
Si el cargo del trabajador no esta en la lista predefinida, seleccione **"Otro (personalizado)"** al final de la lista y escriba el cargo manualmente.
:::

### Editar un Trabajador

1. En la lista de trabajadores, haga clic en **Editar** junto al trabajador
2. Modifique los campos necesarios
3. Haga clic en **Guardar cambios**

### Desactivar un Trabajador

Si un trabajador ya no labora en la empresa:

1. En la lista, haga clic en **Desactivar** junto al trabajador
2. Confirme la accion en el modal
3. El trabajador quedara marcado como inactivo pero no se elimina del sistema

Para reactivarlo posteriormente:

1. Busque el trabajador (puede estar oculto por filtro de activos)
2. Haga clic en **Activar**

---

## Creacion de Procesos Disciplinarios

### Antes de Empezar

Antes de crear un proceso disciplinario, asegurese de tener:

- El trabajador registrado en el sistema
- Descripcion clara de los hechos ocurridos
- Fecha de ocurrencia de los hechos
- Conocimiento de los articulos legales o normas incumplidas (opcional, el abogado puede completar)

### Paso 1: Iniciar el Proceso

1. Haga clic en **Crear Descargos** en el menu lateral
2. Se abrira el formulario de creacion

<!-- ![Crear proceso](/screenshots/cliente-crear-proceso.png) -->

### Paso 2: Completar el Formulario

**Seccion - Empresa y Trabajador:**

- La empresa se selecciona automaticamente (su empresa)
- Seleccione al trabajador de la lista (busqueda por nombre)

**Seccion - Hechos:**

| Campo               | Descripcion                          | Requerido |
| ------------------- | ------------------------------------ | :-------: |
| Hechos              | Descripcion detallada de lo ocurrido |    Si     |
| Fecha de Ocurrencia | Dia en que ocurrieron los hechos     |    Si     |
| Normas Incumplidas  | Descripcion de las normas violadas   | Opcional  |

**Seccion - Articulos Legales:**

- Seleccione los articulos del Codigo Sustantivo del Trabajo aplicables
- Puede seleccionar multiples articulos
- Si no esta seguro, el abogado puede completar esta seccion despues

**Seccion - Sanciones Laborales:**

- Seleccione las sanciones aplicables del catalogo
- Las sanciones estan clasificadas como Leves o Graves
- Puede seleccionar multiples sanciones

**Seccion - Pruebas Iniciales:**

- Descripcion de las pruebas que respaldan los hechos
- Adjunte archivos si los tiene disponibles

3. Haga clic en **Crear**

### Paso 3: Siguiente Paso

Una vez creado el proceso:

- El estado inicial es **Apertura**
- El administrador o abogado asignara un abogado al caso
- El abogado se encargara de generar la citacion y programar la diligencia
- Usted podra hacer seguimiento del estado en el **Historial de Descargos**

---

## Seguimiento de Procesos

### Ver el Historial de Procesos

1. Haga clic en **Historial de Descargos** en el menu lateral
2. Vera la tabla con todos los procesos de **su empresa**
3. Cada proceso muestra:
    - Codigo del proceso
    - Trabajador
    - Estado actual (con badge de color)
    - Abogado asignado
    - Fechas importantes

### Estados y su Significado

| Estado                  | Color      | Que esta pasando                                |
| ----------------------- | ---------- | ----------------------------------------------- |
| Apertura                | Azul       | Proceso creado, esperando asignacion de abogado |
| Descargos Pendientes    | Amarillo   | Citacion enviada, esperando la diligencia       |
| Descargos Realizados    | Verde      | El trabajador respondio, en analisis            |
| Descargos No Realizados | Naranja    | El trabajador no asistio a la diligencia        |
| Sancion Emitida         | Rojo       | Se emitio una sancion disciplinaria             |
| Impugnacion Realizada   | Morado     | El trabajador presento recurso                  |
| Cerrado                 | Gris       | Proceso completamente finalizado                |
| Archivado               | Gris claro | Proceso cerrado sin sancion                     |

### Detalle de un Proceso

Haga clic en un proceso para ver su detalle completo:

- Informacion del trabajador
- Hechos descritos
- Articulos y sanciones aplicables
- Timeline de eventos
- Documentos generados
- Estado de notificaciones por email

---

## Descarga de Documentos

### Documentos Disponibles

Segun el estado del proceso, podra descargar:

| Documento            | Disponible desde             | Formato    |
| -------------------- | ---------------------------- | ---------- |
| Citacion a Descargos | Estado: Descargos Pendientes | PDF / DOCX |
| Acta de Descargos    | Estado: Descargos Realizados | DOCX       |
| Documento de Sancion | Estado: Sancion Emitida      | PDF / HTML |

### Como Descargar

1. En la tabla de procesos o en el detalle del proceso
2. Busque las acciones de descarga (iconos de flecha hacia abajo)
3. Haga clic en **Descargar Citacion**, **Descargar Acta** o **Descargar Sancion**
4. El archivo se descargara automaticamente

<!-- ![Descargar documentos](/screenshots/cliente-descargar-documentos.png) -->

---

## Programacion de Diligencias de Descargos

### Requisitos Previos

- El proceso debe tener un abogado asignado
- Debe programarse con **minimo 5 dias habiles** de anticipacion
- El trabajador debe tener correo electronico registrado

:::caution[Plazo Legal Minimo]
Segun la legislacion laboral colombiana, la citacion a descargos debe notificarse al trabajador con suficiente anticipacion. CES Legal requiere un minimo de **5 dias habiles** antes de la fecha programada para la diligencia.
:::

### Programar una Diligencia

1. Desde un proceso en estado de **Apertura** o con la accion correspondiente
2. Seleccione la modalidad: **Virtual**
3. Seleccione la **Fecha de Acceso** (dia en que el trabajador podra responder)
4. Confirme la programacion

### Modalidad Virtual

En la modalidad virtual:

- Se genera un **enlace unico** para el trabajador
- El enlace tiene validez de **6 dias**
- El trabajador solo puede acceder en la **fecha exacta** configurada
- Tiene **45 minutos** para responder todas las preguntas
- Puede adjuntar archivos de evidencia

---

## Re-enviar una Citacion

Si el trabajador dice que no recibio el correo con la citacion:

1. En **Historial de Descargos**, busque el proceso en estado **Descargos Pendientes**
2. Haga clic en la accion **Re-enviar Citacion**
3. El sistema envia nuevamente el correo al trabajador con la citacion adjunta

:::note[Verifique el correo del trabajador]
Antes de re-enviar, confirme que el correo electronico del trabajador este escrito correctamente en su perfil.
:::

---

## Reprogramar una Diligencia

Si el trabajador no pudo acceder en la fecha programada o necesita un nuevo enlace de acceso:

1. Vaya a **Gestion Laboral > Descargos**
2. Busque la diligencia del proceso
3. Haga clic en **Regenerar Token de Acceso**
4. El sistema genera un nuevo enlace valido por 6 dias
5. Use **Re-enviar Citacion** en el proceso para notificarle, o copie el enlace directamente

:::caution[El enlace anterior queda invalido]
El trabajador ya no podra usar el enlace anterior. Asegurese de informarle el cambio antes de la nueva fecha.
:::

---

## Ver el Enlace de Acceso del Trabajador

Si prefiere enviarle el enlace directamente en lugar de esperar a que llegue el correo:

1. En **Gestion Laboral > Descargos**, busque la diligencia del proceso
2. Haga clic en la accion **Ver Link de Acceso**
3. Se muestra la URL completa que debe abrir el trabajador en su navegador
4. Copiela y enviela por WhatsApp, mensaje de texto u otro canal

---

## Monitoreo de Notificaciones por Email

### Tracking de Citaciones

Despues de enviar una citacion, puede verificar si el trabajador la recibio y leyo:

1. En el detalle del proceso, busque la seccion de **Tracking de Email**
2. Vera el estado:

| Estado    | Icono    | Significado                             |
| --------- | -------- | --------------------------------------- |
| Pendiente | Gris     | Correo enviado pero no procesado aun    |
| Entregado | Amarillo | Correo llego al servidor del trabajador |
| Leido (N) | Verde    | Trabajador abrio el correo N veces      |

### Tracking de Sanciones

De la misma manera, cuando se emite y notifica una sancion:

- Puede verificar si el trabajador recibio la notificacion
- Ver cuantas veces abrio el correo
- Ver la fecha y hora de la primera apertura
- Ver el tiempo transcurrido entre envio y apertura

:::note[Importante sobre el Tracking]
El tracking funciona mediante un pixel de imagen incrustado en el correo. Algunos clientes de correo (como Outlook) bloquean imagenes externas por defecto, lo que puede afectar la precision del seguimiento. Un estado "Pendiente" no necesariamente significa que el trabajador no leyo el correo.
:::

---

## Informacion de su Empresa

### Ver Datos de la Empresa

1. En el menu lateral, vaya a **Administracion** > **Empresas**
2. Vera la informacion de **su empresa unicamente**:
    - Razon social y NIT
    - Representante legal
    - Direccion, telefono y email
    - Departamento y ciudad
    - Cantidad de trabajadores registrados

### Limitaciones

Como cliente, **no puede**:

- Modificar datos de la empresa (solo el administrador puede)
- Ver datos de otras empresas
- Crear nuevas empresas

Si necesita actualizar informacion de su empresa, contacte al administrador del sistema.

---

## Tour de Onboarding Detallado

El sistema incluye un tutorial interactivo que resalta los elementos principales de la interfaz. Los pasos del tour cubren:

### Modulo de Trabajadores

El tour resalta los siguientes elementos:

1. **Selector de empresa** - Muestra que la empresa esta asignada automaticamente
2. **Tipo de documento** - Como seleccionar CC, CE, TI o Pasaporte
3. **Numero de documento** - Campo con mascara automatica
4. **Genero** - Seleccion para formato correcto de documentos
5. **Nombres y apellidos** - Campos de nombre completo
6. **Departamento y ciudad** - Ubicacion de nacimiento
7. **Email** - Correo electronico del trabajador
8. **Cargo** - Lista predefinida con opcion personalizada
9. **Area** - Departamento laboral
10. **Estado activo** - Toggle de activacion

### Modulo de Procesos

El tour cubre:

1. Como seleccionar empresa y trabajador
2. Como describir los hechos
3. Como seleccionar articulos y sanciones
4. Como adjuntar pruebas

---

## Formulario de Feedback Automatico

### Por que aparece este formulario

En determinados momentos, el sistema le pedira su opinion sobre la plataforma. Aparece como un formulario en el listado de procesos y **no tiene boton de cerrar**: debe completarlo para seguir usando el sistema.

Aparece cuando:

- Es la primera vez que tiene procesos en curso
- Un proceso llego al estado "Descargos Realizados"
- Han pasado 14 dias desde que respondio el ultimo formulario
- Completo 5, 10 o 15 procesos

### Como responder el formulario

1. Seleccione su calificacion de 1 a 5 estrellas
2. Responda las preguntas que aparecen
3. Escriba un comentario o sugerencia en el campo de texto
4. Haga clic en **Enviar**

El formulario desaparece y puede seguir usando el sistema con normalidad.

:::note[Todos los campos son obligatorios]
El boton de envio no se activa hasta completar todos los campos, incluyendo el comentario.
:::

---

## Problemas Comunes y Soluciones

### No veo ningun trabajador en la lista

**Causa:** No hay trabajadores registrados para su empresa.
**Solucion:** Haga clic en **Crear Trabajador** para registrar al primer trabajador.

### No puedo crear un proceso disciplinario

**Verificar:**

1. Que tenga al menos un trabajador registrado y activo
2. Que los campos obligatorios esten completos (hechos, fecha de ocurrencia)
3. Que el trabajador seleccionado pertenezca a su empresa

### No veo procesos de otra empresa

**Explicacion:** Esto es por diseno. Como cliente, solo tiene acceso a los datos de su empresa asignada. Si necesita acceso a otra empresa, contacte al administrador.

### El tracking de email muestra "Pendiente" despues de varios dias

**Posibles causas:**

1. El correo fue enviado a una direccion incorrecta
2. El correo cayo en la carpeta de spam del trabajador
3. El cliente de correo bloquea imagenes externas

**Solucion:** Verifique que la direccion de email del trabajador sea correcta. Pida al trabajador que revise su carpeta de spam. Si el problema persiste, contacte al administrador.

### No puedo descargar un documento

**Verificar:**

1. Que el documento haya sido generado (el boton de descarga aparece solo cuando el documento existe)
2. Que el proceso este en el estado correcto para ese documento
3. Que su sesion no haya expirado (vuelva a iniciar sesion si es necesario)

### Quiero cambiar mi contraseña

**Solucion:** En el menú de la esquina superior se encuentra la opción de "Cambiar Contraseña" si no puede cambiar la contraseña contacte al administrador del sistema para que actualice su contraseña. Actualmente, el cambio de contraseña se realiza a traves del modulo de usuarios administrado por el administrador.

---

## Resumen de Navegacion

| Menu                   | Funcionalidad                        |
| ---------------------- | ------------------------------------ |
| Crear Descargos        | Crear un nuevo proceso disciplinario |
| Historial de Descargos | Ver todos los procesos de su empresa |
| Trabajadores           | Gestionar trabajadores de su empresa |
| Empresas               | Ver informacion de su empresa        |

---

## Archivos Relacionados

```
app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php
app/Filament/Admin/Resources/TrabajadorResource.php
app/Filament/Admin/Resources/EmpresaResource.php
app/Models/User.php  (isCliente(), empresa_id)
```

## Proximos Pasos

- [Manual del Administrador](/manuales/administrador/) - Guia para el rol de administrador
- [Manual del Abogado](/manuales/abogado/) - Guia para el rol de abogado
- [Estados del Proceso](/flujo/estados-proceso/) - Explicacion detallada de los estados
- [Reglas de Negocio](/flujo/reglas-negocio/) - Validaciones y plazos legales
