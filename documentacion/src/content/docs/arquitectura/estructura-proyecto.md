---
title: Estructura del Proyecto
description: Organización de archivos y carpetas del proyecto CES Legal
---

## Estructura General

```
ces-legal/
├── app/                          # Código de la aplicación
│   ├── Filament/                 # Panel administrativo Filament
│   │   └── Admin/
│   │       ├── Pages/            # Páginas personalizadas
│   │       ├── Resources/        # Recursos CRUD (8)
│   │       └── Widgets/          # Widgets del dashboard (5)
│   ├── Http/
│   │   ├── Controllers/          # Controladores (3)
│   │   └── Middleware/           # Middleware personalizado
│   ├── Livewire/                 # Componentes Livewire (1)
│   ├── Models/                   # Modelos Eloquent (22)
│   ├── Notifications/            # Notificaciones
│   ├── Observers/                # Observers de modelos
│   ├── Policies/                 # Políticas de autorización (10)
│   ├── Providers/                # Service Providers
│   └── Services/                 # Servicios de negocio (9)
├── bootstrap/                    # Archivos de arranque
├── config/                       # Configuraciones
├── database/
│   ├── factories/                # Factories para testing
│   ├── migrations/               # Migraciones (56)
│   └── seeders/                  # Seeders de datos (8)
├── documentacion/                # Documentación Astro Starlight
├── public/                       # Archivos públicos
├── resources/
│   ├── css/                      # Estilos CSS
│   ├── js/                       # JavaScript
│   └── views/                    # Vistas Blade
│       ├── documentos/           # Plantillas de documentos
│       ├── emails/               # Plantillas de correo
│       ├── filament/             # Vistas Filament personalizadas
│       └── livewire/             # Vistas de componentes Livewire
├── routes/                       # Definición de rutas
├── storage/                      # Archivos generados
│   ├── app/
│   │   └── public/
│   │       ├── documentos/       # PDFs y Word generados
│   │       └── evidencias/       # Archivos subidos
│   └── logs/                     # Logs de la aplicación
├── tests/                        # Tests automatizados
├── vendor/                       # Dependencias de Composer
├── .env                          # Variables de entorno
├── artisan                       # CLI de Laravel
├── composer.json                 # Dependencias PHP
├── package.json                  # Dependencias Node.js
└── vite.config.js               # Configuración de Vite
```

## Directorio `app/`

### Filament Resources (`app/Filament/Admin/Resources/`)

Cada recurso maneja el CRUD de una entidad:

```
Resources/
├── ProcesoDisciplinarioResource/
│   ├── Pages/
│   │   ├── CreateProcesoDisciplinario.php
│   │   ├── EditProcesoDisciplinario.php
│   │   └── ListProcesoDisciplinarios.php
│   └── ProcesoDisciplinarioResource.php
├── TrabajadorResource/
├── EmpresaResource/
├── UserResource/
├── ArticuloLegalResource/
├── SancionLaboralResource/
├── DiligenciaDescargoResource/
└── SolicitudContratoResource/
```

### Modelos (`app/Models/`)

```
Models/
├── ProcesoDisciplinario.php      # Núcleo del sistema
├── Trabajador.php                # Empleados investigados
├── Empresa.php                   # Empresas clientes
├── User.php                      # Usuarios del sistema
├── DiligenciaDescargo.php        # Sesiones de descargos
├── PreguntaDescargo.php          # Preguntas formuladas
├── RespuestaDescargo.php         # Respuestas del trabajador
├── AnalisisJuridico.php          # Análisis legal
├── Sancion.php                   # Decisión disciplinaria
├── SancionLaboral.php            # Catálogo de sanciones
├── ArticuloLegal.php             # Artículos del código
├── Documento.php                 # Documentos generados
├── Timeline.php                  # Historial de cambios
├── TerminoLegal.php              # Plazos configurables
├── TrazabilidadIADescargo.php    # Auditoría de IA
├── DiaNoHabil.php                # Festivos
├── DisponibilidadAbogado.php     # Agenda de abogados
├── Impugnacion.php               # Recursos del trabajador
├── SolicitudContrato.php         # Contratos
├── EmailTracking.php             # Seguimiento de emails
├── Configuracion.php             # Configuraciones
└── Notificacion.php              # Notificaciones
```

### Servicios (`app/Services/`)

