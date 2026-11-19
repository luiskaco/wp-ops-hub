# 02 — Tareas Abiertas

Máximo 10 tareas activas. Al cerrar una tarea, se borra de aquí y se agrega una línea en `04_CHANGELOG.md`.

---

## Dónde quedó

- Rediseño de paneles de admin hecho en la rama `feat/admin-redesign` (sin commit ni release todavía). Verificado: `php -l`, 134 pruebas de `essential-hardening` y render de los paneles; vista previa en navegador de `wp-mcp-agent-bridge` y `wp-site-ops-toolkit`. Sin ver aún en un wp-admin real: `essential-hardening`, `wp-assets-pulse` y `modern-wp-migrator`.
- `wp-mcp-agent-bridge` en versión **`1.0.3`** (tag `wp-mcp-agent-bridge-v1.0.3` en `main` `323d8cd`), actualizado vía PUC y verificado en vivo en `https://adium.orange-la.com/wp-json/mcp/v1/rpc` (`initialize`, `tools/list`, `get-site-info`, `upload-media-base64`, `create-post`, `update-post` con `featured_image_id`, `seo-update-meta` con Yoast SEO).
- Pendiente menor opcional: probar `upload-media-url` en entorno remoto y revocar la Application Password temporal de pruebas en `adium.orange-la.com`.

## En curso / Pendiente

*(Sin tareas bloqueantes abiertas.)*