# WordPress Utilities & Plugins Toolkit

Repositorio central de micro-plugins, utilidades y herramientas modulares para WordPress. Diseñadas para ser livianas, portables, sin dependencias externas y listas para ser implementadas en cualquier entorno de producción.

---

## 📦 Catálogo de Plugins

| Plugin | Categoría | Versión | Estado | Descripción |
|---|---|---|---|---|
| [**Essential Hardening & Security**](./plugins/essential-hardening/) | Seguridad & Hardening | `1.8.0` | `Producción` | Suite de 24 escudos y herramientas: Micro-WAF, fuerza bruta configurable, honeypot anti-bot en login, alertas de login admin, 2FA por correo (opcional), registro en vivo de eventos bloqueados, alertas de actualizaciones pendientes, alertas de administradores nuevos, verificación de integridad del núcleo de WordPress, protección de la recuperación de contraseña, contraseñas robustas para administradores, protección de uploads, desactiva comentarios (403 directo), anti-enumeración con bloqueo temporal de IP opcional, país y bandera de cada IP bloqueada (opcional), cabeceras HTTP (HSTS y CSP opcionales) y anonimización de huellas. |
| [**Modern WP Migrator**](./plugins/modern-wp-migrator/) | Migración & Backups | `7.113.4` | `Producción` | Motor de migración sin restricciones: límite de subida desbloqueado a 500 GB, restauración en 1 clic directa, sin telemetría ni publicidad, compatible con PHP 8.3+. |
| [**WP LLMs.txt Manager**](./plugins/wp-llms-txt/) | SEO & Inteligencia Artificial | `1.1.0` | `Producción` | Generador, editor y endpoint virtual para servir archivos estandarizados `/llms.txt` y `/llms-full.txt` optimizados para indexación por LLMs (llmstxt.org). |
| [**WP Site Ops Toolkit**](./plugins/wp-site-ops-toolkit/) | Operaciones & Mantenimiento | `1.0.0` | `Planificado` | Suite modular de operaciones: Modo mantenimiento (HTTP 503) con bypass por IP/cookie, limpiador de base de datos en 1 clic (transients, revisiones, spam) y logger/blackhole de `wp_mail` para desarrollo y staging. |
| [**WP MCP Agent Bridge**](./plugins/wp-mcp-agent-bridge/) | IA & Integraciones | `1.0.0` | `Planificado` | Servidor Model Context Protocol (MCP) robusto y nativo: conecta asistentes de IA (Cursor, Claude, Antigravity) para operar WordPress, WooCommerce completo, subida de medios (URL y Base64), SEO, purga de caché y maquetadores. |

---

## 🎯 Filosofía del Toolkit

- **Zero Bloat:** Ningún plugin incluye librerías innecesarias, trackers ni peticiones a servidores externos.
- **Portabilidad Total:** Cada plugin en la carpeta `plugins/` es 100% independiente y puede copiarse directamente a cualquier instalación de WordPress.
- **Rendimiento Nativo:** Construidos sobre la API nativa de WordPress y los estándares de PHP modernos (PHP 7.4 a PHP 8.5).
- **Flexibilidad:** Compatibles tanto como plugins tradicionales administrables desde el panel de WordPress o como plugins obligatorios (*Must-Use* en `mu-plugins`).

---

## 🚀 Cómo Usar Cualquier Plugin en tu Proyecto

### Método 1: Plugin Estándar
1. Descarga o copia la carpeta del plugin deseado desde `plugins/<nombre-del-plugin>/`.
2. Pégala en el directorio `wp-content/plugins/` de tu sitio WordPress.
3. Ve a **Plugins > Plugins Instalados** en tu escritorio de WordPress y haz clic en **Activar**.

### Método 2: Must-Use Plugin (`mu-plugins`)
1. Si necesitas que el plugin se ejecute de forma permanente sin opción de ser desactivado desde el panel, copia el archivo PHP principal en la carpeta `wp-content/mu-plugins/`.

---

## 📚 Documentación Técnica

- [Guía técnica de Essential Hardening & Security](./docs/essential-hardening.md)
- [Guía técnica de Modern WP Migrator](./docs/modern-wp-migrator.md)
- [Guía técnica de WP LLMs.txt Manager](./docs/wp-llms-txt.md)
- [Guía técnica de WP Site Ops Toolkit](./docs/wp-site-ops-toolkit.md)
- [Guía técnica de WP MCP Agent Bridge](./docs/wp-mcp-agent-bridge.md)
- [Guía para agregar y desarrollar nuevos plugins](./docs/DEVELOPMENT.md)

---

## 📄 Licencia

Todos los plugins de este repositorio se distribuyen bajo licencias de código abierto compatibles con WordPress (**GPL-2.0-or-later** / **GPL-3.0-or-later**).
