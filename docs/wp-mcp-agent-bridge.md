# Documentación Técnica: WP MCP Agent Bridge

Suite nativa, ultraligera y modular del **Model Context Protocol (MCP)** para WordPress. Convierte cualquier instalación de WordPress en un entorno totalmente gobernable por agentes autónomos y asistentes de IA (Cursor, Claude Desktop, Antigravity, VS Code, Windsurf, Grok) mediante transporte Streamable HTTP JSON-RPC 2.0 y autenticación nativa.

- **Nombre clave / Slug:** `wp-mcp-agent-bridge`
- **Versión:** `1.0.2`
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.5
- **Ubicación:** `plugins/wp-mcp-agent-bridge/`

---

## 1. Visión General y Filosofía

A diferencia de soluciones comerciales infladas o divididas artificialmente en versiones "Pro" (como OneMCP):
1. **Zero Bloat & Cero Tablas SQL Adicionales:** No ejecuta `dbDelta()` ni altera el esquema de base de datos. Los registros y ajustes se gestionan en `wp_options` con `autoload = false`.
2. **Todas las Capacidades Nativas Incluidas de Serie:**
   - Núcleo de WordPress (Posts, Páginas, CPTs, Taxonomías, Plugins).
   - Biblioteca de Medios avanzada: subida por URL y en Base64 directo.
   - E-commerce con **WooCommerce** (Productos y Pedidos).
   - Integración multi-gestor **SEO** (Yoast SEO, Rank Math, AIOSEO).
   - Limpieza y purga de **Caché** (LiteSpeed, WP Rocket, WP Super Cache, W3 Total Cache).
3. **Seguridad Robusta & Capacidades Granulares:**
   - Transporte JSON-RPC sobre la REST API de WordPress (`/wp-json/mcp/v1`).
   - Autenticación nativa mediante *Application Passwords* de WordPress.
   - Verificación estricta de capacidades por herramienta y a nivel de objeto (`read_post`, `edit_post`, `delete_post`, `manage_options`, `manage_woocommerce`, `upload_files`).
   - Panel administrativo con interruptores individuales para operar en modo solo lectura o deshabilitar operaciones destructivas (`delete-post`).

---

## 2. Decisiones de Arquitectura

### 2.1. Protocolo y Transporte: Streamable HTTP JSON-RPC 2.0
- **Ruta de Endpoint:** `/wp-json/mcp/v1/rpc` vía `rest_api_init`.
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
- `get-site-info`: Devuelve versión de WP, PHP, URL, tema activo e integraciones detectadas.
- `list-posts`: Consulta paginada y filtrada por `post_type`, estado (`publish`, `draft`, `pending`, `private`, `any`) o búsqueda, respetando permisos de lectura del usuario.
- `get-post`: Lee contenido, extracto, autor, fecha y URL de una publicación validando `read_post`.
- `create-post`: Crea posts, páginas o Custom Post Types con estado configurable (sanitiza HTML con `wp_kses_post` salvo `unfiltered_html`).
- `update-post`: Actualiza título, contenido, extracto o estado validando `edit_post` y `publish_posts`.
- `delete-post`: Envío a la papelera o borrado permanente validando `delete_post` (deshabilitado por defecto).
- `list-terms` / `create-term`: Gestión de categorías, etiquetas y taxonomías registradas.
- `list-plugins`: Consulta de plugins instalados y estado activo (incluyendo red Multisite).

### 3.2. Medios y Subida de Imágenes (`media`)
- `list-media` / `get-media`: Explorar la biblioteca de medios, URLs, `alt` y dimensiones.
- `upload-media-url`: Descarga e ingesta de imágenes remotas mediante `media_handle_sideload()` con límite de 10 MB y validación de MIME permitido.
- `upload-media-base64`: Subida directa de imágenes en Base64 validando tamaño (10 MB máx.), extensión y contenido real de imagen.
- `update-media`: Actualizar texto alternativo (`alt`) y título validando `edit_post`.

