# Documentación Técnica: Consent Guard

Banner de consentimiento de cookies escrito desde cero a partir del análisis de CookieAdmin 1.2.2 (Softaculous, LGPL-2.1). No reutiliza su código: solo se tomó como referencia funcional.

- **Slug:** `consent-guard`
- **Versión:** `1.0.0`
- **Compatibilidad:** WordPress 6.7+ | **PHP 8.4 - 8.5** (ADR-007)
- **Ubicación:** `plugins/consent-guard/`
- **Tests:** `php tests/consent-guard/run.php` (PHP 8.4+)

---

## 1. Problemas de CookieAdmin que se evitaron

| CookieAdmin 1.2.2 | Consent Guard |
|---|---|
| El escáner envía **todos** los formularios POST con datos falsos (comentarios, contacto, login, checkout) y desactiva SSL | Escáner pasivo: solo GET a la portada y las 9 páginas más recientes del propio host, con `wp_safe_remote_get` |
| Guardado de consentimientos (Pro) sin nonce ni límite: se puede llenar la tabla | REST con esquema estricto, comprobación de `Origin` y 20 peticiones cada 10 minutos por IP anonimizada (ADR-009) |
| Geolocalización confía en `X-Forwarded-For` y `CF-Connecting-IP` | Solo `REMOTE_ADDR`; un proxy de confianza se declara con el filtro `consent_guard_client_ip` |
| Decide en el servidor según la cookie: con caché, se filtran rastreadores | Bloqueo siempre en el servidor y activación en el navegador: HTML idéntico para todos |
| Bloqueo con expresiones regulares sobre todo el HTML | `WP_HTML_Tag_Processor` de WordPress |
| `array_walk(null)` con cookie corrupta: error fatal en PHP 8 | El servidor no lee la cookie; el JS la valida y la descarta si no es válida |
| Colores y URLs con `sanitize_text_field` | `sanitize_hex_color`, `esc_url_raw` solo http(s), enums y rangos |
| Añade `data:` a los protocolos de `wp_kses` de todo el sitio | Lista de protocolos propia solo para el mensaje del banner |
| `innerHTML` con descripciones de cookies | Todo el HTML lo genera PHP escapado; el JS no usa `innerHTML` |
| Comprueba el rol `administrator` | Comprueba el permiso `manage_options` |

## 2. Arquitectura

Clases `final` con namespace `ConsentGuard` y autoloader propio (`ConsentGuard\Admin\AdminPage` → `includes/admin/admin-page.php`). `declare(strict_types=1)` en todo `includes/`. El archivo principal usa sintaxis de PHP 7.4 para poder avisar en versiones antiguas.

| Clase | Responsabilidad |
|---|---|
| `Plugin` | Arranque, activación (tabla y cron) y desactivación |
| `Category` | Enum de categorías; traduce las de la Open Cookie Database |
| `Settings` | Valores por defecto, lectura y saneamiento por pestaña |
| `Services` | Catálogo de servicios conocidos y reglas de bloqueo (filtro `consent_guard_services`) |
| `ScriptBlocker` | Buffer de salida en `template_redirect` que marca los scripts como `text/plain` |
| `Frontend` | Consent Mode por defecto en `<head>`, encolado de JS/CSS y HTML del banner |
| `RestController` | `POST /wp-json/consent-guard/v1/consent` |
| `ConsentLog` | Tabla `{prefix}consent_guard_log`, resumen, poda diaria y CSV |
| `Ip` | IP del cliente y anonimización (IPv4 /24, IPv6 /48) |
| `CookieDatabase` | Lectura del CSV de la Open Cookie Database (solo en admin) |
| `CookieRegistry` | Lista de cookies del sitio (`wp_options`, sin autoload) |
| `Scanner` | Escaneo pasivo y sugerencia de cookies por servicio detectado |
| `Shortcodes` | `[consent_guard_preferences]` y `[consent_guard_cookies]` |
| `Admin\AdminPage` / `Admin\AdminActions` | Pantalla con pestañas y acciones en `admin-post.php` (nonce + `manage_options`) |

## 3. Flujo de consentimiento

1. El servidor marca los scripts reconocidos con `type="text/plain"`, `data-cg-category` y, si tenían tipo, `data-cg-type`.
2. En `<head>`, `consent-guard-gcm` fija Consent Mode en «denegado» y, si hay cookie válida, envía la actualización.
3. `consent.js` lee la cookie `cg_consent`. Si falta o su revisión no coincide, muestra el banner.
4. Al decidir, guarda la cookie, envía las señales (GCM, Clarity, evento `consentguard:change`), registra la elección y activa los scripts en orden.
5. Si se retira una categoría, borra sus cookies conocidas y recarga la página.

**Cookie `cg_consent`** (JSON, `SameSite=Lax`, `Secure` en HTTPS): `v` es la versión del formato, `id` el identificador aleatorio de 32 hex, `r` la revisión de la política, `c` las categorías aceptadas y `t` la marca de tiempo.

**Revisión:** el botón «Pedir consentimiento de nuevo» sube `revision` y todas las cookies anteriores dejan de valer.

## 4. Datos almacenados

| Dónde | Contenido |
|---|---|
| `consent_guard_settings` (autoload) | Ajustes |
| `consent_guard_cookies` (sin autoload) | Lista de cookies del sitio (máx. 500) |
| `consent_guard_last_scan` (sin autoload) | Resultado del último escaneo |
| `consent_guard_db_version` (autoload) | Versión del esquema de la tabla |
| `{prefix}consent_guard_log` | `consent_id`, `created_at` (UTC), `revision`, `action`, `categories`, `ip_anon` |
| Transients `cg_rl_*` | Contadores del límite de peticiones (10 min) |
| Cron `consent_guard_prune_log` | Poda diaria según `log_retention_days` |

`uninstall.php` lo borra todo; la tabla solo si «Conservar los registros al desinstalar» está apagado. En multisitio recorre todos los sitios.

## 5. Historial de cambios

| Versión | Fecha | Cambio |
|---|---|---|
| `1.0.0` | 2026-11-25 | Primera versión. |
