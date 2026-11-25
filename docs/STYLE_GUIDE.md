# Guía de estilo y convenciones

Cómo se escribe el código y la documentación de este repo. El diseño visual está en [`DESIGN_SYSTEM.md`](./DESIGN_SYSTEM.md). Cómo publicar y probar está en [`DEVELOPMENT.md`](./DEVELOPMENT.md).

Si algo de acá contradice al código, manda el código: hay que corregir esta guía.

---

## 1. Principios

1. **Portable:** cada plugin funciona solo, copiado a cualquier sitio. Nada compartido entre plugins en tiempo de ejecución.
2. **Sin dependencias:** solo la API de WordPress y PHP. Sin Composer ni Node en runtime. El código de terceros va en `vendor/` y no se edita.
3. **Seguro por defecto:** toda entrada se valida, toda salida se escapa, toda acción se autoriza (§ 5).
4. **Sin sorpresas:** un módulo apagado no registra hooks. Nada se envía fuera del sitio sin que el admin lo active.
5. **Claro antes que ingenioso:** una función hace una cosa y se lee de arriba abajo.

## 2. Formato

- **WordPress Coding Standards (WPCS):** tabulaciones, espacios dentro de los paréntesis y llaves en la misma línea.

```php
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
```

- **Arrays con `array()`**, no `[]`. Es la norma de WPCS y la que usa todo el repo.
- **Comparaciones estrictas:** `===` y `in_array( ..., true )`. Las condiciones Yoda (`'x' === $y`) son la norma.
- **Un `return` temprano** antes que `if` anidados.
- **Archivos con `defined( 'ABSPATH' ) || exit;`** en la primera línea ejecutable.

## 3. Nombres

| Qué | Convención | Ejemplo |
|---|---|---|
| Carpeta y slug del plugin | kebab-case | `consent-guard` |
| Archivos PHP | kebab-case | `trait-admin.php`, `script-blocker.php` |
| Clases | PascalCase con prefijo o namespace | `Essential_Hardening`, `ConsentGuard\ScriptBlocker` |
| Funciones y variables | snake_case (WPCS) | `$home_host`, `get_default_settings()` |
| Constantes | UPPER_SNAKE_CASE | `VERSION`, `CONSENT_GUARD_FILE` |
| Hooks, opciones, transients y meta | snake_case con el prefijo del plugin | `essential_hardening_client_ip`, `consent_guard_settings` |
| Clases CSS | prefijo del plugin + kebab-case | `ops-card`, `cga-btn--primary` |
| Text domain | igual que el slug | `consent-guard` |
| Endpoints REST | `<slug>/v1/<recurso>` | `consent-guard/v1/consent` |

- **Identificadores en inglés; comentarios y documentación en español.**
- **Métodos:** snake_case en los plugins con traits (WPCS). En los plugins con namespace y PHP 8.4+ (`consent-guard`), los métodos van en camelCase y las variables siguen en snake_case. Es un desvío de la regla global (camelCase en variables) a favor de WPCS; está registrado en `AGENTS.md`.

## 4. Estructura de un plugin

Dos patrones válidos. Elegir uno por plugin y no mezclarlos.

**A. `final class` + traits (ADR-001)**, para PHP 7.4+:

```text
plugins/<slug>/
├── <slug>.php            # Cabecera, constantes y final class que usa los traits
├── includes/trait-*.php  # Un módulo por trait
├── assets/admin.css
├── uninstall.php
└── README.md
```

**B. Namespace + autoloader (ADR-007)**, para PHP 8.4+:

```text
plugins/<slug>/
├── <slug>.php            # Solo sintaxis PHP 7.4: comprueba la versión y carga includes/
├── includes/autoload.php # Namespace\Clase -> includes/clase.php (kebab-case)
├── includes/*.php        # Una clase final por archivo, declare(strict_types=1)
├── assets/
├── uninstall.php         # Sintaxis PHP 7.4
└── README.md
```

Reglas comunes:

