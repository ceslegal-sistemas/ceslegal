---
title: Documentos
description: Modulo de generacion y gestion de documentos legales con soporte PDF, DOCX y generacion con IA
---

## Descripcion General

El modulo de **Documentos** gestiona todos los documentos legales generados durante un proceso disciplinario. Utiliza un modelo polimorfico que permite asociar documentos a cualquier entidad del sistema. Los documentos se generan automaticamente mediante plantillas (citaciones) o con inteligencia artificial (sanciones), y pueden exportarse en formato PDF o DOCX.

El servicio principal es `DocumentGeneratorService`, ubicado en:

```
app/Services/DocumentGeneratorService.php
```

## Caracteristicas Principales

### Modelo Polimorfico

El modelo `Documento` usa relaciones polimorficas de Laravel (`MorphTo`), lo que permite asociar documentos a diferentes entidades:

```php
class Documento extends Model
{
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Actualmente, los documentos se asocian a `ProcesoDisciplinario`, pero la arquitectura permite extenderlo a otras entidades sin cambios en el esquema.

### Tipos de Documento

| Tipo | Codigo | Metodo de Generacion | Formato de Salida |
|------|--------|---------------------|-------------------|
| Citacion a descargos | `citacion_descargos` | Plantilla DOCX con interpolacion | PDF (via LibreOffice) o DOCX |
| Acta de descargos | `acta_descargos` | Servicio ActaDescargosService | PDF |
| Sancion | `sancion` | IA (Google Gemini) + conversion | PDF (via LibreOffice o Dompdf) |

### Generacion de Citaciones (Plantilla DOCX)

Las citaciones se generan usando **PHPWord TemplateProcessor** a partir de una plantilla DOCX predefinida. El proceso:

1. Se carga la plantilla DOCX desde la raiz del proyecto.
2. Se preparan las variables con datos del proceso, trabajador y empresa.
3. Se reemplazan las variables en la plantilla.
4. Se guarda el DOCX temporal.
5. Se convierte a PDF usando LibreOffice.
6. Se elimina el DOCX temporal.

**Variables de interpolacion:**

| Variable | Descripcion | Ejemplo |
|----------|-------------|---------|
| `${DIA}` | Dia actual | 15 |
| `${MES}` | Mes actual en texto | enero |
| `${AÑO}` | Anio actual | 2026 |
| `${DIA_LETRA}` | Dia en texto | miercoles |
| `${NOMBRE_EMPRESA}` | Razon social | CES Legal S.A.S. |
| `${NIT}` | NIT de la empresa | 900.123.456-7 |
| `${NOMBRES}` | Nombres del trabajador | Juan Carlos |
| `${APELLIDOS}` | Apellidos del trabajador | Perez Lopez |
| `${NUMERO_DOCUMENTO}` | Documento de identidad | 1.234.567.890 |
| `${CARGO}` | Cargo del trabajador | Analista |
| `${CORREO}` | Email del trabajador | juan@email.com |
| `${DIA_DESCARGOS}` | Dia de la diligencia | 22 |
| `${MES_DESCARGOS}` | Mes de la diligencia | enero |
| `${HORA_DESCARGOS}` | Hora de la diligencia | 10:00 AM |
| `${MODALIDAD_DESCARGOS}` | Modalidad | Presencial |
| `${RAZON_DESCARGO}` | Hechos del proceso | (texto de hechos) |
| `${SANCIONES_LABORALES}` | Sanciones del reglamento | (texto formateado) |
| `${CIUDAD}` | Ciudad de la empresa | Bogota |
| `${DEPARTAMENTO}` | Departamento | Cundinamarca |
| `${DIRECCION_EMPRESA}` | Direccion (solo presencial) | Calle 100 #15-20 |
| `${CODIGO_PROCESO}` | Codigo del proceso | PD-2026-0001 |
| `${NOMBRE_EMPLEADOR}` | Representante legal | Maria Garcia |

### Generacion de Sanciones (IA + HTML)

Los documentos de sancion se generan en un flujo diferente:

1. Se construye un prompt detallado con datos del caso, descargos y principios de lenguaje claro.
2. Se envia a Google Gemini con `maxOutputTokens: 8192`.
3. La IA retorna HTML con formato profesional (Calibri 11pt, justificado, interlineado 1.5).
4. Se limpia el HTML (remueve bloques de codigo markdown).
5. Se envuelve en estructura HTML completa si es necesario.
6. Se convierte a PDF.

### Conversion a PDF

El sistema soporta dos metodos de conversion, con fallback automatico:

**1. LibreOffice (preferido)**

```php
$command = sprintf(
    '"%s" --headless --convert-to pdf --outdir %s %s',
    $this->libreOfficePath,
    escapeshellarg($outputDir),
    escapeshellarg($inputPath)
);
```

- Ruta configurada: `C:\Program Files\LibreOffice\program\soffice.exe`
- Soporta DOCX a PDF y HTML a PDF.
- Alta fidelidad en la conversion.

**2. Dompdf (fallback para HTML)**

```php
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
```

- Solo para conversion de HTML a PDF.
- Usado cuando LibreOffice no esta disponible.
- Menor fidelidad pero funcional.

**3. Archivo original (ultimo recurso)**

Si ambos metodos fallan, se mantiene el archivo en su formato original (DOCX o HTML).

### Versionamiento

Cada documento tiene un campo `version` que permite generar multiples versiones del mismo tipo de documento para un proceso. Actualmente todas las versiones se crean con `version: 1`.

## Modelo de Datos

### Tabla: `documentos`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint | Identificador unico |
| `documentable_type` | string | Tipo de modelo asociado (morph) |
| `documentable_id` | bigint | ID del modelo asociado (morph) |
| `tipo_documento` | string | citacion_descargos, acta_descargos, sancion |
| `nombre_archivo` | string | Nombre del archivo |
| `ruta_archivo` | string | Ruta completa del archivo en disco |
| `formato` | string | Extension del archivo (pdf, docx, html) |
| `generado_por` | foreignId | Usuario que genero el documento |
| `version` | integer | Numero de version |
| `plantilla_usada` | string | Nombre de la plantilla (si aplica) |
| `variables_usadas` | json | Variables interpoladas (si aplica) |
| `fecha_generacion` | datetime | Fecha y hora de generacion |

### Casts

```php
protected $casts = [
    'version' => 'integer',
    'variables_usadas' => 'array',
    'fecha_generacion' => 'datetime',
];
```

## Relaciones con Otros Modulos

| Relacion | Tipo | Modelo | Descripcion |
|----------|------|--------|-------------|
| `documentable` | MorphTo | Polimorfico | Entidad a la que pertenece el documento |
| `generadoPor` | BelongsTo | User | Usuario que genero el documento |

### Entidades que Tienen Documentos

```php
// En ProcesoDisciplinario:
public function documentos(): MorphMany
{
    return $this->morphMany(Documento::class, 'documentable');
}
```

## Almacenamiento

Los documentos se almacenan en el sistema de archivos local:

| Tipo | Ruta de Almacenamiento |
|------|----------------------|
| Citaciones | `storage/app/citaciones/` |
| Sanciones | `storage/app/sanciones/` |
| Actas | `storage/app/actas/` |
| Temporales | `storage/app/temp/` |

### Nomenclatura de Archivos

| Tipo | Patron | Ejemplo |
|------|--------|---------|
| Citacion | `citacion_{codigo}.pdf` | `citacion_PD-2026-0001.pdf` |
| Sancion | `sancion_{codigo}_{tipo}_{timestamp}.pdf` | `sancion_PD-2026-0001_suspension_1706300000.pdf` |
| Acta | `acta_descargos_{codigo}.pdf` | `acta_descargos_PD-2026-0001.pdf` |

## Notas de Uso

### Flujo de Generacion de Citacion Completo

```
1. Abogado hace clic en "Enviar Citacion"
2. DocumentGeneratorService::generarCitacionDescargos()
   a. Carga plantilla DOCX
   b. Prepara variables (proceso, trabajador, empresa)
   c. Interpola variables en plantilla
   d. Guarda DOCX temporal
   e. Convierte a PDF con LibreOffice
   f. Elimina DOCX temporal
