---
title: Base de Datos
description: Modelo de datos y estructura de la base de datos de CES Legal
---

## Diagrama Entidad-Relación

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    EMPRESAS     │       │   TRABAJADORES  │       │     USERS       │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │──┐    │ id              │       │ id              │
│ nombre          │  │    │ nombre          │       │ name            │
│ nit             │  │    │ cedula          │       │ email           │
│ direccion       │  ├───▶│ cargo           │       │ empresa_id      │──┐
│ telefono        │  │    │ empresa_id      │◀──────│ role            │  │
│ email           │  │    │ fecha_ingreso   │       │ password        │  │
│ created_at      │  │    │ salario         │       └─────────────────┘  │
│ updated_at      │  │    │ created_at      │                            │
└─────────────────┘  │    │ deleted_at      │       ┌────────────────────┘
                     │    └─────────────────┘       │
                     │            │                 │
                     │            ▼                 │
                     │    ┌─────────────────────────┴───┐
                     │    │   PROCESOS_DISCIPLINARIOS   │
                     │    ├─────────────────────────────┤
                     │    │ id                          │
                     │    │ codigo (unique)             │
                     └───▶│ empresa_id                  │
                          │ trabajador_id               │◀──────────┐
                          │ abogado_id                  │           │
                          │ estado                      │           │
                          │ hechos                      │           │
                          │ articulos_incumplidos       │           │
                          │ pruebas_iniciales           │           │
                          │ fecha_apertura              │           │
                          │ created_at                  │           │
                          │ deleted_at                  │           │
                          └─────────────────────────────┘           │
                                      │                             │
                    ┌─────────────────┼─────────────────┐           │
                    ▼                 ▼                 ▼           │
        ┌───────────────────┐ ┌───────────────┐ ┌───────────────┐   │
        │DILIGENCIAS_DESCARGO│ │   SANCIONES   │ │   TIMELINES   │   │
        ├───────────────────┤ ├───────────────┤ ├───────────────┤   │
        │ id                │ │ id            │ │ id            │   │
        │ proceso_id        │ │ proceso_id    │ │ proceso_id    │   │
        │ fecha             │ │ tipo_sancion  │ │ evento        │   │
        │ hora              │ │ descripcion   │ │ descripcion   │   │
        │ modalidad         │ │ fecha_inicio  │ │ usuario_id    │   │
        │ lugar             │ │ fecha_fin     │ │ created_at    │   │
        │ token_acceso      │ │ created_at    │ └───────────────┘   │
        │ token_expira      │ └───────────────┘                     │
        │ estado            │                                       │
        │ created_at        │                                       │
        └───────────────────┘                                       │
                │                                                   │
                ▼                                                   │
        ┌───────────────────┐      ┌───────────────────┐           │
        │PREGUNTAS_DESCARGOS│      │RESPUESTAS_DESCARGOS│           │
        ├───────────────────┤      ├───────────────────┤           │
        │ id                │◀────▶│ id                │           │
        │ diligencia_id     │      │ pregunta_id       │           │
        │ pregunta          │      │ respuesta         │           │
        │ orden             │      │ created_at        │           │
        │ generada_por_ia   │      └───────────────────┘           │
        │ created_at        │                                       │
        └───────────────────┘                                       │
                                                                    │
        ┌───────────────────┐      ┌───────────────────┐           │
        │  ARTICULOS_LEGALES │      │ SANCIONES_LABORALES│           │
        ├───────────────────┤      ├───────────────────┤           │
        │ id                │      │ id                │           │
        │ codigo            │      │ nombre            │           │
        │ titulo            │      │ tipo              │           │
        │ descripcion       │      │ descripcion       │───────────┘
        │ created_at        │      │ created_at        │
        └───────────────────┘      └───────────────────┘
```

## Tablas Principales

### `procesos_disciplinarios`

Tabla central del sistema que almacena cada proceso disciplinario.

```sql
CREATE TABLE procesos_disciplinarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    empresa_id BIGINT UNSIGNED NOT NULL,
    trabajador_id BIGINT UNSIGNED NOT NULL,
    abogado_id BIGINT UNSIGNED NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'apertura',
    hechos TEXT NOT NULL,
    articulos_incumplidos JSON NULL,
    pruebas_iniciales JSON NULL,
    fecha_apertura DATE NOT NULL,
    fecha_cierre DATE NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    UNIQUE KEY unique_codigo_deleted (codigo, deleted_at),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id),
    FOREIGN KEY (abogado_id) REFERENCES users(id)
);
```

**Estados posibles:**
- `apertura`
- `descargos_pendientes`
- `descargos_realizados`
- `descargos_no_realizados`
- `sancion_emitida`
- `impugnacion_realizada`
- `cerrado`
- `archivado`

### `trabajadores`

Empleados que pueden ser sujetos de procesos disciplinarios.

```sql
CREATE TABLE trabajadores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    cedula VARCHAR(20) NOT NULL,
    cargo VARCHAR(255) NULL,
    area VARCHAR(255) NULL,
    fecha_ingreso DATE NULL,
    tipo_contrato VARCHAR(100) NULL,
    salario DECIMAL(15,2) NULL,
    email VARCHAR(255) NULL,
    telefono VARCHAR(50) NULL,
    direccion TEXT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    UNIQUE KEY unique_cedula_empresa (cedula, empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);
```

### `empresas`

Empresas clientes del sistema.

```sql
CREATE TABLE empresas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    nit VARCHAR(20) NOT NULL UNIQUE,
    direccion TEXT NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    representante_legal VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### `diligencias_descargos`

Sesiones programadas de descargos.

