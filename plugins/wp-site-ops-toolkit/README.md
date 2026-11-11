# WP Site Ops Toolkit

Suite ligera, modular y sin dependencias externas para mantenimiento de sitios, limpieza de base de datos y depuración de correos transaccionales en WordPress.

---

## 🚀 Módulos Incluidos

### 1. Modo Mantenimiento & Staging Gate
- Responde con **HTTP 503 (Service Unavailable)** y cabecera `Retry-After: 3600` para no perjudicar el posicionamiento SEO.
- **Bypass por IP:** Permite que clientes o revisores naveguen normalmente desde sus direcciones IP.
- **Bypass por Enlace Secreto:** Permite generar una URL con parámetro (`?ops_preview=clave_secreta`) que fija una cookie segura de 24 horas para acceder al sitio sin necesidad de crearles una cuenta de usuario.

### 2. Limpieza de Base de Datos en 1 Clic
- Purga transients expirados de la tabla `wp_options`.
- Elimina revisiones antiguas acumuladas en `wp_posts`.
- Vacía comentarios de spam y papelera.
- Limpia metadatos huérfanos en `wp_postmeta`.

### 3. Depurador de Correo (Mail Debugger & Blackhole)
- Registra de forma rotativa los últimos 50 correos emitidos mediante `wp_mail` (destinatario, asunto, estado y fecha).
- **Modo Blackhole (Simulación):** Impide que WordPress envíe correos a clientes reales por accidente en entornos locales (Laragon) o servidores de staging/pruebas.

---

## 📄 Licencia

Distribuido bajo licencia **GPL-2.0-or-later**.
