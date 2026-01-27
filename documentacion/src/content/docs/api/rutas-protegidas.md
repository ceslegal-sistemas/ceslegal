---
title: Rutas Protegidas
description: Endpoints protegidos del panel de administracion Filament en CES Legal
---

## Descripcion General

Todas las rutas protegidas de CES Legal estan gestionadas por el panel de administracion **Filament 3.2**. Requieren autenticacion y autorizacion mediante el sistema de roles y permisos **Filament Shield** (basado en Spatie Permission).

El panel es accesible en `/admin` y utiliza el middleware de autenticacion de Filament junto con politicas (Policies) por recurso.

## Autenticacion

### Acceso al Panel

```
GET /admin/login
POST /admin/login
POST /admin/logout
```

Solo los usuarios con los roles `super_admin`, `abogado` o `cliente` pueden acceder al panel:

```php
// app/Models/User.php
public function canAccessPanel(Panel $panel): bool
{
    return in_array($this->role, ['super_admin', 'abogado', 'cliente']);
}
```

### Roles del Sistema

| Rol | Codigo | Alcance |
|-----|--------|---------|
| Administrador | `super_admin` | Acceso total a todos los recursos y empresas |
| Abogado | `abogado` | Gestion de procesos asignados, todas las empresas |
| Cliente (RRHH) | `cliente` | Solo datos de su empresa asignada (`empresa_id`) |

---

## Recursos CRUD de Filament

Cada recurso genera automaticamente las siguientes rutas:

```
GET    /admin/{recurso}              # Listado (index)
GET    /admin/{recurso}/create       # Formulario de creacion
POST   /admin/{recurso}              # Guardar nuevo registro
GET    /admin/{recurso}/{id}         # Ver detalle
GET    /admin/{recurso}/{id}/edit    # Formulario de edicion
PUT    /admin/{recurso}/{id}         # Actualizar registro
DELETE /admin/{recurso}/{id}         # Eliminar registro
```

### 1. Procesos Disciplinarios

```
/admin/proceso-disciplinarios
```

| Atributo | Valor |
|----------|-------|
| Recurso | `ProcesoDisciplinarioResource` |
| Modelo | `ProcesoDisciplinario` |
| Icono | `heroicon-o-shield-exclamation` |
| Etiqueta | Historial de Descargos |
| Orden de navegacion | 1 |

**Paginas disponibles:**
- `ListProcesoDisciplinarios` - Listado con filtros por estado, empresa, abogado
- `CreateProcesoDisciplinario` - Creacion con seleccion de empresa, trabajador, hechos, articulos legales y sanciones laborales
- `EditProcesoDisciplinario` - Edicion con acciones contextuales segun estado

**Navegacion personalizada:**
Este recurso genera dos items de navegacion:
1. **Crear Descargos** (`/admin/proceso-disciplinarios/create`) - Acceso directo a creacion
2. **Historial de Descargos** (`/admin/proceso-disciplinarios`) - Listado completo

**Acciones de tabla:**
- Generar citacion (PDF/DOCX)
- Enviar citacion por email
- Generar preguntas con IA
- Programar diligencia de descargos
- Emitir sancion
- Archivar proceso
- Ver tracking de email

**Multi-tenancy:**
```php
// Clientes solo ven procesos de su empresa
if (auth()->user()->isCliente()) {
    $query->where('empresa_id', auth()->user()->empresa_id);
}
```

---

### 2. Trabajadores

```
/admin/trabajadores
```

| Atributo | Valor |
|----------|-------|
| Recurso | `TrabajadorResource` |
| Modelo | `Trabajador` |
| Icono | `heroicon-o-user-group` |
| Etiqueta | Trabajadores |
| Orden de navegacion | 2 |

**Paginas disponibles:**
- `ListTrabajadors` - Listado con busqueda por nombre, documento, empresa
- `CreateTrabajador` - Creacion con datos personales y laborales
- `ViewTrabajador` - Vista de detalle
- `EditTrabajador` - Edicion de datos

**Campos del formulario:**
- Empresa (autoasignada para clientes)
- Tipo y numero de documento (CC, CE, TI, PASS)
- Genero (masculino/femenino)
- Nombres y apellidos
- Departamento y ciudad de nacimiento (1,122 municipios de Colombia)
- Correo electronico
- Cargo (lista predefinida de 36 cargos + opcion personalizada)
- Area (lista predefinida de 23 areas + opcion personalizada)
- Estado activo/inactivo

