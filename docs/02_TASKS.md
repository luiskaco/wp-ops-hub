# 02 — Tareas Abiertas

Máximo 10 tareas activas. Al cerrar una tarea, se borra de aquí y se agrega una línea en `04_CHANGELOG.md`.

---

## Dónde quedó

- Rediseño de los paneles de admin **publicado** el 2026-11-19: `essential-hardening 1.8.1`, `wp-assets-pulse 1.0.1`, `wp-mcp-agent-bridge 1.0.4`, `modern-wp-migrator 7.113.5` y `wp-site-ops-toolkit 1.0.2` (exige PHP 8.2+, ver ADR-006). Releases y `PHP Compatibility` en verde.
- Sin ver aún en un wp-admin real: `essential-hardening`, `wp-assets-pulse` y `modern-wp-migrator` (solo se probó el renderizado por código; en navegador, `wp-mcp-agent-bridge` y `wp-site-ops-toolkit`).
- Pendiente menor opcional: probar `upload-media-url` de `wp-mcp-agent-bridge` en entorno remoto y revocar la Application Password temporal de pruebas en `adium.orange-la.com`.

## En curso / Pendiente

- **PUC en `wp-assets-pulse` y `wp-site-ops-toolkit`:** no traen Plugin Update Checker, así que los sitios no ven sus releases. Pendiente hasta que se decida; `consent-guard` sirve de modelo (`includes/updater.php`).
- **Permisio (`consent-guard`) 1.0.0, publicado el 2026-11-25:** falta probarlo en un sitio real con caché de página y rastreadores reales. Para la 1.1 quedan: geolocalización, "Do not sell" (CCPA) y consentimiento compartido entre subdominios.