# ✅ Solución: Combinación de Correspondencias en Plantilla Word

## 🔍 Problema Identificado

El documento se generaba pero **no reemplazaba las variables** porque:
- ✅ La plantilla SÍ usa el formato correcto: `${VARIABLE}`
- ❌ Los nombres de las variables **NO coincidían**

## 📋 Variables que la Plantilla Espera

Analicé tu plantilla y encontré que usa estas variables:

### Fecha Actual (del documento)
- `${DIA}` - Día numérico (01-31)
- `${MES}` - Mes en texto (enero, febrero...)
- `${AÑO}` - Año (2025)
- `${DIA_LETRA}` - Día de la semana (lunes, martes...)

### Empresa
- `${CIUDAD_EMPRESA}` - Ciudad de la empresa
- `${DEPARTAMENTO_EMPRESA}` - Departamento de la empresa
- `${NIT}` - NIT de la empresa
- `${DIRECCION}` - Dirección de la empresa

### Trabajador
- `${NOMBRES}` - Nombres del trabajador
- `${APELLIDOS}` - Apellidos del trabajador
- `${NUMERO_DOCUMENTO}` - Número de documento
- `${CARGO}` - Cargo del trabajador

### Citación a Descargos
- `${DIA_DESCARGOS}` - Día de la audiencia
- `${MES_DESCARGOS}` - Mes de la audiencia
- `${AÑO_DESCARGOS}` - Año de la audiencia
- `${DIA_LETRA_DESCARGOS}` - Día de la semana de la audiencia
- `${HORA}` - Hora de la audiencia
- `${RAZON_DESCARGO}` - Motivo/hechos del proceso

### Adicional
- `${ARTICULOS_LEGALES}` - Artículos del CST incumplidos

---

## ✅ Solución Aplicada

Actualicé el archivo `app/Services/DocumentGeneratorService.php` para incluir **todas las variables** que tu plantilla espera.

### Cambios Realizados

1. **Separación de nombres y apellidos**:
   ```php
   $partes = explode(' ', $nombreCompleto, 3);
   $nombres = $partes[0] . ' ' . $partes[1];
   $apellidos = $partes[2];
   ```

2. **Fecha actual completa**:
   ```php
   'DIA' => $fechaActual->format('d'),
   'MES' => $fechaActual->isoFormat('MMMM'),
   'AÑO' => $fechaActual->year,
   'DIA_LETRA' => $fechaActual->isoFormat('dddd'),
   ```

3. **Datos de la empresa**:
   ```php
   'CIUDAD_EMPRESA' => $empresa->ciudad,
   'DEPARTAMENTO_EMPRESA' => $empresa->departamento,
   'NIT' => $empresa->nit,
   'DIRECCION' => $empresa->direccion,
   ```

4. **Fecha y hora de descargos separadas**:
   ```php
   'DIA_DESCARGOS' => $fechaDescargos->format('d'),
   'MES_DESCARGOS' => $fechaDescargos->isoFormat('MMMM'),
   'AÑO_DESCARGOS' => $fechaDescargos->year,
   'HORA' => $fechaDescargos->format('H:i A'),
   ```

---

## 🧪 Cómo Verificar que Funciona

### Paso 1: Generar un Documento de Prueba

Ejecuta este comando desde la interfaz de Filament:

1. Ve a **Procesos Disciplinarios**
2. Busca un proceso con fecha de descargos programada
3. Haz clic en **"📄 Generar Documento"** (botón azul)
4. Se descargará el documento

### Paso 2: Abrir y Verificar

Abre el documento descargado y verifica que:

✅ **Fecha actual** (arriba del documento):
- Ejemplo: "22 de diciembre de 2025" o "lunes, 22 de diciembre de 2025"

✅ **Datos de la empresa**:
- Ciudad: [Ciudad de la empresa]
- Departamento: [Departamento de la empresa]
- NIT: [NIT de la empresa]

✅ **Datos del trabajador**:
- Nombres: [Primeros dos nombres]
- Apellidos: [Apellidos]
- C.C. No.: [Número de documento]
- Cargo: [Cargo del trabajador]