```sql
CREATE TABLE diligencias_descargos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proceso_disciplinario_id BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    modalidad ENUM('presencial', 'virtual', 'telefonica') NOT NULL,
    lugar VARCHAR(255) NULL,
    enlace_virtual VARCHAR(500) NULL,
    token_acceso VARCHAR(100) NULL UNIQUE,
    token_expira_at TIMESTAMP NULL,
    estado VARCHAR(50) DEFAULT 'programada',
    observaciones TEXT NULL,
    acta_generada BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (proceso_disciplinario_id) REFERENCES procesos_disciplinarios(id)
);
```

### `preguntas_descargos`

Preguntas para la diligencia de descargos.

```sql
CREATE TABLE preguntas_descargos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    diligencia_descargo_id BIGINT UNSIGNED NOT NULL,
    pregunta TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    tipo VARCHAR(50) DEFAULT 'abierta',
    generada_por_ia BOOLEAN DEFAULT FALSE,
    es_dinamica BOOLEAN DEFAULT FALSE,
    pregunta_padre_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (diligencia_descargo_id) REFERENCES diligencias_descargos(id),
    FOREIGN KEY (pregunta_padre_id) REFERENCES preguntas_descargos(id)
);
```

### `respuestas_descargos`

Respuestas del trabajador a las preguntas.

```sql
CREATE TABLE respuestas_descargos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pregunta_descargo_id BIGINT UNSIGNED NOT NULL,
    respuesta TEXT NOT NULL,
    archivos_adjuntos JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (pregunta_descargo_id) REFERENCES preguntas_descargos(id)
);
```

### `sanciones`

Sanciones emitidas en procesos disciplinarios.

```sql
CREATE TABLE sanciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proceso_disciplinario_id BIGINT UNSIGNED NOT NULL,
    sancion_laboral_id BIGINT UNSIGNED NOT NULL,
    descripcion TEXT NOT NULL,
    fundamento_legal TEXT NULL,
    fecha_notificacion DATE NULL,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    dias_suspension INT NULL,
    monto_descuento DECIMAL(15,2) NULL,
    recomendada_por_ia BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (proceso_disciplinario_id) REFERENCES procesos_disciplinarios(id),
    FOREIGN KEY (sancion_laboral_id) REFERENCES sanciones_laborales(id)
);
```

## Tablas de Catálogos

### `sanciones_laborales`

Catálogo de 63 tipos de sanciones laborales.

```sql
CREATE TABLE sanciones_laborales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('leve', 'grave', 'muy_grave') NOT NULL,
    descripcion TEXT NULL,
    dias_minimos INT NULL,
    dias_maximos INT NULL,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### `articulos_legales`

Artículos del Código Sustantivo del Trabajo.

```sql
CREATE TABLE articulos_legales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    norma VARCHAR(255) NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### `dias_no_habiles`

Festivos de Colombia.

```sql
CREATE TABLE dias_no_habiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    anio INT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_fecha (fecha)
);
```

## Tablas de Auditoría

### `timelines`

Historial de cambios de cada proceso.

```sql
CREATE TABLE timelines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proceso_disciplinario_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    evento VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,

    FOREIGN KEY (proceso_disciplinario_id) REFERENCES procesos_disciplinarios(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### `trazabilidad_ia_descargos`

Auditoría de llamadas a la IA.

```sql
CREATE TABLE trazabilidad_ia_descargos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    diligencia_descargo_id BIGINT UNSIGNED NOT NULL,
    tipo_operacion VARCHAR(50) NOT NULL,
    prompt_enviado TEXT NOT NULL,
    respuesta_ia TEXT NOT NULL,
    modelo_utilizado VARCHAR(100) NULL,
    tokens_utilizados INT NULL,
    tiempo_respuesta_ms INT NULL,
    exitoso BOOLEAN DEFAULT TRUE,
    error_mensaje TEXT NULL,
    created_at TIMESTAMP NULL,

    FOREIGN KEY (diligencia_descargo_id) REFERENCES diligencias_descargos(id)
);
```

### `email_trackings`

Seguimiento de apertura de emails.

```sql
CREATE TABLE email_trackings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proceso_disciplinario_id BIGINT UNSIGNED NOT NULL,
    tipo_email VARCHAR(50) NOT NULL,
    destinatario VARCHAR(255) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    estado ENUM('pendiente', 'entregado', 'leido') DEFAULT 'pendiente',
    enviado_at TIMESTAMP NULL,
    entregado_at TIMESTAMP NULL,
    leido_at TIMESTAMP NULL,
    ip_lectura VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (proceso_disciplinario_id) REFERENCES procesos_disciplinarios(id)
);
```

## Índices Importantes

```sql
-- Búsqueda rápida de procesos por empresa
CREATE INDEX idx_procesos_empresa ON procesos_disciplinarios(empresa_id);

-- Búsqueda rápida de trabajadores por cédula
CREATE INDEX idx_trabajadores_cedula ON trabajadores(cedula);

-- Búsqueda rápida de diligencias por fecha
CREATE INDEX idx_diligencias_fecha ON diligencias_descargos(fecha);

-- Búsqueda por token de acceso público
CREATE INDEX idx_diligencias_token ON diligencias_descargos(token_acceso);
```

## Relaciones del Modelo

```php
// ProcesoDisciplinario.php
class ProcesoDisciplinario extends Model
{
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    public function diligencias(): HasMany
    {
        return $this->hasMany(DiligenciaDescargo::class);
    }

    public function sanciones(): HasMany
    {
        return $this->hasMany(Sancion::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }
}
```

## Próximos Pasos

- [Servicios](/arquitectura/servicios/) - Lógica de negocio
- [Estados del Proceso](/flujo/estados-proceso/) - Máquina de estados
