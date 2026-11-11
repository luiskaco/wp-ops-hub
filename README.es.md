# WP Ops Hub (WordPress Operations & Intelligence Hub)

[![Compatibilidad PHP](https://img.shields.io/badge/PHP-7.4%20a%208.5-777bb4?style=flat-square&logo=php)](https://www.php.net)
[![Versión WordPress](https://img.shields.io/badge/WordPress-5.8+-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![Licencia: GPL-2.0-or-later](https://img.shields.io/badge/Licencia-GPL--2.0--or--later-blue?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Cero Dependencias](https://img.shields.io/badge/Dependencias-Cero%20Externas-success?style=flat-square)](#-filosofía-de-ingeniería)

> **Ecosistema de micro-plugins modulares, portables y de nivel empresarial para operaciones, seguridad, DevOps e integraciones de IA en WordPress.**

🌍 **Documentación:** [Read in English](README.md) | [Leer en Español](README.es.md)

---

## 🌟 Visión General

**WP Ops Hub** es un repositorio centralizado de micro-plugins ultraligeros y listos para producción, diseñados específicamente para sitios de alto rendimiento, agencias de desarrollo web y flujos modernos de DevOps.

Cada plugin ubicado dentro de `plugins/` es 100% independiente, no depende de librerías externas pesadas ni compiladores, y respeta estrictamente las APIs nativas de WordPress y los estándares de PHP modernos.

---

## 📦 Catálogo de Plugins

| Plugin | Categoría | Versión | Estado | Capacidades Principales |
|---|---|---|---|---|
| [**Essential Hardening & Security**](./plugins/essential-hardening/) | Seguridad & Hardening | `1.8.0` | `Producción` | 24 escudos defensivos: Micro-WAF contra SQLi/XSS/RCE/LFI, rate limiting de login con retardo disuasivo (tarpit), honeypot anti-bots en login, 2FA por correo, verificador de integridad del núcleo (checksums oficiales), protección de carpeta uploads, bloqueo de enumeración de usuarios y mini-log rotativo de eventos. |
| [**WP MCP Agent Bridge**](./plugins/wp-mcp-agent-bridge/) | IA & Integraciones | `1.0.0` | `Producción` | Servidor nativo **Model Context Protocol (MCP)** mediante HTTP Streamable JSON-RPC 2.0. Conecta asistentes de IA (Cursor, Claude Desktop, Antigravity, VS Code) para operar contenido, subir imágenes (vía URL o Base64), gestionar tiendas WooCommerce completas, optimizar SEO multigestor y purgar caché. |
| [**WP Site Ops Toolkit**](./plugins/wp-site-ops-toolkit/) | Operaciones & Mantenimiento | `1.0.0` | `Producción` | Modo mantenimiento controlado (código HTTP 503) con bypass por IP o enlace secreto con cookie temporal para clientes, optimizador/limpiador de base de datos en 1 clic (transients, revisiones, spam, postmeta huérfano) y depurador de correo (`wp_mail`) con modo simulación (blackhole). |
| [**Modern WP Migrator**](./plugins/modern-wp-migrator/) | Migración & Backups | `7.113.4` | `Producción` | Motor de migración sin restricciones: límite de subida desbloqueado a 500 GB, restauración en 1 clic directa, sin telemetría ni publicidad, compatible con PHP 8.3+. |
| [**WP LLMs.txt Manager**](./plugins/wp-llms-txt/) | SEO & Inteligencia Artificial | `1.1.0` | `Producción` | Generador, editor y endpoint virtual para servir archivos estandarizados `/llms.txt` y `/llms-full.txt` optimizados para indexación por LLMs (llmstxt.org). |

---

## 🎯 Filosofía de Ingeniería

- **Zero Bloat & Cero Dependencias Externas:** Sin necesidad de Node.js o herramientas de build complejas para el despliegue, sin telemetría invasiva y sin gateways de API externas.
- **Portabilidad Total:** Cada subcarpeta en `plugins/` es un plugin independiente que puede copiarse directamente a cualquier directorio `wp-content/plugins/` o `wp-content/mu-plugins/`.
- **Sin Corrupción del Esquema SQL:** Las configuraciones y registros rotativos usan tablas nativas de WordPress (`wp_options` con `autoload = false`) y transients. Cero sentencias `CREATE TABLE` innecesarias.
- **Estándares Modernos de PHP:** Código modular estructurado mediante traits de PHP, sanitización estricta de entradas, escape de salidas y compatibilidad garantizada desde PHP 7.4 hasta PHP 8.5.

---

## 🚀 Instalación y Uso

### Método 1: Plugin Estándar
1. Descarga o clona este repositorio.
2. Copia la carpeta del plugin deseado desde `plugins/<nombre-del-plugin>/` a la carpeta `wp-content/plugins/` de tu instalación WordPress.
3. Ve a **Plugins > Plugins Instalados** en tu escritorio de WordPress y haz clic en **Activar**.

### Método 2: Must-Use Plugin (`mu-plugins`)
Para entornos donde los clientes no deben desactivar por error plugins críticos de operaciones o seguridad:
1. Copia el archivo principal o carpeta del plugin dentro de `wp-content/mu-plugins/`.
2. WordPress lo ejecutará de forma automática y transparente en cada petición.

---

## 📚 Documentación Técnica Detallada

- [Guía técnica de Essential Hardening & Security](./docs/essential-hardening.md)
- [Guía técnica de WP MCP Agent Bridge](./docs/wp-mcp-agent-bridge.md)
- [Guía técnica de WP Site Ops Toolkit](./docs/wp-site-ops-toolkit.md)
- [Guía técnica de Modern WP Migrator](./docs/modern-wp-migrator.md)
- [Guía técnica de WP LLMs.txt Manager](./docs/wp-llms-txt.md)
- [Guía para agregar y desarrollar nuevos plugins](./docs/DEVELOPMENT.md)

---

## 🧪 Pruebas Automatizadas

Cada plugin incluye su propia suite de pruebas unitarias y de integración en `tests/<plugin>/run.php`. Los tests corren directamente sobre el WordPress local sin requerir librerías pesadas adicionales.

Para ejecutar las pruebas:
```bash
# Ejemplo: Pruebas automatizadas de WP MCP Agent Bridge
php tests/wp-mcp-agent-bridge/run.php

# Ejemplo: Pruebas de WP Site Ops Toolkit
php tests/wp-site-ops-toolkit/run.php
```

---

## 📄 Licencia

Todos los plugins de este repositorio se distribuyen bajo licencia de código abierto **GNU General Public License v2.0 or later** ([GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)).