| Servicio | Líneas | Responsabilidad |
|----------|--------|-----------------|
| `DocumentGeneratorService.php` | ~1,141 | Genera documentos legales |
| `ActaDescargosService.php` | ~705 | Genera actas de descargos |
| `IADescargoService.php` | ~645 | Genera preguntas con IA |
| `IAAnalisisSancionService.php` | ~333 | Analiza y recomienda sanciones |
| `NotificacionService.php` | ~393 | Sistema de notificaciones |
| `EstadoProcesoService.php` | ~243 | Máquina de estados |
| `TimelineService.php` | ~238 | Auditoría de cambios |
| `TerminoLegalService.php` | ~223 | Gestión de plazos |
| `DisponibilidadHelper.php` | ~154 | Disponibilidad de abogados |

### Controladores (`app/Http/Controllers/`)

```
Controllers/
├── DescargoPublicoController.php  # Formulario público de descargos
└── EmailTrackingController.php    # Pixel de seguimiento
```

### Policies (`app/Policies/`)

```
Policies/
├── ProcesoDisciplinarioPolicy.php
├── TrabajadorPolicy.php
├── EmpresaPolicy.php
├── UserPolicy.php
├── DiligenciaDescargoPolicy.php
├── SancionPolicy.php
├── ArticuloLegalPolicy.php
├── SancionLaboralPolicy.php
├── DocumentoPolicy.php
└── SolicitudContratoPolicy.php
```

## Directorio `database/`

### Migraciones Principales

```
migrations/
├── 2024_01_01_000001_create_empresas_table.php
├── 2024_01_01_000002_create_trabajadores_table.php
├── 2024_01_01_000003_create_procesos_disciplinarios_table.php
├── 2024_01_01_000004_create_diligencias_descargos_table.php
├── 2024_01_01_000005_create_preguntas_descargos_table.php
├── 2024_01_01_000006_create_respuestas_descargos_table.php
├── 2024_01_01_000007_create_sanciones_table.php
├── 2024_01_01_000008_create_documentos_table.php
├── 2024_01_01_000009_create_timelines_table.php
├── 2024_01_01_000010_create_trazabilidad_ia_table.php
└── ... (56 migraciones total)
```

### Seeders

```
seeders/
├── DatabaseSeeder.php             # Seeder principal
├── UserSeeder.php                 # Usuarios por defecto
├── EmpresaSeeder.php              # Empresas de prueba
├── SancionLaboralSeeder.php       # 63 tipos de sanciones
├── ArticuloLegalSeeder.php        # Artículos del código
├── DiaNoHabilSeeder.php           # Festivos de Colombia
├── TerminoLegalSeeder.php         # Plazos legales
└── RoleSeeder.php                 # Roles y permisos
```

## Directorio `resources/views/`

### Plantillas de Documentos

```
views/documentos/
├── citacion.blade.php             # Citación a descargos
├── citacion-word.blade.php        # Citación formato Word
├── acta-descargos.blade.php       # Acta de descargos
├── sancion.blade.php              # Documento de sanción
└── layouts/
    └── documento-base.blade.php   # Layout base para PDFs
```

### Plantillas de Email

```
views/emails/
├── citacion.blade.php             # Email de citación
├── notificacion.blade.php         # Email de notificación
└── sancion.blade.php              # Email de sanción
```

### Vistas Livewire

```
views/livewire/
└── formulario-descargos.blade.php # Formulario público
```

## Directorio `routes/`

```
routes/
├── web.php                        # Rutas web públicas
├── auth.php                       # Rutas de autenticación
└── api.php                        # Rutas de API (si aplica)
```

## Archivos de Configuración Importantes

| Archivo | Propósito |
|---------|-----------|
| `.env` | Variables de entorno |
| `config/app.php` | Configuración de la aplicación |
| `config/database.php` | Configuración de base de datos |
| `config/filament.php` | Configuración de Filament |
| `config/filament-shield.php` | Configuración de permisos |
| `composer.json` | Dependencias PHP |
| `package.json` | Dependencias Node.js |
| `vite.config.js` | Configuración de build |
| `tailwind.config.js` | Configuración de Tailwind |

## Próximos Pasos

- [Base de Datos](/arquitectura/base-datos/) - Modelo de datos
- [Servicios](/arquitectura/servicios/) - Lógica de negocio
