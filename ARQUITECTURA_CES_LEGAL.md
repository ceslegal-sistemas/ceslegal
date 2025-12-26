# ARQUITECTURA CES LEGAL - Software Jurídico Laboral

## 📋 Información del Proyecto

**Cliente:** CES LEGAL
**Proyecto:** Sistema de Gestión Jurídico-Laboral
**Stack Técnico:** Laravel 12 + FilamentPHP + MySQL
**Duración:** 19 de diciembre 2025 - 24 de enero 2026
**Arquitectura:** Single-tenant con separación lógica

---

## 🎯 Objetivo del Sistema

Automatizar y digitalizar los procesos jurídicos laborales que actualmente realiza CES LEGAL de forma manual, específicamente:

1. Procesos Disciplinarios Laborales (9 pasos)
2. Contratos Laborales por Labor u Obra (4 pasos)

---

## 👥 Usuarios del Sistema

### 1. Abogados de CES LEGAL (Rol Principal)
- Gestionan procesos disciplinarios completos
- Realizan análisis jurídicos
- Crean y editan documentos legales
- Controlan términos legales

### 2. Administradores
- Gestión completa del sistema
- Configuración de usuarios y permisos
- Acceso a reportes y estadísticas globales

### 3. Clientes (Empresas)
- Visualizan el estado de sus procesos
- Envían solicitudes de apertura
- Reciben notificaciones

### 4. RRHH de Empresas Clientes
- Reciben documentos finales
- Cierran procesos
- Notifican a trabajadores

---

## 🗄️ DISEÑO DE BASE DE DATOS

### Tablas Principales

#### 1. `users` (Usuarios del Sistema)
```sql
id - bigint unsigned (PK)
name - varchar(255)
email - varchar(255) UNIQUE
password - varchar(255)
role - enum('admin', 'abogado', 'cliente', 'rrhh')
empresa_id - bigint unsigned (FK - nullable) [Para clientes/RRHH]
active - boolean DEFAULT true
created_at - timestamp
updated_at - timestamp
```

#### 2. `empresas` (Empresas Clientes)
```sql
id - bigint unsigned (PK)
razon_social - varchar(255)
nit - varchar(50) UNIQUE
direccion - text
telefono - varchar(50)
email_contacto - varchar(255)
ciudad - varchar(100)
departamento - varchar(100)
representante_legal - varchar(255)
active - boolean DEFAULT true
created_at - timestamp
updated_at - timestamp
```

#### 3. `trabajadores` (Trabajadores de las Empresas)
```sql
id - bigint unsigned (PK)
empresa_id - bigint unsigned (FK)
tipo_documento - enum('CC', 'CE', 'TI', 'PASS')
numero_documento - varchar(50)
nombres - varchar(255)
apellidos - varchar(255)
cargo - varchar(255)
fecha_ingreso - date
email - varchar(255)
telefono - varchar(50)
direccion - text
active - boolean DEFAULT true
created_at - timestamp
updated_at - timestamp

UNIQUE (tipo_documento, numero_documento)
INDEX idx_empresa (empresa_id)
```

---

### MÓDULO 1: PROCESOS DISCIPLINARIOS

#### 4. `procesos_disciplinarios` (Proceso Principal)
```sql
id - bigint unsigned (PK)
codigo - varchar(50) UNIQUE [Auto: PD-2025-0001]
empresa_id - bigint unsigned (FK)
trabajador_id - bigint unsigned (FK)
abogado_id - bigint unsigned (FK) [user_id del abogado]
estado - enum(
    'apertura',
    'traslado',
    'descargos_pendientes',
    'descargos_realizados',
    'analisis_juridico',
    'pendiente_gerencia',
    'sancion_definida',
    'notificado',
    'impugnado',
    'cerrado',
    'archivado'
)
hechos - text
fecha_ocurrencia - date
normas_incumplidas - text
pruebas_iniciales - text
fecha_solicitud - datetime
fecha_apertura - datetime
fecha_descargos_programada - datetime
fecha_descargos_realizada - datetime (nullable)
fecha_analisis - datetime (nullable)
decision_sancion - boolean (nullable) [true = hay sanción, false = archivo]
motivo_archivo - text (nullable)
tipo_sancion - enum('llamado_atencion', 'suspension', 'terminacion') (nullable)
fecha_notificacion - datetime (nullable)
fecha_limite_impugnacion - datetime (nullable) [automático: +3 días hábiles]
impugnado - boolean DEFAULT false
fecha_impugnacion - datetime (nullable)
fecha_cierre - datetime (nullable)
created_at - timestamp
updated_at - timestamp
deleted_at - timestamp (nullable) [soft delete]

INDEX idx_empresa (empresa_id)
INDEX idx_trabajador (trabajador_id)
INDEX idx_estado (estado)
INDEX idx_abogado (abogado_id)
```

