# Documentación Técnica: WP Assets Pulse

Micro-plugin modular y ultraligero de optimización de entrega de recursos en front-end (WPO), eliminación de scripts innecesarios del núcleo de WordPress y optimización de carga diferida de assets.

- **Nombre clave / Slug:** `wp-assets-pulse`
- **Versión:** `1.0.0`
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.5
- **Ubicación:** `plugins/wp-assets-pulse/`

---

## 1. Visión General y Filosofía

1. **Zero Bloat & Cero Dependencias:** Construido exclusivamente sobre hooks nativos (`wp_enqueue_scripts`, `script_loader_tag`, `wp_head`).
2. **Cero Tablas SQL Adicionales:** Persistencia limpia en `wp_options` bajo `wp_assets_pulse_settings`.
3. **Cero Riesgo de Rotura:** Todos los módulos vienen con valores predeterminados seguros y switches individuales para desactivarlos si un tema o plugin concreto lo requiere.
4. **Identidad Visual Corporativa:** Panel integrado con la paleta `#98825b`, `#ede2ce` y `#edd1c1`.

---

## 2. Especificación de los Módulos de Optimización

### 2.1. Desactivación de Recursos Innecesarios (*Debloat*)
- **Desactivar Emojis:** Remueve el script y estilos de emojis de WordPress (`wp_emoji_styles`, `print_emoji_detection_script`). Los navegadores modernos renderizan emojis nativamente.
- **Desactivar Bloques Gutenberg en Front-End:** Remueve `wp-block-library` y `classic-theme-styles` en el front-end si el sitio utiliza maquetadores (Elementor, Divi) o plantillas clásicas.
- **Remover Estilos Globales Inline (SVG Filters):** Elimina los filtros SVG masivos y el CSS de `global-styles` inyectado por defecto desde WordPress 5.9 en temas que no usan Full Site Editing (FSE).

### 2.2. Optimización de JavaScript (*Script Loading*)
- **Atributos `defer` en Scripts:** Añade `defer` a scripts encolados no esenciales en el footer, mejorando inmediatamente el Largest Contentful Paint (LCP) y First Input Delay (FID).
- **Lista de Exclusión de Scripts:** Campo configurable para evitar aplicar `defer` a scripts delicados (ej. `jquery-core`).

### 2.3. Optimización de Fuentes y Preconexiones (*Resource Hints*)
- **Preconnect:** Añade etiquetas `<link rel="preconnect">` para orígenes externos críticos (Google Fonts, CDNs).
- **Carga Local de Fuentes:** Previene bloqueos de renderizado causados por peticiones DNS a fuentes externas.

---

## 3. Configuración en Base de Datos (`wp_options`)

- **Clave:** `wp_assets_pulse_settings`
- **Valores por defecto:**
  ```php
  array(
      'disable_emojis'          => true,
      'disable_block_css'       => false,
      'disable_global_styles'   => false,
      'defer_js_enabled'        => false,
      'defer_js_exclusions'     => "jquery\njquery-core",
      'preconnect_domains'      => "https://fonts.googleapis.com\nhttps://fonts.gstatic.com",
  )
  ```

---

## 4. Estructura de Archivos

```
plugins/wp-assets-pulse/
├── wp-assets-pulse.php           # Cabecera principal, singleton y constantes
├── README.md                     # Documentación de uso
├── includes/
│   ├── trait-debloat.php         # Limpieza de emojis, SVG filters y block styles
│   ├── trait-scripts.php         # Manejo de defer y exclusiones
│   ├── trait-hints.php           # Inyección de preconnect y resource hints
│   └── trait-admin.php           # Panel con paleta visual unificada
└── uninstall.php                 # Limpieza limpia de wp_options
```

---

## 5. Historial de Cambios

| Versión | Cambios |
|---------|---------|
| **1.0.0** | Versión inicial documentada y planificada: debloat de emojis/bloques, script deferring configurable y resource hints preconnect. |