✅ **Fecha de la audiencia**:
- Ejemplo: "lunes (29) de diciembre de 2025 a las 19:39"

✅ **Razón del descargo**:
- Debe mostrar el contenido del campo "Hechos" del proceso

✅ **Artículos legales**:
- Si seleccionaste artículos: "Art. 58, Art. 60 Num. 1"
- Si no: "No especificado"

---

## 📝 Ejemplo de Salida Esperada

```
Bogotá, Cundinamarca, 22 de diciembre de 2025

Señor: Juan Pablo Rendon Beltran
C.C. No. 1234567890
Cargo: Operario de Producción
Bogotá, Cundinamarca

Referencia: Citación a diligencia de descargos

Respetado señor Rendon Beltran,

...usted presuntamente el día lunes (16) de diciembre de 2025...
[Aquí aparece el contenido del campo HECHOS]

...constitutivos de una falta disciplinaria conforme a:
Art. 58, Art. 60 Num. 3 [Artículos seleccionados]

...citado para el día lunes (29) de diciembre de 2025 a las 19:39...
```

---

## 🔧 Variables Adicionales Disponibles

También agregué variables de compatibilidad con nombres alternativos:

- `${CODIGO_PROCESO}` - Código del proceso (ej: PD-2025-0001)
- `${EMPRESA_NOMBRE}` - Razón social de la empresa
- `${TRABAJADOR_NOMBRE}` - Nombre completo del trabajador
- `${TRABAJADOR_DOCUMENTO}` - Tipo y número de documento
- `${FECHA_DESCARGOS}` - Fecha completa formateada
- `${MODALIDAD_DESCARGOS}` - Presencial/Virtual/Telefónico
- `${HECHOS}` - Hechos del proceso
- `${ANTECEDENTES}` - Antecedentes
- `${NORMAS_INCUMPLIDAS}` - Normas incumplidas

---

## 🎯 Resultado

Ahora cuando generes un documento:

1. ✅ Todas las variables de la plantilla se reemplazarán
2. ✅ Las fechas aparecerán en español (lunes, martes...)
3. ✅ Los datos de la empresa se completarán automáticamente
4. ✅ El nombre del trabajador se separará en nombres y apellidos
5. ✅ Los artículos legales seleccionados aparecerán en el documento

---

## 💡 Si Aún No Funciona

Si después de estos cambios las variables **AÚN NO** se reemplazan:

1. **Verifica que la empresa tenga datos completos**:
   ```sql
   SELECT ciudad, departamento, direccion FROM empresas WHERE id = ?;
   ```

2. **Verifica que el trabajador tenga nombre completo**:
   - Debe tener al menos 2 palabras (nombres) + apellidos
   - Ejemplo: "Juan Pablo Rendon Beltran"

3. **Limpia la caché**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

4. **Genera un nuevo documento** desde la interfaz

---

## 📋 Scripts de Diagnóstico Creados

Se crearon 3 scripts para ayudarte a diagnosticar:

### 1. `verificar_plantilla.php`
- Verifica que la plantilla se pueda leer
- Intenta reemplazar variables de prueba
- Genera documento de prueba

### 2. `extraer_texto_plantilla.php`
- Extrae el texto de la plantilla
- Identifica qué variables usa
- Muestra el formato de las variables

### 3. Para ejecutarlos:
```bash
php verificar_plantilla.php
php extraer_texto_plantilla.php
```

---

## ✨ Próximos Pasos

1. **Genera un documento de prueba** desde Filament
2. **Verifica** que todas las variables se reemplazaron
3. Si todo funciona: **Elimina los scripts de prueba**
   ```bash
   rm verificar_plantilla.php
   rm extraer_texto_plantilla.php
   ```

---

## 📞 Soporte

Si encuentras variables que **NO** se están reemplazando:

1. Anota el nombre exacto de la variable en la plantilla
2. Verifica que esté en la lista de variables del DocumentGeneratorService
3. Si no está, agrégala al array `$variables`

---

**¡Las variables ahora deberían reemplazarse correctamente!** 🎉

*Última actualización: 22 de diciembre de 2025*
