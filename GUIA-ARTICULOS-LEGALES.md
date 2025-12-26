# 📖 Guía de Uso: Artículos Legales

## ✅ Recurso Creado Exitosamente

Se ha creado el recurso **ArticuloLegalResource** para que el super_admin pueda gestionar los artículos legales del Código Sustantivo del Trabajo desde la interfaz de Filament.

---

## 🔐 Acceso

**Solo el rol `super_admin` puede acceder a este recurso.**

### Ubicación en el Menú

- **Grupo**: Configuración
- **Nombre**: Artículos Legales
- **Icono**: ⚖️ Balanza (scale)
- **Badge**: Muestra el número de artículos activos

---

## 📋 Funcionalidades

### 1. Crear Artículo Legal

**Formulario organizado en 2 secciones:**

#### Sección 1: Información del Artículo
- **Código del Artículo** (requerido)
  - Ejemplo: "Art. 58", "Art. 60 Num. 1"
  - Máximo 50 caracteres
  - Icono: #

- **Categoría** (opcional)
  - Opciones disponibles:
    - Obligaciones del Trabajador
    - Prohibiciones al Trabajador
    - Derechos del Trabajador
    - Faltas Disciplinarias
    - Sanciones
    - Otros
  - Selector con búsqueda

- **Título del Artículo** (requerido)
  - Ejemplo: "Obligaciones especiales del trabajador"
  - Máximo 255 caracteres

- **Descripción Completa** (requerido)
  - Descripción detallada del contenido del artículo
  - Textarea de 4 líneas

#### Sección 2: Configuración
- **Orden de Visualización**
  - Número entero (0 o mayor)
  - Default: 0
  - Menor número = aparece primero en el selector

- **¿Artículo Activo?**
  - Toggle switch
  - Default: Activo (true)
  - Solo artículos activos aparecen en el selector de procesos

---

## 📊 Tabla de Artículos

### Columnas Visibles

1. **Código**: Con icono, copiable, ordenable
2. **Título**: Truncado a 50 chars con tooltip completo
3. **Categoría**: Badge con colores e iconos:
   - 🔴 Prohibiciones (rojo)
   - ⚠️ Faltas (amarillo)
   - ✅ Obligaciones (verde)
   - ℹ️ Derechos (azul)
   - 🛡️ Sanciones (morado)
   - 📄 Otros (gris)
4. **Estado**: Icono de check o X (activo/inactivo)
5. **Orden**: Número para ordenar
6. **Creado**: Fecha de creación (oculta por defecto)
7. **Actualizado**: Fecha de última actualización (oculta por defecto)

### Orden Predeterminado
Los artículos se ordenan automáticamente por el campo "orden" en orden ascendente.

---

## 🔍 Filtros Disponibles

### 1. Filtro por Categoría
- Selector múltiple
- Permite filtrar por una o varias categorías a la vez

### 2. Filtro por Estado
- Ternario (3 opciones):
  - Todos
  - Solo activos
  - Solo inactivos

---

## ⚡ Acciones Individuales

Cada artículo tiene 3 acciones disponibles:

### 1. Activar/Desactivar
- **Botón dinámico**:
  - Si está activo: muestra "Desactivar" (rojo)
  - Si está inactivo: muestra "Activar" (verde)
- Requiere confirmación
- Cambia el estado con un solo clic
- Muestra notificación de éxito

### 2. Editar
- Abre el formulario de edición
- Permite modificar todos los campos

### 3. Eliminar
- Requiere confirmación
- Elimina permanentemente el artículo
- **⚠️ Precaución**: Si el artículo está siendo usado en procesos disciplinarios, puede causar errores

---

## 📦 Acciones en Lote

Puedes seleccionar varios artículos y realizar acciones en lote:

### 1. Activar Seleccionados
- Icono: ✅ Check verde
- Activa todos los artículos seleccionados de una vez
- Muestra notificación con cantidad de artículos activados

### 2. Desactivar Seleccionados
- Icono: ❌ X rojo
- Desactiva todos los artículos seleccionados
- Muestra notificación con cantidad de artículos desactivados

### 3. Eliminar Seleccionados
- Requiere confirmación
- Elimina todos los artículos seleccionados
- **⚠️ Usar con precaución**

---

## 🎨 Características Especiales

### Badge en el Menú
El ícono del menú muestra un badge verde con el número total de artículos activos.

### Estado Vacío
Cuando no hay artículos registrados, se muestra:
- Mensaje: "No hay artículos legales registrados"
- Descripción: "Comience agregando artículos del Código Sustantivo del Trabajo"
- Botón: "Crear primer artículo"

### Búsqueda
Puedes buscar artículos por:
- Código
- Título
- Categoría

### Tabla Responsive
- Diseño de rayas alternadas (striped)
- Columnas toggleables (puedes mostrar/ocultar columnas)
- Tooltip en el título si es muy largo

---

## 💡 Ejemplos de Uso

### Ejemplo 1: Crear un Artículo de Obligaciones

**Datos a ingresar:**
- Código: `Art. 58`
- Categoría: `Obligaciones del Trabajador`
- Título: `Obligaciones especiales del trabajador`
- Descripción: `Son obligaciones especiales del trabajador: 1) Realizar personalmente la labor...`
- Orden: `1`
- Activo: ✅ Sí

