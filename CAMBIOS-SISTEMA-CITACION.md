# 📋 Cambios Implementados en el Sistema de Citación

## ✅ Resumen General

Se han implementado exitosamente todas las mejoras solicitadas para el sistema de citación automática de procesos disciplinarios:

1. **Sistema de Artículos Legales**: Selección de artículos del CST antes de generar citación
2. **Unificación de Roles**: Roles RRHH y Cliente unificados en un solo rol "Cliente"
3. **Dos Opciones de Generación**: Separación entre "Generar Documento" y "Enviar Citación"
4. **Integración con Plantilla Real**: Uso de la plantilla oficial del sistema

---

## 1️⃣ Sistema de Artículos Legales

### Base de Datos

**Tabla `articulos_legales`**:
- `id`: Identificador único
- `codigo`: Código del artículo (ej: "Art. 58", "Art. 60 Num. 1")
- `titulo`: Título descriptivo del artículo
- `descripcion`: Descripción completa del artículo
- `categoria`: Categoría (Obligaciones, Prohibiciones, etc.)
- `activo`: Si el artículo está activo o no
- `orden`: Orden de visualización

**Campo en `procesos_disciplinarios`**:
- `articulos_legales_ids`: JSON array con los IDs de artículos seleccionados

### Modelo ArticuloLegal

Archivo: `app/Models/ArticuloLegal.php`

Características:
- Scope `activos()`: Filtra solo artículos activos
- Scope `ordenado()`: Ordena por campo orden y código
- Accessor `texto_completo`: Devuelve "Art. XX - Título"

### Seeder Inicial

Archivo: `database/seeders/ArticulosLegalesSeeder.php`

Artículos iniciales cargados:
- Art. 58: Obligaciones especiales del trabajador
- Art. 60: Prohibiciones a los trabajadores
- Art. 60 Num. 1 al 8: Prohibiciones específicas

**Total**: 10 artículos precargados

### Selector en el Formulario

Ubicación: `ProcesoDisciplinarioResource.php` líneas 401-417

Características:
- Selector múltiple con búsqueda
- Solo visible para roles `super_admin` y `abogado`
- Precarga todos los artículos activos
- Muestra código y título completo
- Helper text explicativo

### Integración en el Modelo ProcesoDisciplinario

Archivo: `app/Models/ProcesoDisciplinario.php`

Nuevos métodos:
- `getArticulosLegalesAttribute()`: Devuelve colección de artículos seleccionados
- `getArticulosLegalesTextoAttribute()`: Devuelve texto formateado "Art. 58, Art. 60 Num. 1"

Casts:
- `articulos_legales_ids` → `array`

---

## 2️⃣ Unificación de Roles RRHH → Cliente

### Cambios en el Modelo User

Archivo: `app/Models/User.php`

- Método `isRRHH()` marcado como `@deprecated`
- Ahora devuelve `$this->isCliente()`
- Mantiene compatibilidad con código existente

### Cambios en UserResource

Archivo: `app/Filament/Admin/Resources/UserResource.php`

**Selector de roles** (líneas 73-79):
- Eliminada opción 'rrhh'
- Opción 'cliente' actualizada: "Cliente - Visualiza procesos de su empresa y gestiona personal"
- Solo 3 roles: `super_admin`, `abogado`, `cliente`

**Validación empresa_id** (línea 92):
```php
->required(fn (Get $get) => in_array($get('role'), ['abogado', 'cliente']))
```

**Tabla de usuarios**:
- Colores eliminados para 'rrhh'
- Iconos eliminados para 'rrhh'
- FormatStateUsing sin caso 'rrhh'

**Filtros**:
- Opciones de filtro sin 'rrhh'

### Migración de Datos

Archivo: `database/migrations/2025_12_22_200907_update_rrhh_role_to_cliente.php`

- Convierte todos los usuarios con rol 'rrhh' a 'cliente'
- Ejecutado exitosamente

---

## 3️⃣ Dos Opciones de Generación

### Botón 1: Generar Documento (Solo Revisar)

Ubicación: `ProcesoDisciplinarioResource.php` líneas 618-655

Características:
- **Icono**: 📄 `heroicon-o-document-text`
- **Color**: Azul (info)
- **Label**: "Generar Documento"
- **Función**: Solo genera el documento para revisión
- **Descarga**: Permite descargar el documento generado
- **Visibilidad**:
  - Requiere `fecha_descargos_programada`
  - Solo para roles `super_admin` y `abogado`

### Botón 2: Enviar Citación (Generar y Enviar)

Ubicación: `ProcesoDisciplinarioResource.php` líneas 657-694

Características:
- **Icono**: ✉️ `heroicon-o-paper-airplane`
- **Color**: Verde (success)
- **Label**: "Enviar Citación"
- **Función**: Genera documento Y lo envía por email
- **Notificación**: Muestra email del trabajador en el modal
- **Visibilidad**:
  - Requiere `fecha_descargos_programada`
  - Requiere `trabajador->email`
  - Solo para roles `super_admin` y `abogado`

---

## 4️⃣ Actualización del DocumentGeneratorService

Archivo: `app/Services/DocumentGeneratorService.php`

### Nuevas Variables en la Plantilla

**Variable agregada**: `ARTICULOS_LEGALES`

Línea 50:
```php
$articulosLegalesTexto = $proceso->articulos_legales_texto ?? 'No especificado';
```

Línea 70:
```php
'ARTICULOS_LEGALES' => $articulosLegalesTexto,
```

### Formato de Salida

Ejemplo de texto generado:
```
"Art. 58, Art. 60 Num. 1, Art. 60 Num. 3"
```

Si no hay artículos seleccionados:
```
"No especificado"
```

