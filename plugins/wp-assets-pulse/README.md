# WP Assets Pulse

Micro-plugin de optimización de entrega de recursos en front-end (WPO), eliminación de scripts innecesarios y optimización de carga diferida de scripts en WordPress.

---

## 🌟 Características Principales

- **Eliminación de Recursos Innecesarios (Debloat):**
  - Desactiva scripts y estilos de emojis nativos de WordPress.
  - Desencola CSS de bloques Gutenberg (`wp-block-library.css` y `classic-theme-styles.css`) en sitios que no los usan.
  - Remueve estilos globales inline generados por `theme.json` y filtros SVG.
- **Carga Diferida de JavaScript (`defer`):**
  - Añade el atributo `defer` a scripts encolados en el front-end para eliminar bloqueos de renderizado.
  - Exclusiones configurables (ej: `jquery` y `jquery-core`).
- **Resource Hints (Preconnect):**
  - Preconexión anticipada a dominios de fuentes y CDNs externas para acelerar la carga visual.
- **Cero Bloat:**
  - Sin tablas SQL adicionales, todo se guarda de forma limpia en `wp_options`.
  - Diseñado con la identidad visual unificada del toolkit.

---

## 📄 Licencia

Distribuido bajo licencia **GPL-2.0-or-later**.
