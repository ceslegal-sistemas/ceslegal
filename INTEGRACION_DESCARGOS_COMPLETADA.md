# Integración del Sistema de Descargos Dinámicos con IA - COMPLETADA

## Resumen de la Integración

El sistema de descargos dinámicos con IA ha sido completamente integrado con el flujo existente de procesos disciplinarios. Ahora, cuando un abogado envía una citación a descargos, el sistema automáticamente:

1. Crea o actualiza la diligencia de descargo
2. Genera un token de acceso temporal único
3. Configura el acceso web para el día de la audiencia
4. Genera 5 preguntas iniciales con IA basadas en los hechos del proceso
5. Envía el email con el link de acceso al trabajador

## Cambios Realizados

### 1. DocumentGeneratorService Actualizado

**Archivo**: `app/Services/DocumentGeneratorService.php`

**Método modificado**: `generarYEnviarCitacion()`

Ahora este método:
- Crea automáticamente una `DiligenciaDescargo` para el proceso
- Genera el token de acceso temporal (válido por 7 días)
- Configura la `fecha_acceso_permitida` igual a la `fecha_descargos_programada`
- Habilita el acceso web (`acceso_habilitado = true`)
- Genera 5 preguntas iniciales con IA (si la API está configurada)
- Pasa el link de descargos al email

**Retorna**:
```php
[
    'success' => true,
    'message' => 'Citación generada y enviada exitosamente. Diligencia de descargos creada con acceso web.',
    'pdf_path' => '/ruta/al/pdf',
    'diligencia_id' => 123,
    'link_descargos' => 'https://tusitio.com/descargos/abc123...',
]
```

### 2. Vista del Email Actualizada

**Archivo**: `resources/views/emails/citacion-descargos.blade.php`

Se agregó una nueva sección:
- **Acceso a Descargos en Línea**: Sección destacada con el botón de acceso
- **Fecha de acceso permitida**: Muestra cuándo podrá acceder
- **Nota importante**: Advierte que el link es personal e intransferible

El email ahora incluye:
- Botón llamativo para acceder al formulario de descargos
- Información clara sobre cuándo estará disponible
- Instrucciones de que es personal e intransferible

### 3. Resource de Filament Creado

**Archivo**: `app/Filament/Admin/Resources/DiligenciaDescargoResource.php`

Nuevo Resource completo con:

#### Formulario de Edición
- **Sección "Información del Proceso"**: Proceso, fecha, lugar
- **Sección "Acceso Web del Trabajador"**:
  - Token (solo lectura)
  - Fecha de expiración (solo lectura)
  - Toggle para habilitar/deshabilitar acceso
  - Fecha de acceso permitida (editable)
  - Información de cuando accedió (solo lectura)
  - IP desde donde accedió (solo lectura)
- **Sección "Información de la Diligencia"**: Asistencia, acompañante, pruebas
- **Sección "Acta de Descargos"**: Estado del acta

#### Tabla (List View)
Columnas:
- Proceso (código)
- Trabajador (nombre completo)
- Fecha de diligencia
- Lugar
- Asistió (icono)
- Acceso Web (icono)
- **Preguntas** (contador con badge)
- **Respondidas** (contador con badge)
- Accedió en (fecha/hora)
- Creado

#### Filtros
- Acceso Web Habilitado
- Trabajador Asistió
- Con Acceso del Trabajador

#### Acciones en la Tabla

1. **Generar Preguntas IA** (icono sparkles ✨)
   - Se muestra solo si NO hay preguntas
   - Genera 5 preguntas con IA
   - Muestra notificación de éxito con cantidad generada

2. **Ver Link** (icono link 🔗)
   - Se muestra solo si hay token
   - Abre modal con:
     - Link completo (copiable)
     - Botón "Copiar"
     - Token de acceso
     - Fecha de expiración
     - Fecha permitida
     - Estado de acceso
     - Si ya accedió: fecha/hora e IP

3. **Regenerar Token** (icono refresh 🔄)
   - Se muestra solo si hay token
   - Requiere confirmación
   - Genera nuevo token (el anterior deja de funcionar)

4. **Ver** (icono eye 👁️)
   - Vista de solo lectura

5. **Editar** (icono pencil ✏️)
   - Edición completa

### 4. Vista del Modal del Link

**Archivo**: `resources/views/filament/modals/link-descargos.blade.php`