### 3.3. WooCommerce E-Commerce (`woocommerce`)
*Activo automáticamente si WooCommerce está instalado y habilitado.*
- **Productos:**
  - `woo-list-products`: Filtrado por estado, stock, categoría y búsqueda.
  - `woo-get-product`: Ficha completa con precios, SKU, inventario, imagen y categorías.
  - `woo-create-product`: Creación de productos simples con precios, SKU, stock e imagen destacada.
  - `woo-update-product`: Edición de precios, stock, estado e imagen destacada.
- **Pedidos:**
  - `woo-list-orders`: Consulta paginada por estado (`processing`, `completed`, `on-hold`, `pending`, `cancelled`, `any`).
  - `woo-get-order`: Detalle de items comprados, totales, cliente y direcciones.
  - `woo-update-order-status`: Modificación controlada del estado validando contra `wc_get_order_statuses()` y nota opcional.

### 3.4. SEO y Redes Sociales (`seo`)
*Detecta automáticamente: Yoast SEO, Rank Math y All in One SEO.*
- `seo-get-meta`: Lee título SEO, meta descripción, palabra clave principal (*focus keyword*), URL canónica y configuración `noindex` para cualquier post o producto.
- `seo-update-meta`: Actualiza los metadatos SEO y ajusta el flag `noindex` (en formato nativo según el plugin detectado).

### 3.5. Rendimiento y Caché (`cache`)
*Detecta automáticamente: LiteSpeed Cache, WP Rocket, WP Super Cache y W3 Total Cache.*
- `cache-get-status`: Comprueba el estado de los plugins de caché soportados.
- `cache-purge`: Purga la caché completa o la de un post específico tras ser modificado.

---

## 4. Seguridad, Permisos y Hardening

1. **Permisos y Capacidades Nativas:**
   - Núcleo de contenido: `edit_posts` / `delete_posts` y validación por objeto (`read_post`, `edit_post`, `delete_post`, `publish_posts`).
   - Medios: `upload_files` y `edit_post` al actualizar metadatos.
   - WooCommerce: `manage_woocommerce`.
   - Operaciones de sistema/caché/plugins: `manage_options`.
2. **Validación, Rate Limiting y Subidas:**
   - `tools/call` valida los parámetros recibidos contra el `inputSchema` de cada herramienta (`required`, tipos, rangos, `maxLength`, `additionalProperties => false`).
   - Rate limiting de 120 solicitudes/minuto por usuario/IP (configurable con el filtro `wp_mcp_bridge_rate_limit`).
   - Subidas de medios restringidas a imágenes seguras (`jpeg`, `png`, `gif`, `webp`, `avif`) de hasta 10 MB (`wp_mcp_bridge_max_upload_bytes`).
3. **Convivencia con `essential-hardening`:**
   - Si `essential-hardening` desactiva Application Passwords globalmente, este plugin las habilita en `/wp-json/mcp/v1` únicamente bajo HTTPS o en entornos locales de desarrollo.
4. **Control Destructivo:**
   - El administrador puede desactivar escrituras (`allow_writes`) o herramientas de eliminación (`allow_deletes`, desactivado por defecto).

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
├── assets/
│   ├── admin.css                 # Estilos del panel de administración
│   └── admin.js                  # Generador Base64 en vivo y botón de copiado
├── includes/
│   ├── trait-support.php         # Helpers de definición, validación de esquemas y logging
│   ├── trait-updater.php         # Actualizaciones automáticas desde GitHub (PUC)
│   ├── trait-server.php          # Protocolo JSON-RPC 2.0 y manejador MCP
│   ├── trait-auth.php            # Autenticación segura, rate limiting y validación de capacidades
│   ├── trait-tools-core.php      # Herramientas de posts, páginas, CPTs, términos y plugins
│   ├── trait-tools-media.php     # Herramientas de medios (URL y Base64)
│   ├── trait-tools-woo.php       # Herramientas de WooCommerce (productos y pedidos)
│   ├── trait-tools-seo.php       # Herramientas de SEO (Yoast, Rank Math, AIOSEO)
│   ├── trait-tools-cache.php     # Herramientas de purga de caché
│   └── trait-admin.php           # Panel de control y generador de configuración de clientes
└── uninstall.php                 # Limpieza de opciones y transitorios al desinstalar
```
