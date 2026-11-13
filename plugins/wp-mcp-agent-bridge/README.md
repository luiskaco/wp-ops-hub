# WP MCP Agent Bridge

Servidor nativo y ultraligero de **Model Context Protocol (MCP)** para WordPress. Conecta asistentes y agentes de inteligencia artificial (Cursor, Claude Desktop, Antigravity, VS Code, Windsurf) para gobernar tu sitio WordPress mediante herramientas estructuradas vía JSON-RPC 2.0.

---

## 🌟 Características Principales

- **Cero Tablas SQL Adicionales:** No altera la base de datos con tablas innecesarias. Todo se almacena limpiamente en `wp_options`.
- **WooCommerce Nativo Incluido:** Gestión completa de productos, pedidos, inventario, precios y estados sin necesidad de comprar versiones "Pro".
- **Biblioteca de Medios Completa:** Subida de imágenes tanto desde URLs remotas (`upload-media-url`) como codificadas en Base64 directo (`upload-media-base64`).
- **SEO Multigestor:** Detección automática y optimización para Yoast SEO, Rank Math y All in One SEO.
- **Purga de Caché:** Soporte para vaciado de caché en LiteSpeed, WP Rocket, WP Super Cache y W3 Total Cache.
- **Seguridad Granular:** Interruptores en el panel para deshabilitar escrituras o prohibir borrados destructivos, validando siempre las capacidades nativas del usuario.

---

## 🚀 Conexión con Clientes MCP

### 1. Obtener Credenciales en WordPress
1. En tu panel de administración de WordPress, ve a **Usuarios > Tu Perfil**.
2. Desplázate hasta la sección **Contraseñas de aplicación**.
3. Escribe un nombre identificador (ej: `cursor-ai`) y haz clic en **Añadir nueva contraseña de aplicación**.
4. Copia la contraseña generada (ej: `xxxx xxxx xxxx xxxx`).

### 2. Configurar en Cursor / Claude Desktop / Antigravity

Añade lo siguiente a tu archivo de configuración MCP (`.cursor/mcp.json` o `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "mi-wordpress": {
      "url": "https://tusitio.com/wp-json/mcp/v1/rpc",
      "headers": {
        "Authorization": "Basic <BASE64_DE_USUARIO_Y_PASSWORD>"
      }
    }
  }
}
```

*Nota: El panel de administración en **Herramientas > WP MCP Bridge** genera este JSON listo para copiar.*

---

## 🛠️ Catálogo de Herramientas (*Abilities*)

### Núcleo (Core)
- `get-site-info`: Información general, versiones y temas.
- `list-posts` / `get-post` / `create-post` / `update-post` / `delete-post`: Posts, páginas y CPTs.
- `list-terms` / `create-term`: Categorías y etiquetas.
- `list-plugins`: Estado de los plugins instalados.

### Medios (Media)
- `list-media` / `get-media`: Exploración de archivos adjuntos.
- `upload-media-url`: Descarga e importación desde URL pública.
- `upload-media-base64`: Subida directa de imágenes locales en Base64.
- `update-media`: Edición de títulos y textos alternativos (`alt text`).

### WooCommerce
- `woo-list-products` / `woo-get-product`: Consulta con filtros por stock y categoría.
- `woo-create-product` / `woo-update-product`: Creación y actualización de precios, SKU e inventario.
- `woo-list-orders` / `woo-get-order` / `woo-update-order-status`: Consulta y cambios de estado en pedidos.

### SEO & Caché
- `seo-get-meta` / `seo-update-meta`: Lectura y optimización de títulos, descripciones y focus keywords.
- `cache-get-status` / `cache-purge`: Comprobación y purgado selectivo de caché de página.

---

## 📄 Licencia

Distribuido bajo licencia **GPL-2.0-or-later**.
