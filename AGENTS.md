# AGENTS.md — wp-ops-hub (micro-plugins de WordPress)

Lo esencial de este proyecto para cualquier persona o IA que trabaje en él
(estándar abierto [AGENTS.md](https://agents.md)). Cómo trabajar lo decide cada
uno; lo de acá vale para todos.

## Qué es

- Toolkit de micro-plugins de WordPress portables. Cada carpeta de `plugins/` es
  un plugin independiente que se copia a cualquier sitio. No es un sitio web: el
  WordPress de la raíz solo sirve para probarlos en local.

## Stack y comandos

- **Stack real:** PHP 7.4 a 8.3+ sobre la API nativa de WordPress (5.8+). Sin Node, sin build y sin dependencias externas salvo las ya incluidas en `vendor/`. Local en Laragon.
- **Comandos:** no hay build. Tests de `essential-hardening`: `php tests/essential-hardening/run.php` (WordPress local activo; ver `docs/DEVELOPMENT.md`). Verificar sintaxis con `php -l` sobre los archivos tocados y probar el plugin activándolo en el WordPress local (enlazado con `mklink /J`, ver `docs/DEVELOPMENT.md`). Publicar una versión: tag `<slug>-v<versión>`; GitHub Actions (`.github/workflows/release-plugins.yml`) arma el ZIP y el Release, y Plugin Update Checker lo ofrece como actualización.

## Estructura

- `plugins/essential-hardening/` — escudos de seguridad (WAF, rate limit,
  cabeceras, anti-enumeración).
- `plugins/modern-wp-migrator/` — migración y backups; derivado de All-in-One WP
  Migration (librerías `lib/` de ServMask).
- `plugins/wp-llms-txt/` — generador y endpoint de `/llms.txt` y `/llms-full.txt`.
- `plugins/wp-mcp-agent-bridge/` y `plugins/wp-site-ops-toolkit/` — ver su guía en `docs/`.
- `tests/<plugin>/` — pruebas por plugin, fuera del ZIP de los releases.
- `docs/` — una guía técnica por plugin y `DEVELOPMENT.md` (cómo agregar y publicar).
- El núcleo de WordPress, `wp-content/` y `wp-config.php` de la raíz no se versionan ni se tocan.

- **Documentación:** no usa la estructura numerada de `docs/`. La documentación vive en `README.md` (catálogo de plugins), `docs/<plugin>.md` y el `README.md` de cada plugin. **Ante cualquier cambio grande** (función nueva, cambio de comportamiento, corrección de seguridad, algo eliminado) se actualizan, antes de publicar: el README del plugin, `docs/<plugin>.md` con su tabla *Historial de cambios*, el catálogo del README raíz y la versión (cabecera y constante). Detalle en `docs/DEVELOPMENT.md`.

## No negociable

  - Las reglas de SEO no aplican: no es un sitio público. `wp-llms-txt` genera esos archivos para los sitios donde se instala y sigue el estándar de llmstxt.org.
  - Código de terceros vendorizado (`plugins/*/vendor/`, `plugins/modern-wp-migrator/lib/vendor/`): no se edita a mano.
  - Prefijos únicos o `final class` en todo: cada plugin convive con plugins ajenos. Nonces, capabilities, sanitizar entradas y escapar salidas son obligatorios (regla `security`, porque son plugins de seguridad y migración).
  - Al subir la versión de un plugin, cambiar a la vez la cabecera PHP y su constante `VERSION`.

## Para continuar

1. Leer `docs/DEVELOPMENT.md` y la guía del plugin que se va a tocar.
2. Ante un cambio grande, actualizar lo que pide la sección de documentación de
   arriba antes de publicar.
