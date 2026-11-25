# 03 — Decisiones de Arquitectura (ADR)

## Índice

- **ADR-001:** Micro-plugins portables con `final class` y *traits* en lugar de monolito o Composer en runtime.
- **ADR-002:** Transporte Streamable HTTP JSON-RPC 2.0 sobre REST API (`/wp-json/mcp/v1`) con *Application Passwords* para `wp-mcp-agent-bridge`.
- **ADR-003:** Persistencia ligera en `wp_options` y transients sin tablas SQL personalizadas.
- **ADR-004:** Validación centralizada de `inputSchema`, autorización por objeto y rate limiting en el router MCP.
- **ADR-005:** Enrutamiento virtual con fallback y control por hash MD5 para archivos físicos en `wp-llms-txt`.
- **ADR-006:** `wp-site-ops-toolkit` exige PHP 8.2+ en lugar de mover sus constantes fuera de los traits.
- **ADR-007:** `consent-guard` exige PHP 8.4+ y usa namespace con clases por archivo en vez de traits.
- **ADR-008:** `consent-guard` guarda el registro de consentimientos en una tabla propia (excepción a ADR-003).
- **ADR-009:** `consent-guard` bloquea siempre los scripts en el servidor y los activa en el navegador, y su endpoint público se protege con límite de peticiones en vez de nonce.

---

## ADR-001 — Micro-plugins portables con `final class` y *traits*

- **Contexto:** Cada plugin debe poder copiarse e instalarse de forma aislada en cualquier sitio WordPress sin colisionar con otros plugins ni requerir compilación.
- **Decisión:** Encapsular cada plugin en una `final class` con prefijos únicos y módulos organizados por *traits* en `includes/` (aplicado en `essential-hardening` desde 1.7.1 y en `wp-mcp-agent-bridge`), sin Node ni autoloaders externos salvo `vendor/` ya incluido en terceros (`modern-wp-migrator`).
- **Consecuencias:** Despliegue directo por carpeta o ZIP; evita colisiones de nombres y mantiene compatibilidad con PHP 7.4+.

## ADR-002 — Transporte Streamable HTTP JSON-RPC 2.0 sobre REST API

- **Contexto:** Los clientes MCP (Cursor, Claude Desktop, Antigravity, VS Code) soportan conexión HTTP directa con cabeceras de autenticación.
- **Decisión:** Exponer `/wp-json/mcp/v1` mediante `register_rest_route` usando *Application Passwords* nativas de WordPress (habilitadas sobre HTTP plano únicamente en entornos locales).
- **Consecuencias:** Aprovecha el sistema nativo de usuarios, roles y contraseñas revocables de WordPress sin implementar tokens propios.

## ADR-003 — Persistencia ligera en `wp_options` y transients

- **Contexto:** Crear tablas personalizadas complica activación, desinstalación y portabilidad en Multisite.
- **Decisión:** Guardar ajustes y logs rotativos (con `autoload = false`) en `wp_options`, y contadores efímeros (rate limit, bloqueos temporales) en transients.
- **Consecuencias:** Activación instantánea y limpieza completa en `uninstall.php`.

## ADR-004 — Validación centralizada de `inputSchema` y autorización por objeto

- **Contexto:** Validar solo capacidades globales (`edit_posts`, `upload_files`) permite escalación horizontal (IDOR) y pasar argumentos no esperados a los callbacks.
- **Decisión:** Validar todos los argumentos contra `$tool['schema']` en `WP_MCP_Bridge_Server::rpc_tools_call()`, exigir capacidades por recurso (`read_post`, `edit_post`, `delete_post`) en cada callback sensible, y aplicar rate limiting + validación real de imágenes (máx. 10 MB).
- **Consecuencias:** Reduce superficie de ataque y asegura consistencia entre lo declarado en `tools/list` y lo ejecutado en `tools/call`.

## ADR-005 — Enrutamiento virtual y control por hash en `wp-llms-txt`

- **Contexto:** No todos los hostings permiten escribir en `ABSPATH`, y si ya existe un `llms.txt` ajeno en disco no debe destruirse ni sobrescribirse en silencio.
- **Decisión:** Servir `/llms.txt` y `/llms-full.txt` mediante `add_rewrite_rule` + fallback por `REQUEST_URI` (solo en raíz y sin redirección `301` de barra final), y rastrear los archivos físicos propios mediante su hash MD5 en `wp_llms_txt_managed_files`.
- **Consecuencias:** Portabilidad total entre hostings sin riesgo de pérdida de archivos creados manualmente por el usuario.

