# Documentación Técnica: WP MCP Agent Bridge

Suite nativa, ultraligera y modular del **Model Context Protocol (MCP)** para WordPress. Convierte cualquier instalación de WordPress en un entorno totalmente gobernable por agentes autónomos y asistentes de IA (Cursor, Claude Desktop, Antigravity, VS Code, Windsurf, Grok) mediante transporte Streamable HTTP JSON-RPC 2.0 y autenticación nativa.

- **Nombre clave / Slug:** `wp-mcp-agent-bridge`
- **Versión:** `1.0.0`
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.5
- **Ubicación:** `plugins/wp-mcp-agent-bridge/`

---

## 1. Visión General y Filosofía

A diferencia de soluciones comerciales infladas o divididas artificialmente en versiones "Pro" (como OneMCP):
1. **Zero Bloat & Cero Tablas SQL Adicionales:** No ejecuta `dbDelta()` ni altera el esquema de base de datos. Los registros y ajustes se gestionan en `wp_options` con `autoload = false`.
2. **Todas las Capacidades Nativas Incluidas de Serie:**
   - Núcleo de WordPress (Posts, Páginas, CPTs, Menús, Taxonomías, Comentarios, Usuarios, Plugins).
   - Biblioteca de Medios avanzada: subida por URL y en Base64 directo.
   - E-commerce completo con **WooCommerce** (Productos, Variaciones, Pedidos, Clientes, Cupones, Métricas).
   - Integración multi-gestor **SEO** (Yoast SEO, Rank Math, AIOSEO, SEOPress).
   - Limpieza y purga de **Caché** (LiteSpeed, WP Rocket, WP Super Cache, W3 Total Cache).
   - Inspección y edición de plantillas de **Maquetadores** (Elementor, Divi, Bricks, Bloques Gutenberg).
3. **Seguridad Robusta & Capabilidades Granulares:**
   - Transporte JSON-RPC sobre la REST API de WordPress (`/wp-json/mcp/v1`).
   - Autenticación segura mediante *Application Passwords* de WordPress o Bearer Token específico.
   - Verificación estricta de capacidades (`manage_options`, `manage_woocommerce`, `upload_files`, `edit_posts`).
   - Panel administrativo con interruptores individuales para desactivar herramientas de escritura/destructivas si solo se requiere modo lectura para el agente.

---

## 2. Decisiones de Arquitectura

### 2.1. Protocolo y Transporte: Streamable HTTP JSON-RPC 2.0
- **Ruta de Endpoint:** `/wp-json/mcp/v1` vía `rest_api_init`.
- **Compatibilidad de Versiones MCP:** Soporta las especificaciones `2025-11-25`, `2025-06-18`, `2025-03-26` y `2024-11-05`.
- **Métodos RPC implementados:**
  - `initialize`: Negociación de protocolo, capacidades y metadatos del servidor WordPress.
  - `tools/list`: Devuelve el catálogo completo de herramientas disponibles según los plugins activos y las opciones activadas en el panel.
  - `tools/call`: Ejecución segura de la herramienta solicitada con validación de tipos (`inputSchema`) y control de permisos del usuario invocador.

### 2.2. Aislamiento de Estado y Rendimiento
- **Estructura por Traits:** Una sola clase principal (`WP_MCP_Agent_Bridge`, `final class`, patrón Singleton) que ensambla sus módulos mediante *traits* de PHP.
- **Detección Condicional en Memoria:** Si WooCommerce o un plugin de SEO/Caché no está activo en el sitio, sus herramientas no se cargan ni se exponen en `tools/list`.

---

## 3. Catálogo Completo de Herramientas (*Abilities*)

### 3.1. Núcleo de WordPress (`core`)
- `get-site-info`: Devuelve versión de WP, URL, tema activo, plugins instalados y módulos MCP habilitados.
- `list-posts`: Consulta paginada y filtrado por `post_type`, estado (`publish`, `draft`, `any`) o búsqueda.
- `get-post`: Lee contenido crudo, renderizado, autor, fechas y campos personalizados.
- `create-post`: Crea posts, páginas o Custom Post Types con estado configurable.
- `update-post`: Actualiza título, contenido, extracto, estado o metadatos.
- `delete-post`: Envío a la papelera o borrado permanente (restringido por switch).
- `list-terms` / `create-term`: Gestión de categorías, etiquetas y taxonomías registradas.
- `list-comments` / `create-comment` / `update-comment-status`: Moderación de comentarios.
- `list-users` / `get-user`: Consulta de usuarios y capacidades (restringido a administradores).
- `list-plugins` / `toggle-plugin`: Consulta de plugins activos y activación/desactivación controlada.

### 3.2. Medios y Subida de Imágenes (`media`)
- `list-media` / `get-media`: Explorar la biblioteca de medios, URLs y metadatos.
- `upload-media-url`: Descarga e ingesta de imágenes remotas mediante `media_handle_sideload()`.
- `upload-media-base64`: Subida directa de imágenes generadas localmente por el agente enviadas en Base64.
- `update-media`: Actualizar textos alternativos (`alt`), títulos y descripciones de imágenes existentes.

### 3.3. WooCommerce E-Commerce (`woocommerce`)
*Activo automáticamente si WooCommerce está instalado y habilitado.*
- **Productos:**
  - `list-products`: Filtrado por estado, stock bajo o inventario agotado.
  - `get-product`: Ficha completa con precios, SKU, inventario, dimensiones y galería.
  - `create-product`: Creación de productos simples o variables.
  - `update-product`: Edición de precios, stock, descripciones y asignación de imágenes.
  - `delete-product`: Borrado controlado con permisos de comercio.
  - `list-variations` / `create-variation`: Gestión de tallas, colores o atributos variables.
