# Sistema de diseño

Cómo se ven los paneles de admin y las interfaces públicas de los plugins. Las convenciones de código están en [`STYLE_GUIDE.md`](./STYLE_GUIDE.md).

**Idea central:** todos los paneles comparten el mismo esqueleto y cambian solo el color de acento. El usuario reconoce la familia de plugins y cada uno tiene su identidad.

La fuente de verdad es `plugins/wp-site-ops-toolkit/assets/admin.css`. Si este documento y ese archivo no coinciden, se corrige el documento.

---

## 1. Reglas

1. **Un CSS por plugin:** `assets/admin.css`, encolado solo en su pantalla. No se comparte entre plugins para seguir siendo portables.
2. **Todo bajo el prefijo del plugin:** el contenedor raíz (`.ops`, `.cga`…) define los tokens y ninguna clase sale de él. Así no se rompe el resto de wp-admin.
3. **Solo tokens:** dentro de los componentes no se escriben colores sueltos, siempre `var(--…)`.
4. **Plugin nuevo:** copiar el CSS de `wp-site-ops-toolkit`, cambiar el prefijo y los 4 tokens de acento. Nada más.

## 2. Tokens

### Acento por plugin

| Plugin | Prefijo | Acento | `--a` | `--ad` (oscuro) | `--a-soft` (fondo) | `--a-glow` |
|---|---|---|---|---|---|---|
| `essential-hardening` | `eh-` | verde azulado | `#0f766e` | `#115e59` | `#ccfbf1` | `rgba(94,234,212,.30)` |
| `wp-assets-pulse` | `pulse-` | naranja | `#c2410c` | `#9a3412` | `#ffedd5` | `rgba(253,186,116,.34)` |
| `wp-mcp-agent-bridge` | `mcp-` | índigo | `#4f46e5` | `#3730a3` | `#e0e7ff` | `rgba(165,180,252,.34)` |
| `wp-site-ops-toolkit` | `ops-` | azul | `#0369a1` | `#075985` | `#e0f2fe` | `rgba(125,211,252,.34)` |
| `consent-guard` | `cga-` (admin) · `cg-` (público) | violeta | `#7e22ce` | `#6b21a8` | `#f3e8ff` | `rgba(216,180,254,.34)` |
| `modern-wp-migrator` | `mwm-` | rosa | `#be185d` | `#9d174d` | `#fce7f3` | — |

Para un plugin nuevo se elige un tono de Tailwind 700 (`--a`), 800 (`--ad`) y 100 (`--a-soft`) que no esté en la tabla, y se agrega una fila.

### Neutros y estados (iguales en todos)

| Token | Valor | Uso |
|---|---|---|
| `--ink` | `#1f2430` | Texto principal |
| `--muted` | `#667085` | Texto secundario, ayudas |
| `--line` | `#e4e7ee` | Bordes |
| `--bg` | `#f6f7fb` | Fondo de filas y cabeceras de tabla |
| `--green` / `--green-soft` | `#17623a` / `#e6f6ec` | Éxito, activo |
| `--amber` / `--amber-soft` | `#8a5a00` / `#fff4d6` | Advertencia |
| `--red` / `--red-soft` | `#a3173f` / `#fde8ee` | Error, peligro |
| `--blue` / `--blue-soft` | `#1d5fa8` / `#e3effb` | Información |

Los colores de estado siempre van con su fondo suave (texto oscuro sobre fondo claro) para cumplir contraste AA.

### Escala

| Elemento | Valor |
|---|---|
| Texto base | 14px |
| Título del encabezado | 28px (22px en móvil), 700 |
| Título de tarjeta | 16px, 700 |
| Etiqueta de campo | 13px, 600 |
| Ayuda e insignias | 12px / 11.5px |
| Radios | 16px encabezado · 14px tarjeta · 12px fila y aviso · 10px campo · 999px botón, pestaña y píldora |
| Separación | 16px entre tarjetas · 14px entre campos · 10px entre filas |
| Sombra | Solo tarjetas: `0 1px 2px rgba(20,30,60,.04)` |
| Corte móvil | 782px (el de wp-admin) · rejilla a 2 columnas desde 960px |

## 3. Componentes

