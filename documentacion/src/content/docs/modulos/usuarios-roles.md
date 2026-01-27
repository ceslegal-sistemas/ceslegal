---
title: Usuarios y Roles
description: Sistema de autenticacion, autorizacion y gestion de roles con Filament Shield en CES Legal
---

## Descripcion General

El modulo de **Usuarios y Roles** gestiona la autenticacion, autorizacion y control de acceso en CES Legal. Utiliza **Filament Shield** (basado en Spatie Permission) para la gestion granular de permisos, combinado con **Policies** de Laravel para la autorizacion a nivel de modelo.

El sistema define tres roles principales con alcances de acceso diferenciados, soporta multi-tenancy por empresa y permite el cambio de contrasena personalizado.

## Caracteristicas Principales

### Autenticacion

El modelo `User` extiende `Authenticatable` e implementa `FilamentUser`:

```php
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['super_admin', 'abogado', 'cliente']);
    }
}
```

Solo los usuarios con roles `super_admin`, `abogado` o `cliente` pueden acceder al panel Filament. El campo `active` permite desactivar usuarios sin eliminarlos.

### Roles del Sistema

#### super_admin

| Aspecto | Detalle |
|---------|---------|
| **Alcance** | Acceso total al sistema |
| **Empresas** | Todas las empresas |
| **Procesos** | Todos los procesos disciplinarios |
| **Trabajadores** | Todos los trabajadores |
| **Acciones especiales** | Crear empresas, gestionar usuarios, configurar roles |
| **empresa_id** | null (no vinculado a empresa) |

#### abogado

| Aspecto | Detalle |
|---------|---------|
| **Alcance** | Procesos asignados y analisis con IA |
| **Empresas** | Las empresas de los procesos asignados |
| **Procesos** | Solo los asignados como `abogado_id` |
| **Trabajadores** | Los de sus procesos asignados |
| **Acciones especiales** | Emitir sanciones, generar analisis IA, enviar citaciones |
| **empresa_id** | Puede estar vinculado a una empresa o ser null |

#### cliente

| Aspecto | Detalle |
|---------|---------|
| **Alcance** | Solo datos de su empresa |
| **Empresas** | Solo su propia empresa |
| **Procesos** | Solo los de su empresa |
| **Trabajadores** | Solo los de su empresa |
| **Acciones especiales** | Crear procesos, registrar trabajadores |
| **empresa_id** | Obligatorio (vinculado a su empresa) |

:::note[Rol RRHH]
El rol `rrhh` fue unificado con `cliente`. Los usuarios que antes tenian rol RRHH ahora son clientes. El metodo `isRRHH()` esta deprecated y redirige a `isCliente()`.
:::

### Filament Shield

Filament Shield genera automaticamente permisos CRUD para cada recurso de Filament:

```
view_proceso_disciplinario
view_any_proceso_disciplinario
create_proceso_disciplinario
update_proceso_disciplinario
delete_proceso_disciplinario
delete_any_proceso_disciplinario
force_delete_proceso_disciplinario
force_delete_any_proceso_disciplinario
restore_proceso_disciplinario
restore_any_proceso_disciplinario
```

Estos permisos se asignan a cada rol segun la configuracion en el panel de Shield.

### Policies de Autorizacion

El sistema cuenta con **10 policies** que controlan la autorizacion a nivel de modelo:

| Policy | Modelo | Descripcion |
|--------|--------|-------------|
| `ProcesoDisciplinarioPolicy` | ProcesoDisciplinario | Acceso a procesos |
| `TrabajadorPolicy` | Trabajador | Acceso a trabajadores |
| `EmpresaPolicy` | Empresa | Acceso a empresas |
| `DiligenciaDescargoPolicy` | DiligenciaDescargo | Acceso a diligencias |
| `SancionLaboralPolicy` | SancionLaboral | Acceso a catalogo de sanciones |
| `ArticuloLegalPolicy` | ArticuloLegal | Acceso a articulos legales |
| `SolicitudContratoPolicy` | SolicitudContrato | Acceso a solicitudes de contrato |
| `UserPolicy` | User | Gestion de usuarios |
| `RolePolicy` | Role | Gestion de roles |

Adicionalmente existen policies de seguridad del sistema:

| Policy | Descripcion |
|--------|-------------|
| `ActivityLogPolicy` | Acceso al log de actividad |
| `FileIntegrityCheckPolicy` | Verificacion de integridad de archivos |
| `MalwareDetectionPolicy` | Deteccion de malware |
| `SecurityAlertPolicy` | Alertas de seguridad |

### Multi-tenancy por Empresa

