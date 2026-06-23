# CES Legal — Dirección de rebrand del panel

Guía para **diseñar en Figma** y luego implementar en el **theming de Filament**.
Concepto: **"Trust & Authority"** — autoridad legal, confianza, serio pero moderno,
coherente con el diferencial garantista (Constitución + jurisprudencia + CST).

> Sin emojis en la UI. Íconos: Lordicon / Heroicons.

---

## 1. Paleta de color

### Modo claro
| Rol | Hex | Uso |
|---|---|---|
| **Primary (navy)** | `#1E3A8A` | Botones primarios, estados activos, focus, enlaces |
| Primary hover | `#172554` | Hover de primary |
| **Accent / CTA (dorado legal)** | `#B45309` | Acción clave (ej. "Emitir Sanción"), resaltados |
| Accent hover | `#92400E` | Hover del CTA |
| Fondo app | `#F8FAFC` | Lienzo general |
| Superficie / card | `#FFFFFF` | Tarjetas, modales, tablas |
| **Sidebar** | `#0F1B3D` | Barra lateral navy oscuro (contraste con contenido claro) |
| Texto principal | `#0F172A` | Títulos y cuerpo |
| Texto atenuado | `#475569` | Secundario, helper text |
| Borde | `#E2E8F0` | Bordes, divisores |

### Modo oscuro
| Rol | Hex |
|---|---|
| Fondo app | `#0B1220` |
| Superficie / card | `#111A2E` |
| Sidebar | `#0A0F1C` |
| Primary (acciones/enlaces) | `#3B82F6` (hover `#60A5FA`) |
| Accent / CTA | `#F59E0B` |
| Texto principal | `#E2E8F0` |
| Texto atenuado | `#94A3B8` |
| Borde | `rgba(255,255,255,0.10)` |

### Semánticos (light / dark) — para badges y estados
| Estado | Light | Dark |
|---|---|---|
| Éxito (No sancionar / OK) | `#15803D` | `#22C55E` |
| Advertencia (Grave / suspensión) | `#B45309` | `#F59E0B` |
| Peligro (Muy grave / terminación) | `#B91C1C` | `#F87171` |
| Info (Leve / llamado) | `#1E40AF` | `#60A5FA` |

> Los badges de sanción ya usan estos colores → el rebrand queda consistente con lo construido.

---

## 2. Tipografía

| Rol | Fuente | Dónde |
|---|---|---|
| **Display / Marca** | **EB Garamond** (serif) | Título del login "CES Legal", marca del sidebar, H1 de páginas clave |
| **UI / Cuerpo** | **Lato** (sans) | Tablas, formularios, labels, botones, casi todo el panel |
| Código de proceso | tabular / mono opcional | `PD-2026-0039` con números tabulares |

Import:
```css
@import url('https://fonts.googleapis.com/css2?family=EB+Garamond:wght@500;600;700&family=Lato:wght@300;400;700&display=swap');
```

Regla: el **serif es acento de autoridad** (marca, login, encabezados), no para tablas/formularios
densos (ahí Lato, más legible). Interlineado cuerpo 1.5–1.6; línea 65–75 caracteres.

---

## 3. Estilo de componentes

- **Sidebar:** navy oscuro, ítem activo con **barra dorada a la izquierda** + texto/ícono claro;
  íconos Lordicon/Heroicons consistentes (24×24). Agrupar por: Operación, Configuración, Empresa.
- **Topbar:** clara, borde sutil inferior, breadcrumbs, menú de usuario a la derecha.
- **Botones:** radio 8–10px, transición 150–200ms.
  - Primary = navy sólido · **CTA = dorado** (solo acciones clave) · Secundario = outline slate · Peligro = rojo.
- **Cards:** blanco, borde `#E2E8F0`, sombra suave, radio 12–14px. Dark: superficie + borde tenue.
- **Badges:** pill con fondo tintado suave + texto del color (leve/grave/muy grave, estados). Ya definidos.
- **Tablas:** header fijo, hover de fila, filas cómodas (44px+), estados como badges, acciones a la derecha.
- **Formularios:** secciones en cards, label claro, helper atenuado, **focus ring navy**, validación cerca del campo.

---

## 4. Pantallas a diseñar en Figma (frames)

Diseña a **1440px** (desktop) y **375px** (responsive). Prioridad:

1. **Login** — split: panel izquierdo navy con "CES Legal" en EB Garamond + tagline
   (*"Procesos disciplinarios con respaldo constitucional"*); derecha, formulario limpio.
2. **Dashboard** — tarjetas de indicadores (Procesos activos, En descargos, Sanciones emitidas,
   RIT vigentes), actividad reciente, accesos rápidos.
3. **Listado de recurso** (Procesos Disciplinarios) — tabla con badges de estado + acciones.
4. **Formulario / wizard** (crear proceso / emitir sanción) — secciones, badges de sanción.
5. **Estados** — vacío, cargando (skeleton), error.

---

## 5. Tokens en Figma (para que la implementación sea directa)

- **Color styles:** crea los hex de arriba como estilos, nombrados por rol
  (`primary/600`, `accent/cta`, `surface`, `text/strong`, `text/muted`, `border`, `success`…),
  con su variante **light** y **dark**.
- **Text styles:** Display, H1, H2, H3, Body, Label, Caption (con EB Garamond / Lato).
- **Componentes:** Button (variants), Badge (variants), Card, Sidebar item, Input, Table row.

> Mantener nombres por **rol** (no por color) facilita mapearlos 1:1 al tema de Filament.

---

## 6. Cómo se implementa después en Filament (referencia)

Cuando el diseño esté listo, yo lo aplico así (no necesitas hacerlo tú):

- `AdminPanelProvider`: `->colors(['primary' => Color::hex('#1E3A8A'), 'warning' => …])`,
  `->brandName('CES Legal')`, `->brandLogo(...)`, `->font('Lato')`.
- **Custom theme** de Filament (Tailwind/CSS) para: EB Garamond en marca/login, sidebar navy,
  radios, sombras, badges y ajustes finos.
- **Login** y **dashboard** vía vistas/branding personalizados.
- Dark mode con las variables de la sección 1.

---

### Anti-patrones a evitar (del análisis)
- Diseño anticuado o recargado.
- Gradientes morado/rosa tipo "IA genérica" (rompen la seriedad legal).
- Esconder el respaldo jurídico: el branding debe **reforzar** "anclado en la ley".