### Mejora en Fecha de Ocurrencia

Líneas 42-44:
```php
$fechaOcurrencia = $proceso->fecha_ocurrencia
    ? Carbon::parse($proceso->fecha_ocurrencia)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
    : 'Por definir';
```

Ahora maneja correctamente cuando `fecha_ocurrencia` es null.

---

## 5️⃣ Plantilla Real Integrada

### Ubicación de la Plantilla

**Archivo original**:
```
C:\laragon\www\ces-legal\storage\app\public\FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx
```

**Copiada a**:
```
C:\laragon\www\ces-legal\FORMATO A CITACIÓN A DESCARGOS-GENERAL-19 DE DICIEMBRE DE 2025.docx
```

**Tamaño**: 1.5 MB

### Variables Disponibles en la Plantilla

Todas estas variables pueden ser utilizadas en la plantilla Word usando el formato `${NOMBRE_VARIABLE}`:

**Empresa**:
- `${EMPRESA_NOMBRE}`
- `${EMPRESA_NIT}`

**Trabajador**:
- `${TRABAJADOR_NOMBRE}`
- `${TRABAJADOR_DOCUMENTO}`
- `${TRABAJADOR_CARGO}`
- `${TRABAJADOR_AREA}`
- `${TRABAJADOR_EMAIL}`

**Proceso**:
- `${CODIGO_PROCESO}`
- `${FECHA_SOLICITUD}`
- `${FECHA_HECHOS}`
- `${FECHA_DESCARGOS}`
- `${MODALIDAD_DESCARGOS}`

**Contenido**:
- `${HECHOS}`
- `${ANTECEDENTES}`
- `${NORMAS_INCUMPLIDAS}`
- `${ARTICULOS_LEGALES}` ← **NUEVA**
- `${IDENTIFICACION_PERJUICIO}`

**Fecha Actual**:
- `${DIA_ACTUAL}`
- `${MES_ACTUAL}`
- `${ANIO_ACTUAL}`

---

## 📊 Migraciones Ejecutadas

1. ✅ `2025_12_22_200013_create_articulos_legales_table.php`
2. ✅ `2025_12_22_200023_add_articulos_legales_to_procesos_disciplinarios.php`
3. ✅ `2025_12_22_200907_update_rrhh_role_to_cliente.php`

### Seeder Ejecutado

1. ✅ `ArticulosLegalesSeeder` - 10 artículos cargados

---

## 🎯 Flujo de Trabajo Completo

### Paso 1: Crear Proceso Disciplinario

1. Cliente registra el proceso con toda la información
2. **Abogado o Super Admin** selecciona los artículos legales incumplidos
3. Se programa la fecha de descargos
4. Se selecciona la modalidad (Presencial, Virtual, Telefónico)

### Paso 2: Generar Documento (Opcional)

1. Abogado/Super Admin hace clic en "📄 Generar Documento"
2. Sistema genera el documento con todas las variables reemplazadas
3. Documento se descarga automáticamente
4. Abogado revisa la correspondencia de información

### Paso 3: Enviar Citación

1. Abogado/Super Admin hace clic en "📧 Enviar Citación"
2. Sistema confirma el email del trabajador
3. Se genera el documento
4. Se envía por email al trabajador
5. Se registra en el timeline del proceso:
   - "Documento generado: Citación a descargos"
   - "Notificación enviada a [email]"

---

## 🔒 Permisos y Visibilidad

### Selector de Artículos Legales
- **Visible para**: `super_admin`, `abogado`
- **Oculto para**: `cliente`

### Botones de Generación
- **Visible para**: `super_admin`, `abogado`
- **Oculto para**: `cliente`

### Requisitos para Generar
- ✅ `fecha_descargos_programada` debe existir
- ✅ Para enviar: `trabajador->email` debe existir

---

## 📝 Archivos Modificados

### Modelos
1. `app/Models/User.php` - Método isRRHH() deprecado
2. `app/Models/ProcesoDisciplinario.php` - Campo y accessors para artículos
3. `app/Models/ArticuloLegal.php` - Modelo nuevo

### Recursos Filament
1. `app/Filament/Admin/Resources/UserResource.php` - Sin rol rrhh
2. `app/Filament/Admin/Resources/ProcesoDisciplinarioResource.php` - Selector y botones

### Servicios
1. `app/Services/DocumentGeneratorService.php` - Variable ARTICULOS_LEGALES

### Base de Datos
1. `database/migrations/2025_12_22_200013_create_articulos_legales_table.php`
2. `database/migrations/2025_12_22_200023_add_articulos_legales_to_procesos_disciplinarios.php`
3. `database/migrations/2025_12_22_200907_update_rrhh_role_to_cliente.php`
4. `database/seeders/ArticulosLegalesSeeder.php`

---

## 🎉 Resultado Final

El sistema ahora permite:

1. ✅ Selección de artículos legales específicos del CST
2. ✅ Un solo rol "Cliente" con funcionalidades unificadas
3. ✅ Opción de generar documento para revisar antes de enviar
4. ✅ Opción de enviar citación directamente
5. ✅ Integración completa con la plantilla oficial
6. ✅ Trazabilidad completa en el timeline
7. ✅ Permisos adecuados por rol

---

## 💡 Próximos Pasos Sugeridos

1. **Agregar más artículos legales** según los proporcionados por el usuario
2. **Personalizar la plantilla Word** con el formato exacto deseado
3. **Probar con un proceso real** desde la interfaz de Filament
4. **Configurar SMTP real** si se desea enviar emails reales
5. **Instalar LibreOffice** en producción para conversión a PDF

---

*Implementado el 22 de diciembre de 2025*
