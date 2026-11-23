# 02 — Tareas Abiertas

Máximo 10 tareas activas. Al cerrar una tarea, se borra de aquí y se agrega una línea en `04_CHANGELOG.md`.

---

## Dónde quedó

- Rediseño de los paneles de admin **publicado** el 2026-11-19: `essential-hardening 1.8.1`, `wp-assets-pulse 1.0.1`, `wp-mcp-agent-bridge 1.0.4`, `modern-wp-migrator 7.113.5` y `wp-site-ops-toolkit 1.0.2` (exige PHP 8.2+, ver ADR-006). Releases y `PHP Compatibility` en verde.
- Sin ver aún en un wp-admin real: `essential-hardening`, `wp-assets-pulse` y `modern-wp-migrator` (solo se probó el renderizado por código; en navegador, `wp-mcp-agent-bridge` y `wp-site-ops-toolkit`).
- `essential-hardening 1.9.0` (alerta por ráfaga de IPs bloqueadas) hecho y sin publicar: falta correr `tests/essential-hardening/run.php` con MySQL de Laragon encendido, probar el correo y crear el tag `essential-hardening-v1.9.0`.
- Pendiente menor opcional: probar `upload-media-url` de `wp-mcp-agent-bridge` en entorno remoto y revocar la Application Password temporal de pruebas en `adium.orange-la.com`.

## En curso / Pendiente

*(Sin tareas bloqueantes abiertas.)*