#### 5. `diligencias_descargos` (Diligencia de Descargos)
```sql
id - bigint unsigned (PK)
proceso_id - bigint unsigned (FK)
fecha_diligencia - datetime
lugar_diligencia - varchar(255)
trabajador_asistio - boolean
motivo_inasistencia - text (nullable)
acompanante_nombre - varchar(255) (nullable)
acompanante_cargo - varchar(255) (nullable)
preguntas_formuladas - json [Array de preguntas]
respuestas - json [Array de respuestas]
pruebas_aportadas - boolean DEFAULT false
descripcion_pruebas - text (nullable)
observaciones - text (nullable)
acta_generada - boolean DEFAULT false
ruta_acta - varchar(255) (nullable)
created_at - timestamp
updated_at - timestamp
```

#### 6. `analisis_juridicos` (Análisis Jurídico)
```sql
id - bigint unsigned (PK)
proceso_id - bigint unsigned (FK)
abogado_id - bigint unsigned (FK)
fecha_analisis - datetime
analisis_hechos - text
analisis_pruebas - text
analisis_normativo - text
conclusion - text
recomendacion - enum('archivo', 'sancion')
tipo_sancion_recomendada - enum('llamado_atencion', 'suspension', 'terminacion') (nullable)
fundamento_legal - text
observaciones - text (nullable)
created_at - timestamp
updated_at - timestamp
```

#### 7. `sanciones` (Sanciones Aplicadas)
```sql
id - bigint unsigned (PK)
proceso_id - bigint unsigned (FK)
tipo_sancion - enum('llamado_atencion', 'suspension', 'terminacion')
dias_suspension - int (nullable) [Solo para suspensión]
fecha_inicio_suspension - date (nullable)
fecha_fin_suspension - date (nullable)
motivo_sancion - text
fundamento_legal - text
observaciones - text (nullable)
documento_generado - boolean DEFAULT false
ruta_documento - varchar(255) (nullable)
fecha_notificacion_rrhh - datetime (nullable)
fecha_notificacion_trabajador - datetime (nullable)
notificado_por - varchar(255) (nullable) [Nombre de quien notificó]
created_at - timestamp
updated_at - timestamp
```

#### 8. `impugnaciones` (Impugnaciones)
```sql
id - bigint unsigned (PK)
proceso_id - bigint unsigned (FK)
sancion_id - bigint unsigned (FK)
fecha_impugnacion - datetime
motivos_impugnacion - text
pruebas_adicionales - text (nullable)
fecha_analisis_impugnacion - datetime (nullable)
abogado_analisis_id - bigint unsigned (FK) (nullable)
analisis_impugnacion - text (nullable)
decision_final - enum('confirma_sancion', 'revoca_sancion', 'modifica_sancion') (nullable)
nueva_sancion_tipo - enum('llamado_atencion', 'suspension', 'terminacion') (nullable)
fundamento_decision - text (nullable)
fecha_decision - datetime (nullable)
documento_generado - boolean DEFAULT false
ruta_documento - varchar(255) (nullable)
created_at - timestamp
updated_at - timestamp
```

---

### MÓDULO 2: CONTRATOS LABORALES

