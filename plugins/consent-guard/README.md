# Consent Guard

Banner de consentimiento de cookies para WordPress, pensado para el RGPD y las leyes de privacidad de EE. UU.

- **Versión:** 1.0.0
- **Requisitos:** WordPress 6.7+ y **PHP 8.4+**. En PHP más antiguo no carga y muestra un aviso en el administrador.
- **Licencia:** GPL-2.0-or-later. Incluye la [Open Cookie Database](https://github.com/jkwakman/Open-Cookie-Database) (Apache-2.0, ver `data/LICENSE-open-cookie-database.txt`).

## Qué hace

- **Banner y panel de preferencias** con cuatro categorías: necesarias, funcionales, analíticas y publicidad. «Aceptar» y «Rechazar» tienen el mismo peso visual y un botón flotante permite cambiar la elección en cualquier momento.
- **Bloqueo de scripts apto para caché:** los scripts de rastreo conocidos (Google Analytics, Tag Manager, Ads, Meta Pixel, Hotjar, Clarity, LinkedIn, TikTok y otros) se cargan desactivados y se activan en el navegador cuando el visitante acepta su categoría.
- **Google Consent Mode v2**, en modo básico o avanzado, y la señal de consentimiento de Microsoft Clarity.
- **Global Privacy Control (GPC):** si el navegador envía la señal, publicidad queda desactivada.
- **Registro de consentimientos** con IP anonimizada, borrado automático por antigüedad y exportación a CSV.
- **Escáner pasivo** de cookies: solo lee páginas, nunca envía formularios.
- **Al retirar un permiso**, se borran las cookies de esa categoría y se recarga la página.

## Uso

1. Activa el plugin y entra en **Ajustes > Consent Guard**.
2. En **Cookies**, pulsa «Escanear ahora» y revisa las categorías.
3. En **Banner**, ajusta los textos, los colores y la URL de tu política de cookies.

### Shortcodes

```text
[consent_guard_preferences label="Cambiar preferencias de cookies"]
[consent_guard_cookies]
```

El primero muestra un botón que abre el panel de preferencias. El segundo muestra la tabla de cookies por categoría, pensada para la página de política de cookies.

### Bloquear un script a mano

Si un script no se reconoce solo, tienes dos opciones:

- Añadir una regla en **Scripts y Consent Mode**, una por línea, con el formato `texto del script | categoría`.
- Añadir el atributo al script:

```html
<script data-cg-category="analytics" src="https://ejemplo.com/tracker.js"></script>
```

### Para desarrolladores

- `window.ConsentGuard.has('analytics')` indica si el visitante aceptó una categoría.
- `window.ConsentGuard.open()` abre el panel de preferencias.
- El evento `consentguard:change` se dispara en `document` con `detail.categories`.
- Filtros de PHP:
  - `consent_guard_services`: añade o quita servicios del catálogo.
  - `consent_guard_block_scripts`: desactiva el bloqueo en una petición concreta.
  - `consent_guard_client_ip`: indica la IP real detrás de un proxy de confianza.
  - `consent_guard_scanner_sslverify`: desactiva la verificación SSL del escáner, solo en local.

## Límites de la versión 1.0

- No bloquea iframes (YouTube, mapas). Está previsto para la 1.1.
- Sin geolocalización, «Do not sell» (CCPA) ni consentimiento compartido entre subdominios.
