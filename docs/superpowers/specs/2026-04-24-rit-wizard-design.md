# RIT Builder — Wizard Filament (Diseño)

**Fecha:** 2026-04-24
**Estado:** Aprobado

---

## Contexto

El constructor de RIT actual es una Filament Page (`RITBuilder.php`) con un formulario blade
manual de 10 secciones colapsables. Los problemas son:

- Los campos de actividad económica no cargan de la BD.
- Los campos condicionales (ej. sucursales) siempre están visibles.
- La sección de jerarquía de cargos pide al usuario escribir con "flechitas".
- No tiene el UX de wizard por pasos como `CreateProcesoDisciplinario`.

---

## Arquitectura

### Enfoque elegido: CreateRecord + HasWizard sobre ReglamentoInterno

```
/admin/reglamentos-internos/create   (nuevo wizard)
  └── CreateReglamentoInterno extends CreateRecord
        uses HasWizard
        7 steps → handleRecordCreation() guarda ReglamentoInterno + genera DOCX
```

**Archivos nuevos:**
- `app/Filament/Admin/Resources/ReglamentoInternoResource.php`
- `app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php`

**Archivos a modificar:**
- `app/Filament/Admin/Pages/Dashboard.php` (view) → apuntar al nuevo wizard
- `app/Filament/Admin/Pages/Auth/Register.php` → apuntar al nuevo wizard
- `app/Filament/Admin/Resources/RitBuilderResource.php` → ocultar del nav (`shouldRegisterNavigation = false`)
- `app/Filament/Admin/Pages/RITBuilder.php` → mantener solo para descarga, ocultar del nav

**Sin cambios en:**
- `app/Models/ReglamentoInterno.php` (ya tiene fillable/casts correctos)
- `app/Services/RITGeneratorService.php` (ya implementado)
- `database/migrations/...reglamentos_internos` (ya tiene columnas necesarias)

---

## Wizard — 7 Steps

### Step 1: Empresa y Actividad Económica

Campos pre-llenados del `Empresa` del usuario (disabled):
- `razon_social` (TextInput, disabled)
- `nit` (TextInput, disabled)
- `domicilio` = `{direccion} {ciudad}, {departamento}` (TextInput, disabled)

Campos editables:
- `actividad_economica_id` → Select con relationship `ActividadEconomica`, searchable por código/nombre
- `actividades_secundarias` → Select multiple, misma fuente
- `num_trabajadores` → TextInput numérico, required
- `tiene_sucursales` → Radio (Sí/No), live()
  - Si Sí → Repeater `sucursales`: `{ciudad: TextInput, num_trabajadores: TextInput}`, addActionLabel "Agregar sucursal"

> Los campos de actividad se guardan en `respuestas_cuestionario` como texto formateado
> (código + nombre) ya que el modelo ReglamentoInterno no tiene FK a actividades.

---

### Step 2: Estructura y Contratos

- `cargos` → Repeater: `{nombre_cargo: TextInput required, puede_sancionar: Toggle}`
  Label del repeater: "Cargos de la empresa"
  AddActionLabel: "Agregar cargo"
  HelperText: "Liste los cargos que existen. Marque los que tienen facultad disciplinaria."
- `tiene_manual_funciones` → Select (Sí / No / En construcción)
- `tipos_contrato` → CheckboxList
  Opciones: Término indefinido / Término fijo / Obra o labor / Aprendizaje SENA
- `tiene_trabajadores_mision` → Radio (Sí/No)

---

### Step 3: Jornada Laboral

- `horario_entrada` → TextInput, required, placeholder "8:00 a.m."
- `horario_salida` → TextInput, required, placeholder "5:00 p.m."
- `trabaja_sabados` → Radio (No / Sí, media jornada / Sí, jornada completa), live()
  - Si Sí → `horario_salida_sabado`: TextInput, placeholder "1:00 p.m."
- `trabaja_dominicales` → Radio (No / Ocasionalmente / Regularmente)
- `tiene_turnos` → Radio (Sí/No), live()
  - Si Sí → `descripcion_turnos`: Textarea, rows 2
- `control_asistencia` → Select (Biométrico / Planilla manual / App móvil / Sin control formal)
- `politica_horas_extras` → Select
  Opciones: Se pagan con recargo legal / No se autorizan / Compensatorio en tiempo

---

### Step 4: Salario y Beneficios

- `forma_pago` → Select required (Transferencia bancaria / Cheque / Efectivo / Mixto)
- `periodicidad_pago` → Select (Mensual / Quincenal / Semanal)
- `maneja_comisiones` → Radio (Sí/No), live()
  - Si Sí → `tipo_comisiones`: Select (Comisiones de ventas / Bonos por desempeño / Ambos)
- `tiene_beneficios_extralegales` → Radio (Sí/No), live()
  - Si Sí → Repeater `beneficios_extralegales`: `{descripcion: TextInput}`, addActionLabel "Agregar beneficio"