#### 9. `solicitudes_contrato` (Solicitudes de Contrato)
```sql
id - bigint unsigned (PK)
codigo - varchar(50) UNIQUE [Auto: SC-2025-0001]
empresa_id - bigint unsigned (FK)
abogado_id - bigint unsigned (FK) (nullable)
estado - enum(
    'solicitado',
    'en_analisis',
    'revision_objeto',
    'contrato_generado',
    'enviado_rrhh',
    'cerrado'
)
tipo_contrato - enum('labor_obra') DEFAULT 'labor_obra'
fecha_solicitud - datetime
trabajador_id - bigint unsigned (FK) (nullable) [Si ya existe]
trabajador_nombres - varchar(255)
trabajador_apellidos - varchar(255)
trabajador_documento_tipo - enum('CC', 'CE', 'TI', 'PASS')
trabajador_documento_numero - varchar(50)
trabajador_email - varchar(255) (nullable)
trabajador_telefono - varchar(50) (nullable)
trabajador_direccion - text (nullable)
cargo_contrato - varchar(255)
responsabilidades - text
objeto_comercial - text [De la orden/contrato comercial]
manual_funciones - text
ruta_orden_compra - varchar(255) (nullable)
ruta_manual_funciones - varchar(255) (nullable)
fecha_inicio_propuesta - date (nullable)
salario_propuesto - decimal(15,2) (nullable)
fecha_analisis - datetime (nullable)
objeto_juridico_redactado - text (nullable) [Objeto específico analizado]
observaciones_juridicas - text (nullable)
fecha_generacion_contrato - datetime (nullable)
ruta_contrato - varchar(255) (nullable)
fecha_envio_rrhh - datetime (nullable)
fecha_cierre - datetime (nullable)
created_at - timestamp
updated_at - timestamp
deleted_at - timestamp (nullable)

INDEX idx_empresa (empresa_id)
INDEX idx_estado (estado)
INDEX idx_abogado (abogado_id)
```

---

### SISTEMA DE GESTIÓN

#### 10. `terminos_legales` (Control de Términos Legales)
```sql
id - bigint unsigned (PK)
proceso_tipo - enum('proceso_disciplinario', 'contrato')
proceso_id - bigint unsigned [ID del proceso relacionado]
termino_tipo - enum(
    'traslado_descargos', // 5 días hábiles
    'impugnacion',        // 3 días hábiles
    'analisis_juridico',  // días configurables
    'respuesta_gerencia'  // días configurables
)
fecha_inicio - datetime
dias_habiles - int
fecha_vencimiento - datetime [Calculada automáticamente]
dias_transcurridos - int DEFAULT 0
estado - enum('activo', 'vencido', 'cerrado')
fecha_cierre - datetime (nullable)
notificacion_enviada - boolean DEFAULT false
observaciones - text (nullable)
created_at - timestamp
updated_at - timestamp

INDEX idx_proceso (proceso_tipo, proceso_id)
INDEX idx_estado (estado)
INDEX idx_vencimiento (fecha_vencimiento)
```

#### 11. `dias_no_habiles` (Festivos y No Hábiles)
```sql
id - bigint unsigned (PK)
fecha - date UNIQUE
descripcion - varchar(255)
tipo - enum('festivo', 'puente', 'especial')
recurrente - boolean DEFAULT false [Para festivos anuales]
created_at - timestamp
updated_at - timestamp

INDEX idx_fecha (fecha)
```

#### 12. `documentos` (Documentos Generados)
```sql
id - bigint unsigned (PK)
documentable_type - varchar(255) [Polymorphic: proceso, contrato, etc.]
documentable_id - bigint unsigned
tipo_documento - enum(
    'apertura_proceso',
    'acta_descargos',
    'analisis_juridico',
    'memorando_llamado',
    'memorando_suspension',
    'memorando_terminacion',
    'contrato_labor_obra',
    'decision_impugnacion'
)
nombre_archivo - varchar(255)
ruta_archivo - varchar(500)
formato - enum('pdf', 'docx')
generado_por - bigint unsigned (FK) [user_id]
version - int DEFAULT 1
plantilla_usada - varchar(255) (nullable)
variables_usadas - json (nullable) [Variables reemplazadas]
fecha_generacion - datetime
created_at - timestamp
updated_at - timestamp

INDEX idx_documentable (documentable_type, documentable_id)
INDEX idx_tipo (tipo_documento)
```

