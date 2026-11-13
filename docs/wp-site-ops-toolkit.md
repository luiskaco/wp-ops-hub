# Documentación Técnica: WP Site Ops Toolkit

Suite modular y ultraligera de herramientas esenciales para mantenimiento, optimización y depuración de WordPress en producción y staging.

- **Nombre clave / Slug:** `wp-site-ops-toolkit`
- **Versión:** `1.0.0`
- **Licencia:** GPL-2.0-or-later
- **Compatibilidad:** WordPress 5.8+ | PHP 7.4 - 8.5
- **Ubicación:** `plugins/wp-site-ops-toolkit/`

---

## 1. Visión General y Filosofía

`WP Site Ops Toolkit` centraliza en una única herramienta las tres operaciones internas más críticas de un sitio WordPress en operación, evitando la sobrecarga de instalar múltiples plugins comerciales o pesados:

1. **Zero Bloat & Cero Dependencias:** Construido exclusivamente sobre las APIs nativas de WordPress (`template_redirect`, transients, `wp_mail`, `wpdb`).
2. **Arquitectura Modular por Interruptores:** Cada módulo (Mantenimiento, Limpieza de DB, Mail Debugger) es 100% independiente. Si un módulo está inactivo, no registra hooks en memoria ni consume recursos.
3. **Seguridad y Permisos:** Todas las acciones de administración y ejecución están restringidas a usuarios con la capacidad `manage_options` y protegidas mediante `wp_verify_nonce`.
4. **Portabilidad Universal:** Funciona en `wp-content/plugins/` o en `wp-content/mu-plugins/`.

---

## 2. Especificación de los Módulos

### 2.1. Módulo 1: Modo Mantenimiento & Staging Gate (`maintenance`)

Permite poner el sitio en modo mantenimiento controlado sin romper la experiencia del administrador ni indexar páginas incompletas.

- **Comportamiento HTTP:** Responde con código **HTTP 503 (Service Unavailable)** y cabecera `Retry-After: 3600` para informar a motores de búsqueda que el sitio volverá pronto.
- **Bypass de Acceso:**
  - **Por rol/capacidad:** Administradores logueados navegan con normalidad.
  - **Por IP autorizada:** Lista blanca de IPs que acceden al front-end sin autenticarse (útil para clientes en revisión).
  - **Por Token Secreto (Query String):** Acceso mediante URL especial (ej. `?ops_preview=token_secreto`), que fija una cookie temporal firmada para navegar libremente.
- **Plantilla:** Pantalla HTML limpia, responsive, sin assets externos, con mensaje y título personalizables.

### 2.2. Módulo 2: Limpieza y Optimización de Base de Datos (`db_cleaner`)

Herramienta manual en un clic para sanear tablas antes de migraciones o copias de seguridad.

- **Acciones disponibles:**
  - Purgar todos los transients y transients de sitio expirados (`_transient_timeout_*`).
  - Eliminar revisiones antiguas de posts (con límite configurable, ej. mantener las últimas 5 o borrar todas).
  - Vaciar papelera de entradas, páginas y comentarios de más de 30 días.
  - Purgar comentarios de spam y metadatos huérfanos (`postmeta` y `commentmeta` sin padre).
- **Seguridad:** Confirmación explícita vía POST con nonce, reporte del conteo de filas eliminadas por sección y sin consultas directas destructivas fuera del alcance del usuario.

### 2.3. Módulo 3: Interceptor y Registro de Correo (`mail_debugger`)

Herramienta esencial para desarrollo local (Laragon) y depuración de correos transaccionales (WooCommerce, formularios, alertas).

- **Captura sin bloqueo:** Intercepta `wp_mail` para registrar los últimos 50 correos enviados (destinatario, asunto, fecha/hora, cabeceras, estado devuelto por `wp_mail`).
- **Modo Agujero Negro / Blackhole (Simulación):**
  - Opción de activar el modo "No enviar": previene que en entornos locales o copias de staging se envíen correos reales a clientes por error, guardando el correo únicamente en el log del panel.
- **Registro de Eventos:** Guardado rotativo en la tabla `wp_options` (`autoload = false`) con visor de contenido HTML/texto del correo.

---

## 3. Estructura de Archivos

Siguiendo el estándar de arquitectura modular del repositorio:

```
plugins/wp-site-ops-toolkit/
├── wp-site-ops-toolkit.php      # Cabecera principal, singleton y cargador modular
├── README.md                     # Documentación de cara al usuario
├── includes/
│   ├── trait-admin.php           # Pantalla de ajustes, pestañas y estilos del panel
│   ├── trait-maintenance.php     # Lógica de modo mantenimiento y bypass
│   ├── trait-db-cleaner.php      # Rutinas seguras de limpieza de base de datos
│   └── trait-mail-debugger.php   # Filtros wp_mail, registro y modo blackhole
└── uninstall.php                 # Limpieza de opciones y transients al desinstalar
```

---

## 4. Opciones Almacenadas (`wp_options`)

Todas las preferencias se guardan en un único registro serializado:
- **Clave:** `wp_site_ops_toolkit_settings`
- **Valores por defecto:**
  ```php
  array(
      // Módulo Mantenimiento
      'maintenance_enabled' => false,
      'maintenance_title'   => 'Sitio en mantenimiento',
      'maintenance_message' => 'Estamos realizando tareas de mantenimiento programadas. Volveremos enseguida.',
      'maintenance_ip_whitelist' => '',
      'maintenance_secret_key'   => '',

      // Módulo Limpieza DB
      'db_cleaner_enabled'  => true,

      // Módulo Mail Debugger
      'mail_debugger_enabled' => false,
      'mail_blackhole_mode'   => false,
  )
  ```
- **Clave independiente para logs de correo:** `wp_site_ops_mail_log` (array con un máximo de 50 elementos, `autoload = false`).