- `politica_permisos` → Select
  Opciones: Solicitud escrita con 24h anticipación / Sin política formal
- `tiene_licencias_especiales` → Radio (Sí/No), live()
  - Si Sí → `descripcion_licencias`: Textarea, rows 2, placeholder "Ej: Licencia de matrimonio 1 día remunerado"

---

### Step 5: Régimen Disciplinario

- `faltas_leves` → TagsInput, sugerencias predefinidas:
  ["Impuntualidad", "No registrar asistencia", "No usar uniforme", "Uso de celular en horario"]
- `faltas_graves` → TagsInput, sugerencias:
  ["Agresión verbal", "Ausentismo sin justificación", "Incumplir normas de seguridad"]
- `faltas_muy_graves` → TagsInput, sugerencias:
  ["Hurto", "Agresión física", "Acoso sexual", "Divulgación de secretos"]
- `sanciones_contempladas` → CheckboxList
  Opciones: Llamado de atención verbal / Llamado de atención escrito / Suspensión 1-3 días / Suspensión 4-8 días / Terminación con justa causa

---

### Step 6: SST y Conducta

- `tiene_sg_sst` → Select (Sí, implementado / En proceso / No)
- `riesgos_principales` → CheckboxList
  Opciones: Ergonómico / Psicosocial / Mecánico / Eléctrico / Público / Otro, live()
  - Si Otro → `riesgos_otros`: TextInput
- `tiene_epp` → Radio (No aplica — oficina / Sí, requeridos), live()
  - Si Sí → `epp_descripcion`: TextInput, placeholder "Casco, guantes, botas de seguridad"
- `politica_celular` → Select (Libre uso / Solo en descansos / Prohibido salvo emergencias)
- `usa_uniforme` → Radio (No / Sí, uniforme completo / Sí, dotación básica)
- `tiene_codigo_etica` → Radio (Sí / No / En construcción)
- `politica_confidencialidad` → Select (Sí, por contrato / Solo verbal / No)
- `que_quiere_prevenir` → Textarea, optional, rows 2

---

### Step 7: Revisión y Generar

- `Placeholder` con resumen de todo lo respondido (secciones colapsables HTML, read-only)
- Botón **"Construir Reglamento Interno con IA"** → dispara `handleRecordCreation()`

**`handleRecordCreation(array $data)`:**

> Nota: Filament intentará guardar cada campo como columna. Se debe **sobreescribir**
> `handleRecordCreation()` para interceptar los datos y serializar todo en el JSON.

1. Extrae `$empresa` del usuario autenticado
2. Construye array `$respuestas` con todos los valores de `$data` (campos del formulario)
3. Resuelve `actividad_economica_id` → texto formateado `"{codigo} - {nombre}"`
4. Llama `RITGeneratorService::generarTextoRIT($respuestas, $empresa)` → `$textoRIT`
5. Crea/actualiza `ReglamentoInterno::updateOrCreate(['empresa_id' => $empresa->id], [
     'nombre'                  => 'RIT generado con IA — ' . now()->format('d/m/Y'),
     'texto_completo'          => $textoRIT,
     'respuestas_cuestionario' => $respuestas,
     'fuente'                  => 'construido_ia',
     'activo'                  => true,
   ])`
6. Llama `RITGeneratorService::generarDocumentoWord($textoRIT, $empresa)`
7. Retorna el registro creado
8. `getRedirectUrl()` → ruta del dashboard con notificación de éxito

---

## ReglamentoInternoResource

```php
// Solo página de creación habilitada
public static function getPages(): array
{
    return [
        'create' => Pages\CreateReglamentoInterno::route('/create'),
    ];
}

// Oculto del menú lateral (acceso solo por enlace directo)
protected static bool $shouldRegisterNavigation = false;
```

El recurso existe solo para registrar la ruta del wizard. No tiene listado ni edit.

---

## Cambios en links existentes

| Archivo | Campo | Cambio |
|---------|-------|--------|
| `dashboard.blade.php` | Link "Construir RIT" | `filament.admin.resources.reglamentos-internos.create` |
| `Register.php` | `$this->redirectUrl` | Misma ruta |
| `RitBuilderResource.php` | Nav | `$shouldRegisterNavigation = false` |
| `RITBuilder.php` (Page) | Nav | Mantener para descarga directa, ocultar del nav principal |

---

## Campos de ReglamentoInterno

Los datos del wizard se serializan como un array asociativo plano en `respuestas_cuestionario`.
Las claves del array coinciden con los nombres de campo del formulario (snake_case).

Para actividad económica, se guarda el texto formateado `"{codigo} - {nombre}"` porque
`ReglamentoInterno` no tiene FK — el `RITGeneratorService` los recibe como texto en el prompt.

---

## Fuera del alcance

- Edición del RIT generado desde el wizard (el usuario puede regenerar completando el wizard de nuevo)
- Vista de listado de RITs históricos
- Guardado de borrador intermedio (el formulario se llena en una sola sesión)