3. Se crea registro en tabla documentos
4. Se envia por correo con tracking
5. Se registra en timeline
```

### Flujo de Generacion de Sancion Completo

```
1. Abogado selecciona tipo de sancion
2. DocumentGeneratorService::generarDocumentoSancion()
   a. Construye prompt con principios de lenguaje claro
   b. Envia prompt a Google Gemini (maxOutputTokens: 8192)
   c. Recibe HTML con documento completo
   d. Limpia contenido HTML
   e. Guarda HTML temporal
   f. Convierte a PDF (LibreOffice > Dompdf > HTML)
3. Se crea registro en tabla documentos
4. Se crea/actualiza registro en tabla sanciones
5. Se envia por correo con tracking
6. Se actualiza estado del proceso
7. Se registra en timeline
```

### Consideraciones de Rendimiento

- La conversion con LibreOffice puede tardar varios segundos.
- La generacion con IA tiene un timeout de 120 segundos.
- Los archivos temporales se eliminan despues de la conversion.
- Si la respuesta de IA se trunca (`MAX_TOKENS`), se registra un warning en el log.

### Advertencia de Formato

Si LibreOffice no esta instalado, las citaciones se envian como DOCX en lugar de PDF. El sistema muestra una advertencia al usuario en este caso.

## Proximos Pasos

- [Procesos Disciplinarios](/modulos/procesos-disciplinarios/) - Modulo principal
- [Sanciones](/modulos/sanciones/) - Emision de sanciones
- [Notificaciones](/modulos/notificaciones/) - Sistema de notificaciones
