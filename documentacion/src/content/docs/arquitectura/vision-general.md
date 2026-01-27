---
title: Visión General
description: Arquitectura general del sistema CES Legal
---

## Descripción del Sistema

CES Legal es una aplicación web monolítica construida con **Laravel 12** y **Filament 3.2**, diseñada para gestionar procesos disciplinarios laborales en Colombia con asistencia de Inteligencia Artificial.

## Arquitectura de Alto Nivel

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENTES                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │  Admin   │  │ Abogado  │  │  Cliente │  │Trabajador│        │
│  │(Filament)│  │(Filament)│  │(Filament)│  │ (Público)│        │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘        │
└───────┼─────────────┼─────────────┼─────────────┼───────────────┘
        │             │             │             │
        ▼             ▼             ▼             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CAPA DE PRESENTACIÓN                        │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Filament Admin Panel                    │    │
│  │  • Resources (CRUD)  • Pages  • Widgets  • Actions       │    │
│  └─────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Livewire Components                     │    │
│  │  • FormularioDescargos (público con timer)               │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CAPA DE APLICACIÓN                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Controllers  │  │   Services   │  │   Policies   │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Observers   │  │Notifications │  │    Jobs      │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CAPA DE DOMINIO                             │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Eloquent Models (22)                    │    │
│  │  ProcesoDisciplinario, Trabajador, Empresa, User, etc.   │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CAPA DE DATOS                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │    MySQL     │  │   Storage    │  │    Cache     │          │
│  │  (56 tablas) │  │  (archivos)  │  │  (database)  │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                   SERVICIOS EXTERNOS                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │Google Gemini │  │  Gmail SMTP  │  │   Storage    │          │
│  │     (IA)     │  │   (correo)   │  │   (local)    │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
```

## Patrones de Diseño Utilizados

### 1. Service Layer Pattern
La lógica de negocio compleja está encapsulada en servicios:

```php
app/Services/
├── DocumentGeneratorService.php   // Generación de documentos
├── ActaDescargosService.php       // Actas de descargos
├── IADescargoService.php          // Integración con Gemini
├── IAAnalisisSancionService.php   // Análisis de sanciones
├── NotificacionService.php        // Sistema de notificaciones
├── EstadoProcesoService.php       // Máquina de estados
├── TimelineService.php            // Auditoría
├── TerminoLegalService.php        // Plazos legales
└── DisponibilidadHelper.php       // Disponibilidad abogados
```

### 2. Repository Pattern (Implícito)
Eloquent ORM actúa como capa de abstracción de datos.

### 3. Observer Pattern
Observers para eventos de modelos:

```php
app/Observers/
└── ProcesoDisciplinarioObserver.php
```

### 4. Policy Pattern
Autorización granular por recurso:

```php
app/Policies/
├── ProcesoDisciplinarioPolicy.php
├── TrabajadorPolicy.php
├── EmpresaPolicy.php
└── ... (10 policies)
```

### 5. State Machine Pattern
Gestión de estados del proceso disciplinario:

```php
// EstadoProcesoService.php
public function cambiarEstado(ProcesoDisciplinario $proceso, string $nuevoEstado): bool
{
    $transicionesValidas = [
        'apertura' => ['descargos_pendientes'],
        'descargos_pendientes' => ['descargos_realizados', 'descargos_no_realizados'],
        'descargos_realizados' => ['sancion_emitida', 'archivado'],
        // ...
    ];
}
```

## Flujo de Datos

### Creación de Proceso Disciplinario

```
1. Usuario (RRHH) → Filament Form
2. ProcesoDisciplinarioResource → Validación
3. ProcesoDisciplinario::create() → Observer
4. EstadoProcesoService → Estado inicial
5. NotificacionService → Notifica al abogado
6. TimelineService → Registra evento
```

### Generación de Preguntas con IA

```
1. Abogado → Acción "Generar Preguntas"
2. IADescargoService → Construye prompt
3. Google Gemini API → Respuesta JSON
4. PreguntaDescargo::create() → Guarda preguntas
5. TrazabilidadIADescargo → Registra auditoría
```

### Formulario Público de Descargos

```
1. Trabajador → URL con token
2. DescargoPublicoController → Valida token
3. FormularioDescargos (Livewire) → Renderiza
4. Timer 45 min → JavaScript
5. Respuestas → RespuestaDescargo::create()
6. ActaDescargosService → Genera acta PDF
```

## Seguridad

### Capas de Seguridad

1. **Autenticación**: Laravel Fortify + Filament Auth
2. **Autorización**: Filament Shield + Policies
3. **Validación**: Form Requests + Filament Forms
4. **CSRF**: Middleware por defecto
5. **XSS**: Blade escaping automático
6. **SQL Injection**: Eloquent ORM

### Multi-tenancy

Cada empresa solo ve sus propios datos:

```php
// En Resources
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (auth()->user()->hasRole('cliente')) {
        return $query->where('empresa_id', auth()->user()->empresa_id);
    }

    return $query;
}
```

## Escalabilidad

### Actual (Monolito)
- Adecuado para carga media
- Fácil de mantener y desplegar
- Base de datos única

### Futuro (Si se requiere)
- Queues para tareas pesadas (emails, PDFs)
- Cache Redis para configuraciones
- CDN para assets estáticos
- Réplicas de lectura de BD

## Próximos Pasos

- [Stack Tecnológico](/arquitectura/stack-tecnologico/) - Detalle de tecnologías
- [Estructura del Proyecto](/arquitectura/estructura-proyecto/) - Organización de archivos
- [Base de Datos](/arquitectura/base-datos/) - Modelo de datos