Modal completo que muestra:
- Link completo (campo de texto seleccionable)
- Botón "Copiar" (copia al portapapeles)
- Token de acceso
- Fecha de expiración
- Fecha permitida
- Estado de acceso
- Alerta verde si ya accedió (con fecha/hora e IP)
- Alerta amarilla si no ha accedido
- Nota informativa sobre las restricciones

## Cómo Usar el Sistema

### Para el Abogado/Administrador

#### Opción 1: Enviar Citación Desde ProcesoDisciplinarioResource

1. Ve al listado de **Procesos Disciplinarios**
2. Busca el proceso que quieres citar
3. Asegúrate de que tenga:
   - Fecha de descargos programada
   - Email del trabajador
4. Click en el botón **"Enviar Citación"**
5. Confirma el envío

**El sistema automáticamente**:
- Genera el PDF de citación
- Crea la diligencia de descargo
- Genera token de acceso
- Genera 5 preguntas con IA
- Envía el email con todo incluido

#### Opción 2: Gestionar Desde DiligenciaDescargoResource

1. Ve a **Descargos** en el menú
2. Verás todas las diligencias creadas
3. Puedes:
   - **Ver estadísticas**: Cuántas preguntas hay, cuántas respondidas
   - **Generar preguntas manualmente**: Click en "Generar Preguntas IA"
   - **Ver el link**: Click en "Ver Link" para copiar y compartir
   - **Regenerar token**: Si expiró o necesitas uno nuevo
   - **Editar configuración**: Cambiar fecha permitida, habilitar/deshabilitar

### Para el Trabajador

1. **Recibe el email** con la citación
2. **Lee la citación** (PDF adjunto)
3. **Guarda el link** de acceso a descargos
4. **El día de la audiencia**:
   - Hace click en el link recibido
   - El sistema valida:
     - Token válido ✓
     - Fecha correcta ✓
     - Acceso habilitado ✓
   - Si todo está bien, ve el formulario de descargos
5. **Responde las preguntas** una por una
6. **Nuevas preguntas se generan automáticamente** basadas en sus respuestas
7. **Finaliza** cuando responda todas

## Flujo Completo Paso a Paso

```
1. Abogado crea Proceso Disciplinario
   ↓
2. Abogado programa fecha de descargos
   ↓
3. Abogado hace click en "Enviar Citación"
   ↓
4. Sistema genera PDF de citación
   ↓
5. Sistema crea DiligenciaDescargo
   ↓
6. Sistema genera token de acceso único
   ↓
7. Sistema configura fecha_acceso_permitida = fecha_descargos_programada
   ↓
8. Sistema genera 5 preguntas iniciales con IA
   ↓
9. Sistema envía email con:
   - PDF de citación adjunto
   - Link de acceso a descargos
   - Fecha de acceso permitida
   ↓
10. Trabajador recibe email
    ↓
11. El día de la audiencia, trabajador hace click en el link
    ↓
12. Sistema valida:
    - Token válido
    - Fecha correcta
    - Acceso habilitado
    ↓
13. Si válido, muestra formulario con preguntas
    ↓
14. Trabajador responde primera pregunta
    ↓
15. Al guardar, IA analiza la respuesta
    ↓
16. Si necesario, IA genera hasta 2 nuevas preguntas
    ↓
17. Nuevas preguntas aparecen automáticamente
    ↓
18. Trabajador continúa respondiendo
    ↓
19. Cuando termina todas, hace click en "Finalizar Descargos"
    ↓
20. Sistema marca trabajador_asistio = true
    ↓
21. Abogado puede ver todas las respuestas en Filament
```

## Verificar que Todo Funciona

### 1. Verificar Configuración de IA

Edita `.env` y agrega tu API key:

```env
IA_PROVIDER=openai
OPENAI_API_KEY=tu-api-key-aqui
```

O si usas Anthropic:

```env
IA_PROVIDER=anthropic
ANTHROPIC_API_KEY=tu-api-key-aqui
```

### 2. Probar el Flujo Completo

1. Crea un proceso disciplinario de prueba
2. Programa fecha de descargos (mañana, por ejemplo)
3. Asegúrate de que el trabajador tenga email
4. Click en "Enviar Citación"
5. Verifica que recibes el email
6. Ve a "Descargos" en el menú
7. Verifica que se creó la diligencia
8. Verifica que hay 5 preguntas (columna "Preguntas")
9. Click en "Ver Link"
10. Copia el link
11. **Para probar hoy** (sin esperar a mañana):
    - Edita la diligencia
    - Cambia `fecha_acceso_permitida` a hoy
    - Guarda
12. Abre el link en navegador privado
13. Deberías ver el formulario de descargos