La restriccion de datos por empresa se implementa en cada Resource de Filament:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (auth()->user()->hasRole('cliente')) {
        return $query->where('empresa_id', auth()->user()->empresa_id);
    }

    return $query;
}
```

Esto garantiza que:

- Los usuarios `cliente` solo ven datos de su empresa.
- Los usuarios `super_admin` ven todo.
- Los usuarios `abogado` ven los procesos asignados (filtrado adicional por `abogado_id`).

### Cambio de Contrasena

El sistema incluye funcionalidad personalizada de cambio de contrasena accesible desde el perfil del usuario en el panel Filament.

## Modelo de Datos

### Tabla: `users`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `name` | string | Nombre completo del usuario |
| `email` | string | Correo electronico (unico) |
| `email_verified_at` | timestamp | Verificacion de email |
| `password` | string | Contrasena hasheada |
| `role` | string | Rol principal (super_admin, abogado, cliente) |
| `empresa_id` | foreignId (nullable) | Empresa vinculada |
| `active` | boolean | Estado activo/inactivo |
| `remember_token` | string | Token de sesion |

### Casts

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
    ];
}
```

### Tablas de Spatie Permission

| Tabla | Descripcion |
|-------|-------------|
| `roles` | Roles del sistema |
| `permissions` | Permisos individuales |
| `model_has_roles` | Asignacion de roles a usuarios |
| `model_has_permissions` | Asignacion directa de permisos |
| `role_has_permissions` | Permisos asignados a roles |

## Relaciones con Otros Modulos

| Relacion | Tipo | Modelo Relacionado | Descripcion |
|----------|------|--------------------|-------------|
| `empresa` | BelongsTo | Empresa | Empresa vinculada |
| `procesosDisciplinariosAsignados` | HasMany | ProcesoDisciplinario | Procesos como abogado |
| `solicitudesContratoAsignadas` | HasMany | SolicitudContrato | Solicitudes como abogado |
| `notificaciones` | HasMany | Notificacion | Notificaciones recibidas |

### Relaciones Indirectas

- **Timeline**: El usuario aparece como actor en los registros de timeline (quien realizo la accion).
- **Documentos**: El usuario aparece como `generado_por` en los documentos.
- **Analisis Juridicos**: El abogado aparece como `abogado_id` en los analisis.
- **Sanciones**: El usuario aparece como `notificado_por` en las sanciones.

## Notas de Uso

### Creacion de Usuarios

Al crear un usuario se debe considerar:

1. **Rol**: Determina el alcance de acceso.
2. **Empresa**: Obligatorio para `cliente`, opcional para `abogado`, null para `super_admin`.
3. **Estado**: Los usuarios inactivos no pueden acceder al panel.
4. **Email**: Debe ser unico en el sistema.

### Metodos de Verificacion de Rol

```php
$user->isAdmin();    // true si role === 'admin'
$user->isAbogado();  // true si role === 'abogado'
$user->isCliente();  // true si role === 'cliente'
$user->isRRHH();     // @deprecated - redirige a isCliente()
```

### Notificaciones por Rol

Las notificaciones se envian automaticamente segun el rol:

- **Abogado**: Recibe notificaciones de procesos asignados, descargos, sanciones e impugnaciones.
- **Cliente**: Recibe notificaciones de descargos completados, sanciones aplicadas y procesos cerrados de su empresa.
- **super_admin**: No recibe notificaciones automaticas (puede consultar todas manualmente).

### Seguridad de Contrasenas

- Las contrasenas se hashean automaticamente con el cast `hashed` de Laravel.
- Se utiliza el algoritmo bcrypt por defecto.
- El token `remember_token` permite sesiones persistentes.

### Desactivacion de Usuarios

Cuando un usuario se desactiva (`active = false`):

- No puede iniciar sesion en el panel.
- Sus procesos asignados permanecen asignados.
- Sus notificaciones se mantienen.
- Puede ser reactivado en cualquier momento.

### Permisos Especificos por Accion

Ademas de los permisos CRUD estandar generados por Shield, existen acciones especificas que requieren permisos adicionales:

| Accion | Permiso Requerido | Descripcion |
|--------|-------------------|-------------|
| Enviar citacion | `update_proceso_disciplinario` | Requiere poder editar el proceso |
| Emitir sancion | `update_proceso_disciplinario` | Requiere poder editar el proceso |
| Generar analisis IA | `update_proceso_disciplinario` | Requiere poder editar el proceso |
| Gestionar roles | `view_any_role` + Shield | Solo super_admin |
| Crear empresas | `create_empresa` | Solo super_admin |

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Modulo principal
- [Empresas](/modulos/empresas/) - Gestion de empresas
- [Notificaciones](/modulos/notificaciones/) - Sistema de notificaciones
