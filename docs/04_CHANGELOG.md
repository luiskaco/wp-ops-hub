# 04 — Changelog

Una línea por cambio relevante, lo más reciente arriba.

- `2026-11-13` — **release(wp-mcp-agent-bridge `1.0.3`):** agregado parámetro `featured_image_id` en `create-post` y `update-post` para asignar la imagen destacada (`set_post_thumbnail`).
- `2026-11-13` — **release(wp-mcp-agent-bridge `1.0.2`):** movido endpoint REST de `/mcp/v1/` a `/mcp/v1/rpc` para evitar colisión con el índice del namespace de WordPress.
- `2026-11-13` — **release(wp-mcp-agent-bridge `1.0.1`):** preparada versión `1.0.1` (hardening de permisos por objeto, validación de `inputSchema`, rate limiting, validación real de imágenes, assets encolados, generador Base64 en vivo en el panel, PUC para releases de GitHub, i18n y suite de pruebas).
- `2026-11-13` — **feat(wp-mcp-agent-bridge):** extraídos CSS/JS de `trait-admin.php` a `assets/admin.css` y `assets/admin.js` (encolados solo en la pantalla del plugin), agregado `load_plugin_textdomain()` y creada suite de pruebas `tests/wp-mcp-agent-bridge/run.php` (20/20 ok en WordPress local).
- `2026-11-13` — **docs:** consolidados PRD, decisiones (ADR) e historiales de cambios en `docs/01_PRD.md`, `docs/02_TASKS.md`, `docs/03_DECISIONS.md` y `docs/04_CHANGELOG.md`; alineado `docs/wp-mcp-agent-bridge.md` con las herramientas reales.
- `2026-11-13` — **fix(wp-mcp-agent-bridge):** corregida autorización por objeto (`read_post`/`edit_post`/`delete_post`), validación de `inputSchema`, rate limiting (120 req/min), restricción HTTP local en Application Passwords, validación real de imágenes (máx. 10 MB), sanitización `wp_kses_post`, `noindex` en Rank Math, `taxonomy_exists` en términos, estados WooCommerce y limpieza Multisite en `uninstall.php`.
- **wp-mcp-agent-bridge `1.0.0`** — Versión inicial: servidor MCP Streamable HTTP JSON-RPC con módulos Core, Medios (URL y Base64), WooCommerce, SEO y Caché.
- **essential-hardening `1.8.0`** — Bloqueo temporal por escaneos `?author=` (5 en 10 min → 403 por 1 hora) y país/bandera de IP en el registro con Top 5 de IP bloqueadas (+21 pruebas, 134 en total).
- **essential-hardening `1.7.1`** — Reorganización interna en archivo principal + 13 *traits* en `includes/` sin cambios de comportamiento (97 pruebas en PHP 8.3–8.5).
- **essential-hardening `1.7.0`** — Límite en recuperación de contraseña (5/IP/hora), opción para desactivar Application Passwords, validación de contraseñas de administradores (mín. 12 chars y HIBP) y bloqueo del sitemap de usuarios (24 escudos).
- **essential-hardening `1.6.0`** — Alertas por correo al crear/promover administradores y verificación diaria opcional de checksums del core de WordPress (21 escudos).
- **essential-hardening `1.5.0`** — Compatibilidad declarada PHP 7.4–8.5, HSTS opcional y Content-Security-Policy en modo solo-reporte (19 escudos).
- **essential-hardening `1.4.2`** — **Seguridad:** corregido bypass de 2FA por parámetro `eh_2fa_verified`, reglas `.htaccess` compatibles con Apache 2.4, límite de códigos 2FA, mejoras de rate limiting y suite de pruebas en `tests/essential-hardening/`.
- **essential-hardening `1.0.0 – 1.4.1`** — Base de escudos (Micro-WAF, protección de uploads, limpieza de `<head>`, honeypot, 2FA, alertas de login y actualizaciones, captura de error fatal de `wp_mail`).
- **wp-llms-txt `1.1.0`** — Detección e importación al editor de archivos `llms.txt` / `llms-full.txt` físicos ajenos en la raíz (máx. 2 MB UTF-8).
- **wp-llms-txt `1.0.2 – 1.0.3`** — Respuesta `200` directa sin `301` de barra final; borrador en memoria sin autoguardado; hash de archivos gestionados en `wp_llms_txt_managed_files`; `uninstall.php`.
- **wp-llms-txt `1.0.0 – 1.0.1`** — Endpoints virtuales `/llms.txt` y `/llms-full.txt`, editor, autogenerador, sincronización física opcional e integración con Plugin Update Checker.
- **modern-wp-migrator `7.113.4`** — Soporte declarado PHP 7.4–8.5 y validación de exportación en PHP 8.3–8.5.
- **modern-wp-migrator `7.113.0 – 7.113.3`** — Integración en monorepo, eliminación de endpoint/formulario de feedback hacia servmask.com, compatibilidad con PUC y prevención de colisión con All-in-One WP Migration.
- **wp-site-ops-toolkit `1.0.0`** — Versión inicial: modo mantenimiento HTTP 503 con bypass por IP/cookie, limpieza de BD en 1 clic y depurador/blackhole de `wp_mail`.
- **wp-assets-pulse `1.0.0`** — Versión inicial: debloat de emojis/bloques, `defer` configurable de scripts y `preconnect` para fuentes externas.
