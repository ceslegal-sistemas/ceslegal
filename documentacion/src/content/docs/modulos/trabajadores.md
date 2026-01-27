---
title: Trabajadores
description: Modulo de gestion de trabajadores vinculados a empresas en CES Legal
---

## Descripcion General

El modulo de **Trabajadores** permite gestionar la informacion de los empleados de cada empresa registrada en el sistema. Los trabajadores son los sujetos de los procesos disciplinarios y a quienes se les envian las citaciones, se les programan diligencias de descargos y se les notifican las sanciones.

Este modulo esta filtrado por empresa (`empresa_id`), de manera que cada usuario solo puede ver los trabajadores de su propia empresa (multi-tenancy). Adicionalmente, los trabajadores pueden ser creados de forma inline directamente desde el formulario de creacion de un proceso disciplinario, agilizando el flujo de trabajo.

## Caracteristicas Principales

### CRUD Completo
El recurso permite crear, leer, actualizar y eliminar trabajadores con todos sus datos personales, laborales y de contacto.

### Filtrado Multi-tenant
Los trabajadores estan automaticamente filtrados por la empresa del usuario autenticado cuando el rol es `cliente`. Los roles `super_admin` y `abogado` pueden ver trabajadores de todas las empresas.

```php
// Filtrado automatico en el Resource
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (auth()->user()->hasRole('cliente')) {
        return $query->where('empresa_id', auth()->user()->empresa_id);
    }

    return $query;
}
```

### Creacion Inline
Desde el formulario de creacion de un proceso disciplinario, se puede crear un nuevo trabajador sin salir del formulario, utilizando un modal de Filament. Esto permite registrar rapidamente un trabajador que aun no existe en el sistema.

### Estado Activo/Inactivo
Cada trabajador tiene un campo `active` (booleano) que permite marcarlo como activo o inactivo. Los trabajadores inactivos no aparecen en los selectores de nuevos procesos pero se mantienen vinculados a procesos existentes.

### Atributo Nombre Completo
El modelo proporciona un accessor `nombre_completo` que concatena nombres y apellidos:

```php
public function getNombreCompletoAttribute(): string
{
    return "{$this->nombres} {$this->apellidos}";
}
```

Este atributo se utiliza en toda la aplicacion para mostrar el nombre del trabajador en tablas, formularios, documentos y correos electronicos.

## Modelo de Datos

### Tabla: `trabajadores`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `empresa_id` | foreignId | Empresa a la que pertenece |
| `tipo_documento` | string | Tipo de documento de identidad (CC, CE, TI, etc.) |
| `numero_documento` | string | Numero de documento de identidad |
| `genero` | string | Genero del trabajador |
| `nombres` | string | Nombres del trabajador |
| `apellidos` | string | Apellidos del trabajador |
| `departamento_nacimiento` | string | Departamento de nacimiento |
| `ciudad_nacimiento` | string | Ciudad de nacimiento |
| `cargo` | string | Cargo que desempenia en la empresa |
| `area` | string | Area o departamento organizacional |
| `fecha_ingreso` | date | Fecha de ingreso a la empresa |
| `email` | string | Correo electronico del trabajador |
| `telefono` | string | Numero de telefono |
| `direccion` | string | Direccion de residencia |
| `active` | boolean | Estado activo/inactivo |

### Casts

```php
protected $casts = [
    'fecha_ingreso' => 'date',
    'active' => 'boolean',
];
```

## Relaciones con Otros Modulos

| Relacion | Tipo | Modelo Relacionado | Descripcion |
|----------|------|--------------------|-------------|
| `empresa` | BelongsTo | `Empresa` | Empresa empleadora |
| `procesosDisciplinarios` | HasMany | `ProcesoDisciplinario` | Procesos donde es sujeto |

Relaciones indirectas a traves de procesos disciplinarios:

- **Diligencias de descargos**: A traves de `ProcesoDisciplinario -> DiligenciaDescargo`.
- **Sanciones**: A traves de `ProcesoDisciplinario -> Sancion`.
- **Documentos**: A traves de `ProcesoDisciplinario -> Documento` (polimorficos).
- **Email Tracking**: A traves de `ProcesoDisciplinario -> EmailTracking`.

## Notas de Uso

### Tipos de Documento Soportados

Los tipos de documento de identidad disponibles en Colombia son:

| Codigo | Nombre |
|--------|--------|
| CC | Cedula de Ciudadania |
| CE | Cedula de Extranjeria |
| TI | Tarjeta de Identidad |
| PA | Pasaporte |
| NIT | Numero de Identificacion Tributaria |
| PEP | Permiso Especial de Permanencia |
| PPT | Permiso de Proteccion Temporal |

### Campos Obligatorios para Procesos

Para que un trabajador pueda ser vinculado a un proceso disciplinario, se requiere como minimo:

- `nombres` y `apellidos`: Para identificar al trabajador en documentos.
- `numero_documento`: Para la identificacion legal en documentos oficiales.
- `cargo`: Para contexto en la citacion y sancion.
- `email`: Para el envio de citaciones y notificaciones de sancion.

:::caution[Correo Electronico]
Si el trabajador no tiene correo electronico registrado, no se podra enviar la citacion a descargos ni la notificacion de sancion por correo. El sistema lanzara una excepcion al intentar enviar.
:::

### Historial de Procesos

Desde la vista de detalle de un trabajador, se puede consultar el historial completo de procesos disciplinarios, incluyendo:

- Procesos activos y cerrados
- Sanciones aplicadas previamente
- Estado de cada proceso

Este historial es utilizado por el servicio `IAAnalisisSancionService` para evaluar la reincidencia al momento de recomendar una sancion.

### Permisos por Rol

- **super_admin**: Acceso completo a todos los trabajadores de todas las empresas.
- **abogado**: Puede ver trabajadores de las empresas a las que tiene procesos asignados.
- **cliente**: Solo puede ver y gestionar los trabajadores de su propia empresa.

### Policy de Autorizacion

La autorizacion esta controlada por `TrabajadorPolicy`, que valida:

- Que el usuario tenga permiso para el recurso.
- Que el trabajador pertenezca a la empresa del usuario (para rol `cliente`).

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Gestion de procesos
- [Empresas](/modulos/empresas/) - Gestion de empresas
- [Diligencias de Descargos](/modulos/diligencias-descargos/) - Formulario de descargos