## Características Destacadas

### Seguridad
- Token único de 64 caracteres
- Validación de fecha (solo puede acceder el día programado)
- Validación de expiración (7 días)
- Registro de IP y timestamp de acceso
- Acceso habilitado/deshabilitado manualmente

### Inteligencia Artificial
- Genera preguntas basadas en hechos del proceso
- Genera preguntas basadas en artículos legales incumplidos
- Analiza respuestas del trabajador
- Genera hasta 2 preguntas de seguimiento por respuesta
- Evita loops infinitos
- Tono jurídico laboral (Colombia)

### UX del Trabajador
- Formulario limpio y profesional
- Indicador visual para preguntas IA (borde morado)
- Contador de caracteres en tiempo real
- Validación de longitud mínima
- Loading spinner durante procesamiento
- Alertas cuando se generan nuevas preguntas
- Mensaje de éxito al finalizar

### UX del Abogado
- Todo integrado en Filament
- Estadísticas en tiempo real (preguntas/respuestas)
- Modal para copiar link fácilmente
- Filtros para encontrar diligencias rápidamente
- Acciones rápidas en la tabla
- Vista detallada de cada diligencia

## Solución de Problemas

### "No se generaron preguntas con IA"

**Causa**: API key no configurada o inválida

**Solución**:
1. Verifica `.env` tiene la API key correcta
2. Verifica que `IA_PROVIDER` esté configurado
3. Prueba la API key manualmente
4. Revisa los logs en `storage/logs/laravel.log`

### "El link no funciona"

**Causas posibles**:
1. Token expirado (más de 7 días)
2. Acceso no habilitado
3. Fecha incorrecta

**Solución**:
1. Ve a "Descargos" en Filament
2. Busca la diligencia
3. Verifica:
   - `acceso_habilitado` está en ON
   - `fecha_acceso_permitida` es la correcta
   - `token_expira_en` no ha pasado
4. Si expiró, usa "Regenerar Token"

### "Las nuevas preguntas no aparecen"

**Causa**: Problema con Livewire

**Solución**:
1. Limpia caché: `php artisan view:clear`
2. Verifica que `@livewireStyles` y `@livewireScripts` estén en el layout
3. Abre la consola del navegador y busca errores JavaScript

## Archivos Modificados/Creados

### Modificados
- `app/Services/DocumentGeneratorService.php`
- `resources/views/emails/citacion-descargos.blade.php`

### Creados
- `app/Filament/Admin/Resources/DiligenciaDescargoResource.php`
- `resources/views/filament/modals/link-descargos.blade.php`

### Ya Existían (del sistema anterior)
- `app/Models/DiligenciaDescargo.php`
- `app/Models/PreguntaDescargo.php`
- `app/Models/RespuestaDescargo.php`
- `app/Models/TrazabilidadIADescargo.php`
- `app/Services/IADescargoService.php`
- `app/Livewire/FormularioDescargos.php`
- `resources/views/livewire/formulario-descargos.blade.php`
- `app/Http/Controllers/DescargoPublicoController.php`
- `routes/web.php` (ruta `/descargos/{token}`)

## Próximos Pasos Recomendados

1. **Configurar la API de IA en producción**
   - Obtener API key de OpenAI o Anthropic
   - Configurar en `.env` de producción

2. **Personalizar el prompt de IA**
   - Editar `app/Services/IADescargoService.php:construirPromptGeneracionPreguntas()`
   - Ajustar el tono según las necesidades de tu empresa

3. **Personalizar el email**
   - Editar `resources/views/emails/citacion-descargos.blade.php`
   - Agregar logo de la empresa
   - Ajustar colores corporativos

4. **Configurar notificaciones adicionales**
   - Notificar al abogado cuando el trabajador accede
   - Notificar cuando completa los descargos
   - Recordatorios automáticos

5. **Generar acta automática**
   - Usar la IA para generar un acta con todas las respuestas
   - Implementar firma electrónica

## Soporte

Para cualquier duda o problema:
1. Revisa la documentación completa en `DESCARGOS_DINAMICOS_README.md`
2. Revisa los ejemplos de código en `EJEMPLOS_USO_DESCARGOS.php`
3. Revisa los logs en `storage/logs/laravel.log`
4. Revisa la trazabilidad de IA en la tabla `trazabilidad_ia_descargos`

## Conclusión

El sistema de descargos dinámicos con IA está completamente integrado y funcional. El flujo es automático y transparente para los usuarios. Solo necesitas configurar tu API key de IA y el sistema estará listo para usar en producción.
