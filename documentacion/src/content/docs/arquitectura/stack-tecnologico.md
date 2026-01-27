---
title: Stack Tecnológico
description: Tecnologías utilizadas en CES Legal
---

## Resumen del Stack

| Capa | Tecnología | Versión |
|------|------------|---------|
| **Framework Backend** | Laravel | 12.0 |
| **Lenguaje Backend** | PHP | 8.2+ |
| **Panel Administrativo** | Filament | 3.2 |
| **Componentes Reactivos** | Livewire | 3.x |
| **Base de Datos** | MySQL | 8.0 |
| **CSS Framework** | Tailwind CSS | 4.0 |
| **Build Tool** | Vite | 7.0 |
| **IA** | Google Gemini | gemini-2.5-flash |

## Backend

### Laravel 12

Framework PHP moderno que proporciona:

- **Eloquent ORM**: Mapeo objeto-relacional elegante
- **Blade Templates**: Motor de plantillas con componentes
- **Artisan CLI**: Herramientas de línea de comandos
- **Migrations**: Control de versiones de base de datos
- **Queues**: Procesamiento asíncrono de tareas
- **Events/Listeners**: Sistema de eventos desacoplado
- **Notifications**: Sistema de notificaciones multicanal

```php
// Ejemplo de modelo Eloquent
class ProcesoDisciplinario extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['codigo', 'estado', 'hechos', ...];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }
}
```

### Filament 3.2

Panel de administración TALL stack:

- **Resources**: CRUD completo con formularios y tablas
- **Pages**: Páginas personalizadas
- **Widgets**: Componentes de dashboard
- **Actions**: Acciones en tablas y formularios
- **Notifications**: Sistema de notificaciones integrado

```php
// Ejemplo de Resource
class ProcesoDisciplinarioResource extends Resource
{
    protected static ?string $model = ProcesoDisciplinario::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('codigo')->required(),
            Select::make('trabajador_id')->relationship('trabajador', 'nombre'),
            // ...
        ]);
    }
}
```

### Livewire 3

Componentes reactivos sin JavaScript:

```php
// Ejemplo de componente Livewire
class FormularioDescargos extends Component
{
    public DiligenciaDescargo $diligencia;
    public array $respuestas = [];

    public function guardarRespuesta($preguntaId, $respuesta)
    {
        // Lógica de guardado
    }

    public function render()
    {
        return view('livewire.formulario-descargos');
    }
}
```

## Base de Datos

### MySQL 8.0

Características utilizadas:

- **JSON columns**: Para datos flexibles
- **Indexes**: Optimización de consultas
- **Foreign keys**: Integridad referencial
- **Soft deletes**: Eliminación lógica
- **Timestamps**: Auditoría automática

```sql
-- Ejemplo de migración
CREATE TABLE procesos_disciplinarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'apertura',
    trabajador_id BIGINT UNSIGNED NOT NULL,
    empresa_id BIGINT UNSIGNED NOT NULL,
    hechos TEXT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (trabajador_id) REFERENCES trabajadores(id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);
```

## Frontend

### Tailwind CSS 4

Framework CSS utility-first:

```html
<!-- Ejemplo de uso -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-bold text-gray-800">Título</h2>
    <p class="text-gray-600 mt-2">Descripción</p>
</div>
```

### Alpine.js

JavaScript minimal para interactividad:

```html
<!-- Timer de descargos -->
<div x-data="{ timeLeft: 2700 }" x-init="setInterval(() => timeLeft--, 1000)">
    <span x-text="Math.floor(timeLeft / 60) + ':' + (timeLeft % 60).toString().padStart(2, '0')"></span>
</div>
```

### Vite 7

Build tool moderno:

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

## Inteligencia Artificial

### Google Gemini API

Modelo: `gemini-2.5-flash`

Usos en el sistema:
1. **Generación de preguntas** de descargos
2. **Análisis de respuestas** del trabajador
3. **Recomendación de sanciones**

```php
// Ejemplo de llamada a Gemini
$response = Http::withHeaders([
    'Content-Type' => 'application/json',
])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
    'contents' => [
        ['parts' => [['text' => $prompt]]]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 2048,
    ]
]);
```

## Generación de Documentos

### dompdf 3.1

Generación de PDFs desde HTML:

```php
$pdf = Pdf::loadView('documentos.citacion', $data);
$pdf->setPaper('letter', 'portrait');
return $pdf->download('citacion.pdf');
```

### PHPWord 1.4

Generación de documentos Word:

```php
$phpWord = new PhpWord();
$section = $phpWord->addSection();
$section->addText('CITACIÓN A DILIGENCIA DE DESCARGOS', ['bold' => true, 'size' => 14]);
// ...
$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($path);
```

## Paquetes de Filament

| Paquete | Uso |
|---------|-----|
| `filament/filament` | Panel administrativo |
| `bezhansalleh/filament-shield` | Control de permisos |
| `moataz-01/filament-notification-sound` | Sonido de notificaciones |
| `husam-tariq/filament-timepicker` | Selector de hora |

## Dependencias Principales

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^12.0",
        "filament/filament": "^3.2",
        "bezhansalleh/filament-shield": "^3.9",
        "dompdf/dompdf": "^3.1",
        "phpoffice/phpword": "^1.4",
        "livewire/livewire": "^3.0"
    }
}
```

## Herramientas de Desarrollo

| Herramienta | Uso |
|-------------|-----|
| **Laragon** | Entorno local Windows |
| **VS Code** | Editor de código |
| **Git** | Control de versiones |
| **Postman** | Testing de APIs |
| **MySQL Workbench** | Gestión de BD |

## Próximos Pasos

- [Estructura del Proyecto](/arquitectura/estructura-proyecto/) - Organización de archivos
- [Base de Datos](/arquitectura/base-datos/) - Modelo de datos
- [Servicios](/arquitectura/servicios/) - Lógica de negocio
