# Diseño — Bufetes (multi-tenant) para CES Legal

- **Fecha:** 2026-07-06
- **Estado:** Diseño aprobado (v1, pendiente de afinar antes de implementar)
- **Autor:** juanparen15 + Claude
- **Rama de trabajo prevista:** nueva (`feat/bufetes`), no `figma-rebrand`

## 1. Problema / objetivo

Hoy el sistema tiene dos audiencias por rol:
- `cliente` (RRHH): atado a **una** empresa (`users.empresa_id`); ve solo lo suyo.
- `abogado` / `super_admin`: ven **todas** las empresas (scope global).

Se quiere soportar un **bufete de abogados**: una firma con uno o varios abogados
que gestiona un **conjunto** de empresas (no una, no todas) y co-gestiona sus
descargos, RIT, auditoría y trabajadores. En el registro, el usuario debe elegir
si es **empresa** o **bufete**.

## 2. Requisitos (decididos en brainstorming)

| Tema | Decisión |
|------|----------|
| Acceso empresa vs bufete | **Coexisten**: la empresa conserva su login cliente (autogestión) y el bufete co-gestiona. |
| Vinculación empresa↔bufete | **Ambas**: el bufete crea empresas nuevas **y** puede invitar empresas ya registradas. |
| Cardinalidad | **Exclusivo**: una empresa pertenece a lo sumo a **un** bufete (`empresas.bufete_id`). |
| Abogados dentro del bufete | **Todos ven todas** las empresas del bufete. |
| Permisos del cliente con bufete | Reportar/consultar, crear descargos y **emitir sanción**. El bufete controla lo jurídico pesado (generar/auditar RIT). |
| Mecánica multi-tenant | **Bufete + `bufete_id` + global scopes + selector de empresa** en el topbar (persistido en sesión). NO Filament tenancy nativo. |

## 3. Enfoque elegido (y descartados)

**Elegido — Modelo `Bufete` + scopes + selector de empresa.** Poco invasivo,
mantiene el panel actual, bajo riesgo para el despliegue Hostinger (solo
migraciones, sin build npm).

Descartados:
- *Filament multi-tenancy nativo* (tenant = Empresa, URLs `/admin/{empresa}/…`):
  refactor grande de todos los resources y del panel; hay que remapear el flujo
  cliente actual (1 empresa). Más riesgo.
- *Ver todo mezclado + columna Empresa*: simple pero satura con muchas empresas y
  sin foco por empresa.

## 4. Modelo de datos

- **`bufetes`**: `id, nombre, nit (nullable, unique), representante, email_contacto,
  telefono, active, timestamps`.
- **`empresas.bufete_id`** (nullable FK → bufetes). `Empresa belongsTo Bufete`,
  `Bufete hasMany Empresa`.
- **`users.bufete_id`** (nullable FK → bufetes). Abogado de bufete: `role='abogado'`,
  `bufete_id` seteado, `empresa_id` null. `Bufete hasMany User (abogados)`.
- **`bufete_invitaciones`**: `id, bufete_id, nit (o email), token, estado
  (pendiente/aceptada/rechazada/expirada), expires_at, timestamps`.

**Distinción staff plataforma vs bufete cliente** (backward-compat):
- `super_admin` → admin global de la plataforma (sin cambios).
- `abogado` con `bufete_id = null` → staff interno CES Legal (ve todo, como hoy).
- `abogado` con `bufete_id` seteado → abogado de un bufete cliente (ve solo su bufete).

## 5. Registro (elección empresa vs bufete)

- Paso 0 nuevo: **"Soy una empresa"** vs **"Soy un bufete de abogados"**.
- **Empresa** → wizard actual sin cambios (`Empresa` + `User(cliente)`, `bufete_id`
  null).
- **Bufete** → wizard corto: datos del bufete + cuenta del abogado dueño → crea
  `Bufete` + `User(role=abogado, bufete_id)` → aterriza en el panel del bufete.
- Ambos flujos envueltos en `DB::transaction` (ver también el hallazgo de auditoría
  sobre `handleRegistration` sin transacción).

## 6. Alta de empresas bajo el bufete (ambas vías)

- **Crear nueva:** resource "Empresas" (scopeado al bufete) → `Empresa` con
  `bufete_id` del bufete actual. Opción "dar acceso al RRHH" que crea/invita un
  `User(cliente)` para esa empresa.
- **Invitar existente:** acción "Invitar empresa" por NIT/email → busca `Empresa`
  auto-registrada (`bufete_id` null) → envía invitación al cliente → al aceptar se
  setea `empresa.bufete_id`. Guard de exclusividad: si ya tiene bufete, se rechaza.

## 7. Alcance de datos (scopes + selector)

- **Trait `ScopedToBufeteOrEmpresa`** aplicado a modelos con `empresa_id`: `Empresa`,
  `Trabajador`, `ProcesoDisciplinario`, `ReglamentoInterno`, `SolicitudContrato`, etc.
  (verificar la lista exacta en implementación).
  - `super_admin` / `abogado(bufete_id null)` → sin filtro (todo).
  - `cliente` → `empresa_id = user.empresa_id`.
  - `abogado(bufete)` → `empresa_id IN (empresas del bufete)`, acotado a la empresa
    activa del selector si hay una.
- **Selector "Empresa" en el topbar** (render-hook, sesión `bufete_empresa_activa`):
  empresas del bufete + "Todas". El cliente no lo ve.

## 8. Permisos (Shield + policies)

- `cliente`: reportar/consultar, crear descargos, **emitir sanción** (como hoy).
- `abogado(bufete)`: todo lo del cliente sobre **todas** las empresas del bufete +
  **generar/auditar RIT** + gestionar empresas/trabajadores del bufete + gestionar
  los abogados del bufete.
- Ajuste de gates existentes en `ProcesoDisciplinarioResource` / páginas RIT:
  **generar/auditar RIT** → solo `abogado`/`super_admin`; **emitir sanción** → sigue
  permitido también a `cliente`.

## 9. Migración / compatibilidad

- Columnas nuevas **nullable** → cero impacto en filas actuales (`bufete_id` null =
  sin bufete = comportamiento de hoy).
- Empresas, clientes, `abogado` internos y `super_admin` existentes: sin cambios.
- Todo **aditivo**; convive con el flujo actual. Solo migraciones (sin npm) →
  desplegable en Hostinger.

## 10. Pruebas clave

- Scopes: cada rol ve lo suyo; el selector acota; `super_admin` ve todo.
- Registro en ambas vías (empresa / bufete).
- Invitar empresa: aceptar / rechazar / expirar; guard de exclusividad.
- Gates de permisos: cliente emite sanción pero no genera RIT; abogado sí.

## 11. Pendiente de afinar (antes de implementar)

- Trato definitivo de los `abogado` internos actuales (¿migrar a `super_admin` o
  dejar como staff global?).
- ¿El bufete tiene RIT/plantillas propias reutilizables entre sus empresas?
- Facturación/planes: ¿la suscripción se cobra al bufete o por empresa?
- Notificaciones: ¿a quién llegan (bufete, cliente, ambos)?
- Auditoría/trazabilidad: registrar qué actor (bufete vs cliente) hizo cada acción.
