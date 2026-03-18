---
title: Empresas
description: Modulo de gestion de empresas que sirve como base del multi-tenancy en CES Legal
---

## Descripcion General

El modulo de **Empresas** gestiona las organizaciones registradas en CES Legal. Cada empresa es la unidad base del sistema multi-tenant: los trabajadores, procesos disciplinarios, usuarios y documentos estan vinculados a una empresa especifica. El modelo `Empresa` es la raiz del arbol de datos de cada cliente.

La informacion de la empresa se utiliza directamente en la generacion de documentos legales (citaciones, sanciones) donde se requieren datos como razon social, NIT, direccion y representante legal.

## Caracteristicas Principales

### CRUD Completo
Permite crear, consultar, actualizar y eliminar empresas con toda su informacion comercial y de contacto.

### Base Multi-tenant
La empresa es el eje del aislamiento de datos. Cada entidad principal del sistema tiene una relacion `empresa_id` que determina la visibilidad:

```
Empresa
  |-- Trabajadores (empresa_id)
  |-- Procesos Disciplinarios (empresa_id)
  |-- Usuarios (empresa_id)
  |-- Solicitudes de Contrato (empresa_id)
```

Los usuarios con rol `cliente` solo pueden ver datos de su propia empresa. Los roles `super_admin` y `abogado` pueden acceder a datos de todas las empresas.

### Estado Activo/Inactivo
El campo `active` permite desactivar empresas sin eliminarlas. Una empresa inactiva no permite crear nuevos procesos, pero los procesos existentes siguen siendo accesibles.

### Informacion para Documentos Legales
Los datos de la empresa se interpolan automaticamente en las plantillas de documentos:

- **Citacion a descargos**: Razon social, NIT, ciudad, departamento, representante legal.
- **Documento de sancion**: Razon social, NIT, representante legal.
- **Correos electronicos**: Nombre de la empresa como contexto.

## Modelo de Datos

### Tabla: `empresas`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `razon_social` | string | Razon social de la empresa |
| `nit` | string | Numero de Identificacion Tributaria |
| `direccion` | string | Direccion fisica de la empresa |
| `telefono` | string | Telefono de contacto |
| `email_contacto` | string | Correo electronico de contacto |
| `ciudad` | string | Ciudad donde opera |
| `departamento` | string | Departamento (division administrativa colombiana) |
| `representante_legal` | string | Nombre del representante legal |
| `active` | boolean | Estado activo/inactivo |

### Casts

```php
protected $casts = [
    'active' => 'boolean',
];
```

## Relaciones con Otros Modulos

| Relacion | Tipo | Modelo Relacionado | Descripcion |
|----------|------|--------------------|-------------|
| `trabajadores` | HasMany | `Trabajador` | Empleados de la empresa |
| `procesosDisciplinarios` | HasMany | `ProcesoDisciplinario` | Procesos disciplinarios |
| `solicitudesContrato` | HasMany | `SolicitudContrato` | Solicitudes de contratos laborales |
| `usuarios` | HasMany | `User` | Usuarios del sistema vinculados |


## Notas de Uso

### Datos Requeridos para Operacion

Para que la generacion de documentos funcione correctamente, se requieren al minimo los siguientes campos:

| Campo | Requerido para |
|-------|---------------|
| `razon_social` | Todos los documentos y correos |
| `nit` | Citaciones y sanciones |
| `representante_legal` | Documentos de sancion (firma) |
| `direccion` | Dato registrado de la empresa (no interpolado en citaciones virtuales) |
| `ciudad` | Citaciones y sanciones |
| `departamento` | Citaciones |

:::caution[Datos Incompletos]
Si la empresa no tiene `representante_legal` registrado, el documento de sancion usara el texto "Representante Legal" como placeholder. Se recomienda completar todos los campos.
:::

### Permisos por Rol

- **super_admin**: Puede crear, editar y eliminar cualquier empresa. Acceso total.
- **abogado**: Puede ver las empresas de los procesos que tiene asignados. No puede crear ni eliminar empresas.
- **cliente**: Solo puede ver la informacion de su propia empresa. Puede editar datos basicos.

### Policy de Autorizacion

La autorizacion esta controlada por `EmpresaPolicy`, que valida:

- Permisos CRUD segun el rol del usuario.
- Acceso restringido a la empresa propia para usuarios con rol `cliente`.

### Consideraciones de Integridad

- Una empresa no puede ser eliminada si tiene trabajadores, procesos o usuarios vinculados.
- Al desactivar una empresa, los procesos en curso continuan su flujo normal.
- Los usuarios `cliente` de una empresa inactiva pueden seguir accediendo al sistema para consultar procesos existentes.

### Uso en Citaciones Virtuales

La modalidad activa es **virtual**. Las citaciones incluyen la ciudad y departamento de la empresa como referencia geografica, pero no la direccion fisica (que solo aplica en modalidad presencial, actualmente desactivada en la UI).

## Proximos Pasos

- [Trabajadores](/modulos/trabajadores/) - Gestion de empleados
- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Gestion de procesos
- [Usuarios y Roles](/modulos/usuarios-roles/) - Gestion de acceso
