# 03 — Decisiones de Arquitectura (ADR)

## Índice

- **ADR-001:** Micro-plugins portables con `final class` y *traits* en lugar de monolito o Composer en runtime.
- **ADR-002:** Transporte Streamable HTTP JSON-RPC 2.0 sobre REST API (`/wp-json/mcp/v1`) con *Application Passwords* para `wp-mcp-agent-bridge`.
- **ADR-003:** Persistencia ligera en `wp_options` y transients sin tablas SQL personalizadas.
- **ADR-004:** Validación centralizada de `inputSchema`, autorización por objeto y rate limiting en el router MCP.
- **ADR-005:** Enrutamiento virtual con fallback y control por hash MD5 para archivos físicos en `wp-llms-txt`.

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