#### 13. `timeline` (Línea de Tiempo / Auditoría)
```sql
id - bigint unsigned (PK)
proceso_tipo - enum('proceso_disciplinario', 'contrato')
proceso_id - bigint unsigned
user_id - bigint unsigned (FK) [Quién realizó la acción]
accion - varchar(255) [Ej: 'Proceso aperturado', 'Descargos realizados']
descripcion - text (nullable)
estado_anterior - varchar(100) (nullable)
estado_nuevo - varchar(100) (nullable)
metadata - json (nullable) [Datos adicionales]
ip_address - varchar(45) (nullable)
user_agent - text (nullable)
created_at - timestamp

INDEX idx_proceso (proceso_tipo, proceso_id)
INDEX idx_user (user_id)
INDEX idx_created (created_at)
```

#### 14. `notificaciones` (Sistema de Notificaciones)
```sql
id - bigint unsigned (PK)
user_id - bigint unsigned (FK)
tipo - enum(
    'proceso_aperturado',
    'descargos_proximos',
    'termino_vencido',
    'sancion_aplicada',
    'impugnacion_recibida',
    'contrato_generado'
)
titulo - varchar(255)
mensaje - text
relacionado_tipo - varchar(255) (nullable) [Polymorphic]
relacionado_id - bigint unsigned (nullable)
leida - boolean DEFAULT false
fecha_lectura - datetime (nullable)
prioridad - enum('baja', 'media', 'alta', 'urgente') DEFAULT 'media'
created_at - timestamp
updated_at - timestamp

INDEX idx_user_leida (user_id, leida)
INDEX idx_tipo (tipo)
```

#### 15. `configuraciones` (Configuraciones del Sistema)
```sql
id - bigint unsigned (PK)
clave - varchar(255) UNIQUE
valor - text
tipo - enum('text', 'number', 'boolean', 'json')
descripcion - text (nullable)
categoria - varchar(100) [Ej: 'terminos', 'documentos', 'general']
editable - boolean DEFAULT true
created_at - timestamp
updated_at - timestamp

INDEX idx_categoria (categoria)
```

---

## 🔄 FLUJOS DE PROCESOS

### FLUJO 1: PROCESO DISCIPLINARIO COMPLETO

**Estados del Proceso:**
```
apertura → traslado → descargos_pendientes → descargos_realizados →
analisis_juridico → pendiente_gerencia → sancion_definida →
notificado → [impugnado (opcional)] → cerrado / archivado
```

**Paso a Paso:**

1. **APERTURA** (estado: `apertura`)
   - Empresa cliente envía solicitud
   - Se registra en `procesos_disciplinarios`
   - Se crea trabajador si no existe
   - Se asigna abogado
   - Se genera código único (PD-2025-0001)

2. **TRASLADO** (estado: `traslado`)
   - Sistema genera comunicación de apertura
   - Se programa fecha de descargos
   - Se activa control de término de 5 días hábiles
   - Se guarda documento en `documentos`
   - Se notifica a RRHH y trabajador

3. **DESCARGOS** (estado: `descargos_pendientes` → `descargos_realizados`)
   - Se registra diligencia en `diligencias_descargos`
   - Se capturan preguntas/respuestas
   - Se registran pruebas aportadas
   - Se genera acta
   - Se cierra término legal

4. **ANÁLISIS JURÍDICO** (estado: `analisis_juridico`)
   - Abogado registra análisis en `analisis_juridicos`
   - Decide: archivo o sanción
   - Si archivo → estado `archivado` (fin)
   - Si sanción → continúa proceso

5. **GERENCIA** (estado: `pendiente_gerencia`)
   - Se notifica a gerencia
   - Gerencia define tipo de sanción
   - Se registra en `sanciones`

6. **NOTIFICACIÓN** (estado: `sancion_definida` → `notificado`)
   - Se genera memorando según tipo
   - Se envía a RRHH
   - RRHH notifica a trabajador
   - Se registra fecha de notificación
   - Se activa término de impugnación (3 días hábiles)

7. **IMPUGNACIÓN (Opcional)** (estado: `impugnado`)
   - Si trabajador impugna, se registra en `impugnaciones`
   - Abogado analiza impugnación
   - Decide: confirmar, revocar o modificar
   - Se genera documento de decisión