- **Pedidos:**
  - `list-orders`: Consulta por estado (`processing`, `completed`, `on-hold`) y rango de fechas.
  - `get-order`: Detalle de items comprados, totales, datos de facturación y dirección de envío.
  - `update-order-status`: Modificación controlada del estado del pedido.
  - `add-order-note`: Registro de notas internas o avisos para el cliente.
- **Cupones y Clientes:**
  - `list-coupons` / `create-coupon` / `delete-coupon`: Descuentos porcentuales o fijos, montos mínimos y expiración.
  - `list-customers` / `get-customer`: Perfil de clientes recurrentes e historial.
- **Métricas:**
  - `get-sales-report`: Resumen de ingresos brutos/netos, pedidos y productos más vendidos.

### 3.4. SEO y Redes Sociales (`seo`)
*Detecta automáticamente: Yoast SEO, Rank Math, All in One SEO y SEOPress.*
- `get-seo-meta`: Lee título SEO, meta descripción, palabra clave principal (*focus keyword*), URL canónica y meta tags de Open Graph / Twitter para cualquier post o producto.
- `update-seo-meta`: Actualiza los metadatos SEO y ajusta flags de indexación (`noindex`, `nofollow`).

### 3.5. Rendimiento y Caché (`cache`)
*Detecta automáticamente: LiteSpeed Cache, WP Rocket, WP Super Cache y W3 Total Cache.*
- `get-cache-status`: Comprueba el estado del plugin de caché activo.
- `purge-cache`: Purga la caché completa o la URL de una entrada/producto específico tras ser modificado.

### 3.6. Maquetadores Visuales (`builders`)
*Detecta automáticamente: Elementor, Divi y Bricks.*
- `list-builder-templates`: Lista páginas y plantillas editables con el builder detectado.
- `get-page-structure`: Devuelve la estructura jerárquica de secciones, columnas y widgets.
- `update-page-element`: Actualiza textos o configuraciones de un bloque específico sin corromper el formato JSON/serializado del maquetador.

---

## 4. Seguridad, Permisos y Hardening

1. **Permisos y Capacidades Nativas:**
   - Núcleo de contenido: requiere `edit_posts` o `manage_options`.
   - Medios: requiere `upload_files`.
   - WooCommerce: requiere `manage_woocommerce` o `edit_shop_orders`.
   - Operaciones de plugins o sistema: requiere `manage_options`.
2. **Convivencia con `essential-hardening`:**
   - Si `essential-hardening` tiene activa la opción `disable_application_passwords`, este plugin provee un filtro específico para habilitar la autenticación exclusivamente en la ruta `/wp-json/mcp/v1`, impidiendo fugas o accesos indeseados en el resto de la REST API.
3. **Control Destructivo:**
   - El administrador puede desactivar herramientas de eliminación (`delete-*`) desde el panel con un solo clic.

---

## 5. Configuración en Base de Datos (`wp_options`)

Todas las preferencias se almacenan en una única clave serializada:
- **Clave:** `wp_mcp_agent_bridge_settings`
- **Estructura:**
  ```php
  array(
      'enabled'                  => true,
      'allow_writes'             => true,
      'allow_deletes'            => false,
      'modules'                  => array(
          'core'        => true,
          'media'       => true,
          'woocommerce' => true,
          'seo'         => true,
          'cache'       => true,
          'builders'    => true,
      ),
      'activity_log_enabled'     => true,
  )
  ```
- **Registro de Actividad:** `wp_mcp_agent_bridge_log` (array rotativo en memoria de máximo 50 eventos, `autoload = false`).

---

## 6. Estructura de Archivos

```
plugins/wp-mcp-agent-bridge/
├── wp-mcp-agent-bridge.php      # Cabecera estándar, singleton, constantes y router REST
├── README.md                     # Guía de uso y plantillas de configuración JSON para clientes
├── includes/
│   ├── trait-server.php          # Protocolo JSON-RPC 2.0 y manejador MCP
│   ├── trait-auth.php            # Autenticación segura y validación de capacidades
│   ├── trait-tools-core.php      # Herramientas de posts, páginas, CPTs, usuarios y plugins
│   ├── trait-tools-media.php     # Herramientas de medios (URL y Base64)
│   ├── trait-tools-woo.php       # Herramientas de WooCommerce (productos, pedidos, métricas)
│   ├── trait-tools-seo.php       # Herramientas de SEO (Yoast, Rank Math, AIOSEO, SEOPress)
│   ├── trait-tools-cache.php     # Herramientas de purga de caché
│   ├── trait-tools-builders.php  # Herramientas de maquetadores visuales
│   └── trait-admin.php           # Panel de control y generador de configuración de clientes
└── uninstall.php                 # Limpieza de opciones y transitorios al desinstalar
```

---

## 7. Historial de Cambios

| Versión | Cambios |
|---------|---------|
| **1.0.0** | Versión inicial documentada y planificada. Implementación completa de servidor Model Context Protocol (MCP) Streamable HTTP JSON-RPC con integración nativa para Core, Medios (URL y Base64), WooCommerce completo, SEO multigestor, purga de caché y maquetadores. |