- **Cabecera completa:** nombre, URI, descripción, versión, `Requires at least`, `Requires PHP`, `Author: WordPress AI Utilities`, licencia GPL-2.0-or-later y text domain.
- **La versión vive en dos sitios:** cabecera y constante `VERSION`. Siempre se cambian juntas.
- **Plugin con PHP mínimo propio:** avisa en `admin_notices` y no carga, nunca da error fatal. Se excluye del lint de CI por debajo de su versión.
- **Assets encolados solo en su pantalla.** Sin CSS ni JS en línea en el panel.
- **`uninstall.php`** borra todo lo que el plugin creó: opciones, transients, cron y tablas. Si guarda datos con valor legal, ofrece conservarlos.

## 5. Seguridad (no negociable)

| Punto | Cómo |
|---|---|
| Autorización | `current_user_can( 'manage_options' )` u otro permiso concreto. Nunca comprobar el rol (`'administrator'`). |
| CSRF | Nonce por acción: `check_admin_referer()` en `admin-post.php`, `check_ajax_referer()` en AJAX. En formularios con varios nonces por pantalla, sin `id` repetidos. |
| Entrada | `wp_unslash()` y luego el saneador justo: `sanitize_key`, `sanitize_text_field`, `sanitize_hex_color`, `esc_url_raw` con protocolos `http`/`https`, `absint` con rango, o lista blanca / enum. Un array donde se espera texto se descarta. |
| Salida | Escapar al imprimir, siempre: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, `wp_kses` con lista propia. JSON en `<script>` con `wp_json_encode` y `JSON_HEX_TAG`. |
| SQL | `$wpdb->prepare()` para todo valor variable. El nombre de tabla solo desde `$wpdb->prefix`. |
| Endpoints públicos | Esquema REST estricto (`type`, `enum`, `pattern`, `maxItems`), límite de peticiones y comprobación de `Origin`. Si no se puede usar nonce (caché), se documenta en un ADR. |
| IP del cliente | Solo `REMOTE_ADDR`. Las cabeceras de proxy se aceptan únicamente vía filtro `<prefijo>_client_ip`. |
| Peticiones salientes | `wp_safe_remote_get` / `_post`, SSL verificado y tiempo límite. Nunca enviar formularios de terceros ni datos del sitio sin que el admin lo active. |
| Filtros globales | No modificar comportamiento de todo el sitio (`kses_allowed_protocols`, etc.) para una necesidad local. |
| HTML en JS | Sin `innerHTML` con datos: el HTML se genera en PHP ya escapado. |
| CSV exportados | Neutralizar fórmulas (`=`, `+`, `-`, `@` al inicio). |
| Datos personales | Mínimos: IP anonimizada, sin user-agent completo, con borrado automático. |

## 6. PHP moderno

- **Plugins de patrón A:** compatibles con PHP 7.4. Sin `match`, `str_contains`, `?->`, tipos unión ni argumentos con nombre.
- **Plugins de patrón B:** se usa lo que ayuda a leer y a evitar errores: `declare(strict_types=1)`, enums, `readonly`, constantes tipadas, `match`, `array_find()` / `array_any()` y `never`.
- **Callbacks de hooks:** parámetros sin tipo estricto o `mixed` cuando WordPress puede pasar `null` o tipos inesperados.
- **El código nuevo no produce avisos `Deprecated`** en la última versión de PHP de la matriz del CI.

## 7. Comentarios y documentación

- **Comentarios en español** que explican el porqué, no el qué. Un DocBlock por clase y por método público.
- **Cada plugin tiene** `README.md` (uso) y `docs/<slug>.md` (técnica, datos guardados e historial).
- **Seguimiento en** `docs/01_PRD.md`, `02_TASKS.md`, `03_DECISIONS.md` y `04_CHANGELOG.md`. Cada dato va en un solo lugar.
- **Decisiones que no se ven en el código** van en un ADR.
- **Estilo de escritura:** frases cortas, voz activa, una idea por párrafo, sin relleno. Primero qué hace y por qué importa; el detalle técnico después.

## 8. Tests

- **Viven en** `tests/<slug>/run.php`, fuera del ZIP. Cargan el WordPress local, se niegan a correr si el host no es local y dejan la base como estaba.
- **Cada corrección de seguridad** empieza con el test que la reproduce.
- **Se prueba la salida escapada:** se guardan valores maliciosos directamente en la base y se comprueba que el HTML final no los ejecuta.

## 9. Git

- **Conventional Commits** con el slug como alcance: `feat(consent-guard): …`, `fix(essential-hardening): …`.
- **Un release por tag:** `<slug>-v<versión>`.