8. **CIERRE** (estado: `cerrado`)
   - Proceso finaliza
   - Se cierra término legal
   - Se genera línea de tiempo completa
   - Historial disponible

---

### FLUJO 2: CONTRATO LABORAL POR LABOR U OBRA

**Estados:**
```
solicitado → en_analisis → revision_objeto → contrato_generado → enviado_rrhh → cerrado
```

**Paso a Paso:**

1. **SOLICITUD** (estado: `solicitado`)
   - Empresa envía solicitud con:
     - Datos del trabajador
     - Responsabilidades
     - Objeto comercial
     - Manual de funciones
     - Orden de compra
   - Se genera código (SC-2025-0001)

2. **ANÁLISIS** (estado: `en_analisis`)
   - Abogado asignado revisa:
     - Objeto comercial
     - Manual de funciones
     - Responsabilidades
   - Identifica elementos específicos

3. **REDACCIÓN OBJETO JURÍDICO** (estado: `revision_objeto`)
   - Abogado redacta objeto jurídico específico
   - Evita generalidades
   - Asegura cumplimiento legal
   - Puede solicitar más información

4. **GENERACIÓN CONTRATO** (estado: `contrato_generado`)
   - Sistema genera contrato con variables:
     - Datos trabajador
     - Datos empresa
     - Objeto específico redactado
     - Funciones detalladas
     - Fecha inicio, salario
   - Se guarda en `documentos`

5. **ENVÍO Y CIERRE** (estado: `enviado_rrhh` → `cerrado`)
   - Se envía a RRHH
   - RRHH finaliza proceso
   - Se registra cierre

---

## 📁 ESTRUCTURA DE ARCHIVOS LARAVEL

```
ces-legal/
├── app/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Empresa.php
│   │   ├── Trabajador.php
│   │   ├── ProcesoDisciplinario.php
│   │   ├── DiligenciaDescargo.php
│   │   ├── AnalisisJuridico.php
│   │   ├── Sancion.php
│   │   ├── Impugnacion.php
│   │   ├── SolicitudContrato.php
│   │   ├── TerminoLegal.php
│   │   ├── DiaNoHabil.php
│   │   ├── Documento.php
│   │   ├── Timeline.php
│   │   ├── Notificacion.php
│   │   └── Configuracion.php
│   │
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── ProcesoDisciplinarioResource.php
│   │   │   ├── SolicitudContratoResource.php
│   │   │   ├── EmpresaResource.php
│   │   │   ├── TrabajadorResource.php
│   │   │   ├── UserResource.php
│   │   │   └── ConfiguracionResource.php
│   │   │
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── ProcesosEnCurso.php
│   │   │   ├── TerminosVencidos.php
│   │   │   └── Reportes.php
│   │   │
│   │   └── Widgets/
│   │       ├── ProcesosPorEstadoWidget.php
│   │       ├── TerminosProximosWidget.php
│   │       └── ActividadRecienteWidget.php
│   │
│   ├── Services/
│   │   ├── TerminoLegalService.php
│   │   ├── DocumentoService.php
│   │   ├── NotificacionService.php
│   │   └── TimelineService.php
│   │
│   ├── Enums/
│   │   ├── EstadoProcesoDisciplinario.php
│   │   ├── TipoSancion.php
│   │   ├── EstadoSolicitudContrato.php
│   │   └── TipoTermino.php
│   │
│   └── Observers/
│       ├── ProcesoDisciplinarioObserver.php
│       └── SolicitudContratoObserver.php
│
├── database/
│   ├── migrations/
│   │   ├── 2025_12_17_000001_create_empresas_table.php
│   │   ├── 2025_12_17_000002_create_trabajadores_table.php
│   │   ├── 2025_12_17_000003_create_procesos_disciplinarios_table.php
│   │   ├── 2025_12_17_000004_create_diligencias_descargos_table.php
│   │   ├── 2025_12_17_000005_create_analisis_juridicos_table.php
│   │   ├── 2025_12_17_000006_create_sanciones_table.php
│   │   ├── 2025_12_17_000007_create_impugnaciones_table.php
│   │   ├── 2025_12_17_000008_create_solicitudes_contrato_table.php
│   │   ├── 2025_12_17_000009_create_terminos_legales_table.php
│   │   ├── 2025_12_17_000010_create_dias_no_habiles_table.php
│   │   ├── 2025_12_17_000011_create_documentos_table.php
│   │   ├── 2025_12_17_000012_create_timeline_table.php
│   │   ├── 2025_12_17_000013_create_notificaciones_table.php
│   │   └── 2025_12_17_000014_create_configuraciones_table.php
│   │
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php
│       ├── EmpresaSeeder.php
│       ├── TrabajadorSeeder.php
│       ├── DiaNoHabilSeeder.php [Festivos 2025-2026]
│       └── ConfiguracionSeeder.php
│
├── resources/
│   └── views/
│       └── documents/
│           └── templates/
│               ├── apertura_proceso.blade.php
│               ├── acta_descargos.blade.php
│               ├── memorando_llamado.blade.php
│               ├── memorando_suspension.blade.php
│               ├── memorando_terminacion.blade.php
│               └── contrato_labor_obra.blade.php
│
└── storage/
    └── app/
        ├── documentos/
        │   ├── procesos/
        │   └── contratos/
        └── plantillas/
```