| Componente | Clases (`p` = prefijo) | Cuándo |
|---|---|---|
| Encabezado | `p-hero`, `p-eyebrow`, `p-title`, `p-version`, `p-sub` | Arriba de cada panel: categoría, nombre, versión y una frase |
| Estado global | `p-pill` + `p-dot--on` / `--off` | A la derecha del encabezado: si el plugin está activo |
| Pestañas | `p-tabs`, `p-tab`, `is-active` + `aria-current="page"` | Secciones del panel, con un dashicon cada una |
| Aviso | `p-alert--ok` / `--warn` / `--error` / `--info` | Resultado de una acción, con `role="status"` |
| Indicadores | `p-kpis`, `p-kpi`, `p-kpi--green` / `--amber` / `--red` / `--blue` | Números de resumen en la parte superior de una pestaña |
| Rejilla | `p-grid`, `p-grid--2` (7fr / 5fr) | Formulario principal a la izquierda y ayuda o acciones a la derecha |
| Tarjeta | `p-card`, `p-card--accent`, `p-card-title`, `p-card-lead` | Agrupa un tema. La tarjeta principal de la pestaña lleva `--accent` |
| Fila con interruptor | `p-item`, `p-item--danger`, `p-item-label`, `p-item-desc` | Cada opción sí/no, con su explicación debajo |
| Campo | `p-field`, `p-hint`, `p-mono` | Texto, URL, número, color y select, con ayuda opcional |
| Botón | `p-btn--primary` / `--soft` / `--danger` | Primario: guardar. Suave: acciones secundarias. Peligro: borrar |
| Tabla | `p-tablewrap`, `p-table`, `p-badge--ok` / `--sim` / `--warn` / `--fail` | Listados; siempre con scroll horizontal propio |
| Vacío | `p-empty` | Cuando una lista no tiene datos: qué hacer para llenarla |
| Nota | `p-note` | Aclaración informativa al pie de una tarjeta |

### Esqueleto de un panel

```html
<div class="wrap"><div class="ops">
  <div class="ops-hero">…</div>
  <nav class="ops-tabs" aria-label="Secciones">…</nav>
  <div class="ops-body">
    <div class="ops-alert ops-alert--ok" role="status">…</div>
    <div class="ops-grid ops-grid--2">
      <div class="ops-card ops-card--accent">…</div>
      <div class="ops-card">…</div>
    </div>
  </div>
</div></div>
```

## 4. Accesibilidad (obligatorio)

- **Foco visible:** `outline: 2px solid var(--a)` con `outline-offset: 2px` en pestañas, botones, campos e interruptores.
- **Interruptores:** `input type="checkbox" role="switch"` con `<label for>` y `aria-describedby` hacia su descripción.
- **Pestaña actual:** `aria-current="page"`. Navegación en `<nav aria-label>`.
- **Botones solo con icono:** `aria-label` con la acción y el objeto («Eliminar _ga»). El dashicon lleva `aria-hidden` o va junto a texto.
- **Tablas:** `<th scope="col">`. Los campos dentro de tablas llevan etiqueta, aunque sea `screen-reader-text`.
- **El color nunca va solo:** cada estado lleva texto o icono además del color.
- **Textos de la interfaz** en español, traducibles con el text domain del plugin, y sin jerga interna.

## 5. Interfaces públicas

Lo que ve el visitante del sitio, como el banner de `consent-guard`, sigue reglas propias porque convive con temas ajenos:

- **Tokens configurables por el admin** como variables CSS (`--cg-bg`, `--cg-text`, `--cg-accent`, `--cg-accent-text`), validadas con `sanitize_hex_color`. Los tonos derivados se calculan con `color-mix()`.
- **Tipografía heredada del tema** (`font-family: inherit`): el plugin no impone fuentes.
- **Sin patrones oscuros:** aceptar y rechazar con el mismo tamaño y peso visual. Retirar el consentimiento es tan fácil como darlo.
- **Objetivos táctiles de 44px** como mínimo y botones a ancho completo por debajo de 600px.
- **Ventanas con `<dialog>` nativo:** foco atrapado, Escape y fondo oscuro sin JS propio.
- **`prefers-reduced-motion`** desactiva las transiciones.
- **`z-index` alto pero acotado** (`2147483000`) y todo bajo el contenedor del plugin.