**Resultado**: Aparecerá en el selector con badge verde y será el primero (orden 1).

### Ejemplo 2: Crear un Artículo de Prohibiciones

**Datos a ingresar:**
- Código: `Art. 60 Num. 3`
- Categoría: `Prohibiciones al Trabajador`
- Título: `Presentarse en estado de embriaguez`
- Descripción: `Presentarse al trabajo en estado de embriaguez o bajo la influencia de narcóticos o drogas enervantes`
- Orden: `5`
- Activo: ✅ Sí

**Resultado**: Aparecerá en el selector con badge rojo y en posición 5.

### Ejemplo 3: Desactivar Temporalmente un Artículo

1. Localiza el artículo en la tabla
2. Haz clic en el botón "Desactivar" (rojo)
3. Confirma la acción
4. El artículo ya NO aparecerá en el selector de procesos disciplinarios
5. Permanece en la tabla pero con icono de X rojo

---

## 🔄 Flujo de Trabajo Recomendado

### 1. Preparación Inicial
1. Como super_admin, accede a "Artículos Legales"
2. Crea todos los artículos del CST que usarás frecuentemente
3. Asigna números de orden lógicos (1, 2, 3...)
4. Categoriza correctamente cada artículo

### 2. Uso Diario
1. Los abogados/super_admin crean procesos disciplinarios
2. En el formulario, seleccionan los artículos legales correspondientes
3. Los artículos se incluyen automáticamente en la citación

### 3. Mantenimiento
1. Si un artículo ya no se usa, desactívalo (no lo elimines)
2. Si necesitas actualizar la descripción, edita el artículo
3. Ajusta los órdenes si necesitas reorganizar el selector

---

## 📝 Artículos Precargados

Actualmente hay **10 artículos** precargados en el sistema:

1. Art. 58 - Obligaciones especiales del trabajador
2. Art. 60 - Prohibiciones a los trabajadores
3. Art. 60 Num. 1 - Faltar al trabajo sin justa causa
4. Art. 60 Num. 2 - Sustraer de la fábrica
5. Art. 60 Num. 3 - Presentarse en estado de embriaguez
6. Art. 60 Num. 4 - Conservar armas
7. Art. 60 Num. 5 - Faltar al respeto
8. Art. 60 Num. 6 - Hacer colectas
9. Art. 60 Num. 7 - Coartar la libertad
10. Art. 60 Num. 8 - Usar los útiles

Puedes editarlos, desactivarlos o agregar más según necesites.

---

## ⚙️ Integración con Procesos Disciplinarios

### Cómo se Usa en los Procesos

1. **Al crear/editar un proceso disciplinario**:
   - El abogado/super_admin ve un selector "Artículos Legales Incumplidos"
   - Solo aparecen artículos activos
   - Puede seleccionar múltiples artículos
   - El selector muestra: "Art. XX - Título"

2. **Al generar la citación**:
   - Los artículos seleccionados se incluyen en la variable `${ARTICULOS_LEGALES}`
   - Formato: "Art. 58, Art. 60 Num. 1, Art. 60 Num. 3"
   - Si no hay artículos: "No especificado"

3. **En el documento Word**:
   - Agrega la variable `${ARTICULOS_LEGALES}` donde necesites mostrar los artículos
   - Se reemplazará automáticamente con la lista de artículos

---

## 🎯 Mejores Prácticas

### ✅ Recomendado

1. **Usar códigos oficiales**: "Art. 58", no "Articulo 58"
2. **Categorizar correctamente**: Facilita filtrar y encontrar
3. **Títulos descriptivos**: Breves pero claros
4. **Orden lógico**: Artículos más usados con números menores
5. **Desactivar, no eliminar**: Si un artículo ya no se usa
6. **Descripciones completas**: Ayuda a identificar el artículo correcto

### ❌ Evitar

1. No eliminar artículos que están siendo usados en procesos
2. No usar códigos inconsistentes (mezclar formatos)
3. No dejar categorías vacías si se puede categorizar
4. No duplicar artículos con códigos similares
5. No usar órdenes repetidos (puede confundir el orden)

---

## 🔒 Permisos y Seguridad

- **Solo super_admin** puede:
  - Ver la lista de artículos
  - Crear nuevos artículos
  - Editar artículos existentes
  - Activar/desactivar artículos
  - Eliminar artículos

- **Abogado y super_admin** pueden:
  - Ver artículos activos en el selector de procesos
  - Seleccionar artículos al crear procesos

- **Cliente** no puede:
  - Ver el selector de artículos legales
  - Gestionar artículos

---

## 📊 Estadísticas

El sistema te muestra:
- **Badge en el menú**: Número total de artículos activos
- **Columna "Estado"**: Indica si el artículo está activo o no
- **Filtros**: Permite ver solo activos o solo inactivos

---

## 🎉 ¡Listo para Usar!

El recurso está completamente funcional y listo para agregar todos los artículos legales que necesites.

**Próximo paso**: Accede a la interfaz de Filament como super_admin y comienza a agregar los artículos del CST que usarás en tus procesos disciplinarios.

---

*Última actualización: 22 de diciembre de 2025*