---

## 🔧 SERVICIOS CLAVE

### 1. TerminoLegalService
```php
- calcularDiasHabiles(fecha_inicio, dias)
- validarVencimiento()
- activarTermino(proceso_id, tipo_termino)
- cerrarTermino(termino_id)
- obtenerDiasTranscurridos(termino_id)
```

### 2. DocumentoService
```php
- generarDocumento(tipo, data, plantilla)
- reemplazarVariables(plantilla, variables)
- convertirPDF(docx_path)
- almacenarDocumento(file, proceso)
- obtenerHistorialDocumentos(proceso_id)
```

### 3. NotificacionService
```php
- notificarUsuario(user_id, tipo, mensaje)
- notificarTerminoProximo(termino_id)
- notificarTerminoVencido(termino_id)
- marcarComoLeida(notificacion_id)
```

### 4. TimelineService
```php
- registrarEvento(proceso_id, accion, user_id, metadata)
- obtenerTimeline(proceso_id)
- exportarTimeline(proceso_id, formato)
```

---

## 🔐 SISTEMA DE ROLES Y PERMISOS

### Roles Definidos

1. **Super Admin**
   - Acceso total
   - Gestión de usuarios
   - Configuraciones del sistema

2. **Abogado**
   - Gestión de procesos disciplinarios
   - Creación de contratos
   - Análisis jurídicos
   - Generación de documentos

3. **Cliente (Empresa)**
   - Ver sus procesos
   - Crear solicitudes
   - Ver documentos generados

4. **RRHH**
   - Ver procesos de su empresa
   - Recibir documentos
   - Cerrar procesos

---

## 📊 DASHBOARDS

### Dashboard Principal (Abogado)
- Procesos activos (contador por estado)
- Términos próximos a vencer (alerta)
- Términos vencidos (alerta roja)
- Actividad reciente
- Gráfico de procesos por mes
- Pendientes de análisis

### Dashboard Cliente
- Mis procesos activos
- Estado de cada proceso
- Documentos generados
- Próximas diligencias

---

## 🚀 PRÓXIMOS PASOS DE IMPLEMENTACIÓN

1. ✅ Instalación Laravel + FilamentPHP
2. ⏳ Creación de migraciones
3. ⏳ Creación de modelos Eloquent
4. ⏳ Configuración de relaciones
5. ⏳ Creación de Resources Filament
6. ⏳ Implementación de Servicios
7. ⏳ Generación de documentos
8. ⏳ Testing y ajustes

---

## 📝 NOTAS IMPORTANTES

- **Días hábiles:** Sistema debe excluir sábados, domingos y festivos
- **Códigos únicos:** Generar automáticamente (PD-2025-0001, SC-2025-0001)
- **Soft deletes:** Usar en procesos y solicitudes
- **Auditoría completa:** Timeline registra TODAS las acciones
- **Documentos versionados:** Cada generación guarda versión
- **Notificaciones automáticas:** Por vencimiento de términos

---

**Fecha de Creación:** 17 de diciembre de 2025
**Creado por:** Claude Code (Arquitecto de Software)
**Versión:** 1.0