**Acciones de tabla:**
- Ver, Editar
- Activar/Desactivar trabajador
- Acciones masivas de activacion/desactivacion

---

### 3. Empresas

```
/admin/empresas
```

| Atributo | Valor |
|----------|-------|
| Recurso | `EmpresaResource` |
| Modelo | `Empresa` |
| Icono | `heroicon-o-building-office-2` |
| Etiqueta | Empresas |
| Grupo de navegacion | Administracion |
| Orden de navegacion | 2 |

**Paginas disponibles:**
- `ListEmpresas` - Listado con badge de empresas activas
- `CreateEmpresa` - Creacion con datos completos
- `ViewEmpresa` - Vista de detalle
- `EditEmpresa` - Edicion (restringida para clientes a su propia empresa)

**Campos del formulario:**
- Razon social
- NIT (con mascara `999999999-9`)
- Representante legal
- Estado activa/inactiva
- Telefono, email de contacto, direccion
- Departamento y ciudad (32 departamentos de Colombia)

**Protecciones:**
- No se puede eliminar una empresa con procesos disciplinarios asociados
- No se puede eliminar una empresa con trabajadores asociados
- Los clientes solo pueden editar su propia empresa

---

### 4. Diligencias de Descargos

```
/admin/diligencia-descargos
```

| Atributo | Valor |
|----------|-------|
| Recurso | `DiligenciaDescargoResource` |
| Modelo | `DiligenciaDescargo` |
| Icono | `heroicon-o-document-text` |
| Etiqueta | Descargos |
| Grupo de navegacion | Gestion Laboral |
| Orden de navegacion | 2 |

**Paginas disponibles:**
- `ListDiligenciaDescargos` - Listado con estado de preguntas y respuestas
- `CreateDiligenciaDescargo` - Programacion de diligencia
- `ViewDiligenciaDescargo` - Vista completa con archivos de evidencia
- `EditDiligenciaDescargo` - Edicion de datos

**Modalidades de descargos:**
- **Virtual** - El trabajador responde por internet (acceso con token y timer de 45 min)

**Acciones de tabla:**
- Generar preguntas con IA (2 preguntas + estandar + cierre)
- Ver link de acceso del trabajador
- Generar acta de descargos (DOCX)
- Descargar acta
- Regenerar token de acceso
- Regenerar acta

---

### 5. Usuarios

```
/admin/users
```

| Atributo | Valor |
|----------|-------|
| Recurso | `UserResource` |
| Modelo | `User` |
| Icono | `heroicon-o-users` |
| Etiqueta | Usuarios |
| Grupo de navegacion | Administracion |
| Orden de navegacion | 1 |

**Paginas disponibles:**
- `ListUsers` - Listado con filtros por rol y empresa
- `CreateUser` - Creacion con asignacion de rol y empresa
- `ViewUser` - Vista de detalle
- `EditUser` - Edicion (contrasena opcional)

**Campos del formulario:**
- Nombre completo (auto-genera email sugerido)
- Correo electronico
- Rol (super_admin, abogado, cliente)
- Empresa asignada (solo para rol cliente)
- Estado activo/inactivo
- Contrasena (minimo 8 caracteres, con confirmacion)

---

### 6. Sanciones Laborales (Catalogo)

```
/admin/sancion-laborals
```

| Atributo | Valor |
|----------|-------|
| Recurso | `SancionLaboralResource` |
| Modelo | `SancionLaboral` |
| Icono | `heroicon-o-scale` |
| Etiqueta | Sanciones Laborales |
| Grupo de navegacion | Configuracion |
| Orden de navegacion | 4 |

**Descripcion:**
Catalogo de 63 tipos de sanciones laborales predefinidas, clasificadas por tipo de falta (leve/grave) y tipo de sancion (llamado de atencion, suspension, terminacion).

**Campos del formulario:**
- Tipo de falta (leve / grave)
- Nombre claro (descripcion corta)
- Descripcion completa
- Tipo de sancion (llamado de atencion, suspension, terminacion)
- Dias de suspension minimos/maximos (si aplica)
- Estado activa/inactiva

---

### 7. Articulos Legales (Catalogo)

```
/admin/articulo-legals
```

| Atributo | Valor |
|----------|-------|
| Recurso | `ArticuloLegalResource` |
| Modelo | `ArticuloLegal` |
| Grupo de navegacion | Configuracion |

**Descripcion:**
Catalogo de articulos del Codigo Sustantivo del Trabajo aplicables a procesos disciplinarios.