## ADR-006 — `wp-site-ops-toolkit` exige PHP 8.2+

- **Contexto:** El plugin declara constantes dentro de traits, algo que PHP solo permite desde 8.2. En PHP 7.4 a 8.1 daba error fatal al cargar y el CI `PHP Compatibility` fallaba desde el inicio.
- **Decisión:** Declarar `Requires PHP: 8.2`, no cargar el plugin en versiones anteriores (aviso en el administrador) y excluirlo del lint de CI por debajo de 8.2, en vez de mover las constantes a la clase.
- **Consecuencias:** Excepción documentada a la compatibilidad PHP 7.4+ del resto de plugins (ver `AGENTS.md`). Sitios con PHP 8.1 o anterior no pueden usarlo.

## ADR-007 — `consent-guard` exige PHP 8.4+

- **Contexto:** Plugin nuevo, escrito desde cero. El dueño del proyecto fijó PHP 8.4 como mínimo para poder usar enums, clases `readonly`, constantes tipadas, `array_find()`/`array_any()` y `new` sin paréntesis, con `declare(strict_types=1)` en todo el código.
- **Decisión:** Declarar `Requires PHP: 8.4`. El archivo principal usa solo sintaxis compatible con PHP 7.4 y comprueba la versión antes de cargar `includes/`; en versiones anteriores muestra un aviso en el administrador en vez de dar error fatal. A diferencia de ADR-001, el código va en clases `final` con namespace `ConsentGuard` y un autoloader propio, una clase por archivo.
- **Consecuencias:** Sitios con PHP 8.3 o anterior no pueden usarlo. El CI `PHP Compatibility` excluye `plugins/consent-guard/includes/` por debajo de 8.4.

## ADR-008 — Registro de consentimientos en tabla propia (`consent-guard`)

- **Contexto:** El RGPD (art. 7.1) exige poder demostrar el consentimiento. Un sitio con tráfico medio genera miles de registros por día; en `wp_options` (ADR-003) eso no escala y vuelve lento cada guardado.
- **Decisión:** Tabla `{prefix}consent_guard_log` creada con `dbDelta` al activar, de solo inserción (cada cambio de preferencia es una fila nueva, nunca se sobrescribe). Guarda ID de consentimiento, fecha, revisión de la política, categorías aceptadas, tipo de acción e IP anonimizada (IPv4 /24, IPv6 /48). No guarda el user-agent completo. Un cron diario borra lo que supera el plazo de retención (365 días por defecto). Los ajustes y la lista de cookies siguen en `wp_options`.
- **Consecuencias:** Excepción documentada a ADR-003. `uninstall.php` borra la tabla salvo que el admin marque «Conservar registros al desinstalar».

## ADR-009 — Bloqueo apto para caché y endpoint público con límite de peticiones (`consent-guard`)

- **Contexto:** CookieAdmin decide en el servidor si bloquear cada script según la cookie del visitante; con caché de página, la versión guardada del primer visitante se sirve a todos y puede cargar rastreadores sin consentimiento. Además desactivó el nonce de su endpoint público «por la caché» y lo dejó sin protección.
- **Decisión:** El servidor convierte **siempre** los scripts de rastreo reconocidos a `type="text/plain"` (con `WP_HTML_Tag_Processor`, no con expresiones regulares), sin mirar la cookie, y el JS del navegador los activa según el consentimiento guardado. El registro de consentimientos usa la REST API (`consent-guard/v1/consent`) sin nonce (un nonce en HTML cacheado caduca y no protege nada en peticiones anónimas), pero con esquema de validación estricto, comprobación de `Origin` y límite de peticiones por IP anonimizada.
- **Consecuencias:** El HTML es idéntico para todos los visitantes y se puede cachear. Para quien ya consintió, los scripts arrancan unos milisegundos más tarde. El registro puede recibir entradas falsas de un atacante, pero limitadas en volumen; cada entrada sirve como prueba solo junto con la cookie del visitante.
