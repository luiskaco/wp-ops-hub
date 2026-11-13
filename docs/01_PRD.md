# 01 — PRD: WP Ops Hub

Requisitos funcionales y no funcionales del ecosistema de micro-plugins portables para WordPress (`plugins/`).

---

## Requisitos Funcionales (`RF`)

| ID | Alcance | Requisito | Criterios de aceptación |
|----|---------|-----------|-------------------------|
| `RF-01` | Global | Cada carpeta en `plugins/` es un micro-plugin independiente y portable. | Puede copiarse a `wp-content/plugins/` o `mu-plugins/` y activarse sin depender de otros plugins del monorepo ni de herramientas externas en runtime. |
| `RF-02` | `essential-hardening` | Proveer defensa perimetral y hardening nativo para WordPress (24 módulos). | Incluye Micro-WAF, rate limiting y bloqueo de login, 2FA por correo, bloqueo de enumeración (`?author=`, REST users, sitemap), protección de uploads, verificación de integridad del core y registro de eventos. |
| `RF-03` | `wp-mcp-agent-bridge` | Exponer un servidor MCP sobre Streamable HTTP JSON-RPC 2.0 en `/wp-json/mcp/v1`. | Atiende `initialize`, `notifications/initialized`, `ping`, `tools/list` y `tools/call` autenticado con *Application Passwords*, con interruptores de solo lectura, bloqueo de borrados y activación por módulo (`core`, `media`, `woocommerce`, `seo`, `cache`). |
| `RF-04` | `wp-site-ops-toolkit` | Proveer herramientas operativas de mantenimiento y saneamiento. | Incluye modo mantenimiento HTTP 503 con bypass por IP o enlace secreto, limpieza de BD en 1 clic (transients, revisiones, papelera, postmeta huérfano) y logger/blackhole de `wp_mail`. |
| `RF-05` | `wp-assets-pulse` | Optimizar la carga de assets en el frontend. | Permite desactivar bloat del core (emojis, CSS de bloques), diferir scripts (`defer`) con exclusiones y emitir `preconnect` para fuentes externas. |
| `RF-06` | `modern-wp-migrator` | Proveer migración y backups completos sin límites artificiales. | Permite exportar/importar sitios completos compatible con PHP 8.3+ y archivos grandes sin telemetría externa. |
| `RF-07` | `wp-llms-txt` | Servir y gestionar `/llms.txt` y `/llms-full.txt` conforme a llmstxt.org. | Responde `200` en `/llms.txt` y `/llms-full.txt` (enrutamiento virtual + fallback), autogenera borrador en memoria, permite sincronización física opcional respetando archivos ajenos e importa archivos existentes. |

---

## Requisitos No Funcionales (`RNF`)

| ID | Requisito | Criterios de aceptación |
|----|-----------|-------------------------|
| `RNF-01` | Compatibilidad PHP 7.4 – 8.5 y WordPress 5.8+. | Todos los archivos PHP pasan `php -l` sin errores y usan APIs nativas de WordPress sin dependencias externas fuera de `vendor/`. |
| `RNF-02` | Cero contaminación de esquema SQL en micro-plugins nativos. | Configuración y logs rotativos se almacenan en `wp_options` (con `autoload = false` para logs) o transients, sin ejecutar `dbDelta()`. |
| `RNF-03` | Seguridad por defecto en endpoints y formularios. | Verificación de capacidades globales y por recurso (`read_post`, `edit_post`, `delete_post`), nonces en admin, validación contra `inputSchema`, sanitización de entradas, escape de salidas y rate limiting en rutas críticas. |
| `RNF-04` | Publicación automatizada por tag de Git. | Cada release se dispara con `<slug>-v<versión>` manteniendo sincronizados el header del plugin, la constante `VERSION`, `README.md`, `docs/<slug>.md` y `docs/04_CHANGELOG.md`. |