---

### 8. Solicitudes de Contrato

```
/admin/solicitud-contratos
```

| Atributo | Valor |
|----------|-------|
| Recurso | `SolicitudContratoResource` |
| Modelo | `SolicitudContrato` |

---

### 9. Disponibilidad de Abogados

```
/admin/disponibilidad-abogados
```

| Atributo | Valor |
|----------|-------|
| Recurso | `DisponibilidadAbogadoResource` |
| Modelo | `DisponibilidadAbogado` |

**Descripcion:**
Gestion de horarios disponibles de los abogados para programacion de diligencias de descargos.

**Campos:**
- Abogado asignado
- Fecha y horario (hora inicio/fin)
- Tipo (presencial, virtual, ambos)
- Estado de disponibilidad
- Proceso asociado (si ya esta ocupado)
- Notas adicionales

---

## Autorizacion por Politicas (Policies)

Cada recurso tiene una politica asociada gestionada por **Filament Shield**:

```
app/Policies/
  ProcesoDisciplinarioPolicy.php
  TrabajadorPolicy.php
  EmpresaPolicy.php
  DiligenciaDescargoPolicy.php
  UserPolicy.php
  SancionLaboralPolicy.php
  ArticuloLegalPolicy.php
  SolicitudContratoPolicy.php
  DisponibilidadAbogadoPolicy.php
  ... (10 policies)
```

### Permisos por Accion

Cada politica define los siguientes permisos:

| Permiso | Descripcion |
|---------|-------------|
| `view_any` | Listar registros |
| `view` | Ver detalle de un registro |
| `create` | Crear nuevo registro |
| `update` | Editar registro existente |
| `delete` | Eliminar registro |
| `delete_any` | Eliminar en lote |
| `restore` | Restaurar registro eliminado (soft delete) |
| `restore_any` | Restaurar en lote |
| `force_delete` | Eliminar permanentemente |
| `force_delete_any` | Eliminar permanentemente en lote |

### Matriz de Permisos por Rol

| Recurso | super_admin | abogado | cliente |
|---------|:-----------:|:-------:|:-------:|
| Procesos Disciplinarios | Todos | Ver/Editar asignados | Ver de su empresa |
| Trabajadores | Todos | Ver todos | CRUD de su empresa |
| Empresas | Todos | Ver todas | Ver su empresa |
| Diligencias | Todos | Gestionar asignadas | Ver de su empresa |
| Usuarios | Todos | No acceso | No acceso |
| Sanciones Laborales | Todos | Solo lectura | No acceso |
| Articulos Legales | Todos | Solo lectura | No acceso |

---

## Filtrado Multi-Tenant

El sistema implementa filtrado automatico por empresa para usuarios con rol `cliente`:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    $user = auth()->user();

    if ($user->isCliente()) {
        return $query->where('empresa_id', $user->empresa_id);
    }

    return $query;
}
```

**Recursos con filtrado multi-tenant:**
- Procesos Disciplinarios
- Trabajadores
- Empresas
- Diligencias de Descargos

:::caution[Seguridad Multi-Tenant]
El filtrado se aplica a nivel de Eloquent query, lo que garantiza que un cliente nunca pueda ver ni acceder a datos de otra empresa, incluso manipulando URLs directamente.
:::

---

## Widgets del Dashboard

El panel principal (`/admin`) incluye widgets segun el rol del usuario:

| Widget | super_admin | abogado | cliente |
|--------|:-----------:|:-------:|:-------:|
| Resumen de procesos por estado | Si | Si (asignados) | Si (su empresa) |
| Procesos pendientes | Si | Si | Si |
| Calendario de diligencias | Si | Si | No |
| Usuarios activos | Si | No | No |

---

## Archivos Relacionados

```
app/Filament/Admin/Resources/
  ProcesoDisciplinarioResource.php
  TrabajadorResource.php
  EmpresaResource.php
  DiligenciaDescargoResource.php
  UserResource.php
  SancionLaboralResource.php
  ArticuloLegalResource.php
  SolicitudContratoResource.php
  DisponibilidadAbogadoResource.php

app/Policies/
  (10 archivos de politicas)

app/Providers/
  Filament/AdminPanelProvider.php
```

## Proximos Pasos

- [Rutas Publicas](/api/rutas-publicas/) - Endpoints sin autenticacion
- [Webhooks](/api/webhooks/) - Integraciones externas
- [Manual Administrador](/manuales/administrador/) - Guia de uso para administradores
