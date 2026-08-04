# LUPE - Dirección de rebrand del panel (alineada a la marca)

Marca: **LUPE**. Identidad = **gradiente rojo→naranja** (crimson → naranja),
moderna, legal-**tech** (no bufete tradicional). Guía para **diseñar en Figma** y luego
implementar en el **theming de Filament**.

> Sin emojis. Íconos: Lordicon / Heroicons.
> Los hex de marca son aproximados del logo - **muestréalos exactos con el eyedropper / MCP de Figma**.

---

## 1. Color de marca (del logo)

| Rol | Hex aprox. | Uso |
|---|---|---|
| **Marca rojo (crimson)** | `#E11D48` | Ancla primary: botones, nav activo, enlaces, foco |
| **Marca naranja** | `#F97316` | Fin del gradiente, acentos cálidos |
| **Gradiente de marca** | `linear-gradient(135deg, #E11D48, #F97316)` | Login, logo, CTA hero, indicador de nav activo |
| Texto "Legal Digital" | `#6B7280` | Subtítulo/marca secundaria |

**Regla de oro de usabilidad:** el rojo/naranja es **enérgico** → úsalo para **identidad y
acciones clave**, NO para "pintar todo". La UI densa (tablas/formularios) va **neutra** (grises)
para que no se sienta "alarma" y para que la marca **resalte** donde importa.

---

## 2. Neutros (la base de la UI densa)

### Modo claro
| Rol | Hex |
|---|---|
| Fondo app | `#FAFAF9` (stone-50, cálido) |
| Superficie / card | `#FFFFFF` |
| Sidebar | `#FFFFFF` con borde, o **charcoal `#1C1917`** (alternativa de contraste) |
| Texto principal | `#1C1917` |
| Texto atenuado | `#57534E` |
| Borde | `#E7E5E4` |

### Modo oscuro
| Rol | Hex |
|---|---|
| Fondo app | `#0C0A09` |
| Superficie / card | `#1C1917` |
| Sidebar | `#0A0908` |
| Texto principal | `#E7E5E4` · atenuado `#A8A29E` |
| Borde | `rgba(255,255,255,0.10)` |
| Marca (más brillante) | rojo `#FB7185` · naranja `#FB923C` |

> Neutros **cálidos (stone)**, no fríos (slate), para armonizar con el rojo/naranja.

---

## 3. Semánticos - OJO con la usabilidad

El rojo/naranja de marca **colisiona** con los colores de estado. Para no confundir:

| Estado | Light | Nota |
|---|---|---|
| Info (leve / informativo) | `#2563EB` azul | **Frío**, contrasta con la marca cálida |
| Éxito (no sancionar / ok) | `#16A34A` verde | - |
| Advertencia (grave / suspensión) | `#F59E0B` ámbar | Cercano a la marca → **acompañar con ícono + texto** |
| Peligro (muy grave / terminación) | `#DC2626` rojo | Cercano a la marca → **reservar a acciones destructivas**, con ícono |

**Principio:** como advertencia/peligro quedan cerca del rojo de marca, el estado **nunca** se
comunica solo por color → siempre **ícono + etiqueta** (también es buena práctica de accesibilidad).
Los badges de gravedad ya construidos (leve=azul, grave=ámbar, muy grave=rojo, no sancionar=verde)
ya siguen esto → el rebrand queda consistente.

---

## 4. Tipografía (legal-tech, moderna)

| Rol | Fuente | Dónde |
|---|---|---|
| **Display / Marca / Títulos** | **Space Grotesk** (o Sora) | Login, H1/H2, marca del sidebar |
| **UI / Cuerpo** | **Inter** | Tablas, formularios, labels, botones (denso y legible) |
| Código de proceso | números tabulares | `PD-2026-0039` |

```css
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap');
```
> Se descarta el serif (EB Garamond): la marca es **"Legal Digital"**, moderna. Una sans
> geométrica comunica mejor el posicionamiento tech sin perder seriedad.

---

## 5. Estilo de componentes

- **Sidebar:** blanco (o charcoal). Ítem activo: **barra/realce con gradiente de marca** + ícono.
  Agrupar por: Operación · Configuración · Empresa.
- **Topbar:** clara, borde inferior sutil, breadcrumbs, menú de usuario.
- **Botones:** radio 8–10px, transición 150–200ms.
  - **Primary = rojo de marca sólido** · **CTA hero = gradiente** (solo acción estrella, ej. "Emitir Sanción")
    · Secundario = outline neutro · **Peligro = `#DC2626`** (destructivo).
- **Cards:** blanco, borde `#E7E5E4`, sombra suave, radio 12–14px.
- **Badges:** pill tintado suave + texto del color (gravedad/estado). Ya definidos.
- **Tablas:** header fijo, hover de fila, filas 44px+, estados como badges, acciones a la derecha.
- **Formularios:** secciones en cards, label claro, helper atenuado, **focus ring rojo de marca**.
- **Toques de marca:** una línea/acento con el **gradiente** en headers de página o el login -
  con moderación, para identidad sin saturar.

---

## 6. Pantallas a diseñar en Figma (1440px + 375px)

1. **Login** - split: panel con **gradiente de marca** + logo LUPE, derecha formulario limpio.
2. **Dashboard** - tarjetas de indicadores (Procesos activos, En descargos, Sanciones, RIT), actividad.
3. **Listado** (Procesos Disciplinarios) - tabla con badges + acciones.
4. **Formulario / wizard** (emitir sanción) - secciones, badges de sanción.
5. **Estados** - vacío, cargando (skeleton), error.

---

## 7. Tokens en Figma (para mapear 1:1 a Filament)

- **Color styles** por **rol** (no por color): `brand/red`, `brand/orange`, `brand/gradient`,
  `surface`, `text/strong`, `text/muted`, `border`, `info`, `success`, `warning`, `danger` -
  con variante **light** y **dark**.
- **Text styles:** Display, H1, H2, H3, Body, Label, Caption (Space Grotesk / Inter).
- **Componentes:** Button (variants), Badge (variants), Card, Sidebar item, Input, Table row.

---

## 8. Implementación en Filament (lo hago yo después)

- `AdminPanelProvider`: `->colors(['primary' => Color::hex('#E11D48'), 'danger' => Color::hex('#DC2626'),
  'warning' => …, 'info' => …, 'gray' => Color::Stone])`, `->brandName('LUPE')`,
  `->brandLogo(...)`, `->font('Inter')`.
- **Custom theme** (Tailwind/CSS): Space Grotesk en marca/títulos, gradiente de marca en login y
  nav activo, radios/sombras/badges, neutros stone.
- **Login** y **dashboard** con branding personalizado. Dark mode con la sección 2.

---

### Anti-patrones a evitar
- Pintar la UI entera de rojo/naranja (fatiga visual + parece "todo error").
- Gradientes morado/rosa "IA genérica" ajenos a la marca.
- Comunicar estados **solo con color** (chocan con el rojo de marca) → usar ícono + etiqueta.
- Tipografía o estilo anticuado que contradiga el posicionamiento "digital".
