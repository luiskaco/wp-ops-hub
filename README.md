# WP Ops Hub (WordPress Operations & Intelligence Hub)

[![PHP Compatibility](https://img.shields.io/badge/PHP-7.4%20to%208.5-777bb4?style=flat-square&logo=php)](https://www.php.net)
[![WordPress Target](https://img.shields.io/badge/WordPress-5.8+-21759b?style=flat-square&logo=wordpress)](https://wordpress.org)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-Zero%20External-success?style=flat-square)](#-engineering-philosophy)

> **Enterprise-grade, modular, and portable micro-plugin ecosystem for WordPress engineering, operations, security, and AI integrations.**

🌍 **Documentation:** [Read in English](README.md) | [Leer en Español](README.es.md)

---

## 🌟 Overview

**WP Ops Hub** is a curated monorepo of lightweight, production-ready WordPress micro-plugins designed for high-performance sites, digital agencies, and modern DevOps workflows. 

Every plugin inside `plugins/` is completely standalone, decoupled from third-party bloat, and adheres strictly to native WordPress APIs and PHP best practices.

---

## 📦 Plugin Suite & Catalog

| Plugin | Domain | Version | Status | Key Capabilities |
|---|---|---|---|---|
| [**Essential Hardening & Security**](./plugins/essential-hardening/) | Security & Defense | `1.8.0` | `Production` | 24 defensive shields: Micro-WAF (SQLi/XSS/RCE/LFI), login rate-limiting with tarpit delay, anti-bot honeypot, email 2FA, core integrity verifier (checksums), upload directory protection, user enumeration blocking, and security event mini-log. |
| [**WP MCP Agent Bridge**](./plugins/wp-mcp-agent-bridge/) | AI & Integraciones | `1.0.2` | `Production` | Built-in native **Model Context Protocol (MCP)** server over Streamable HTTP JSON-RPC 2.0. Connect AI coding agents (Cursor, Claude Desktop, Antigravity, VS Code) to manage posts, media (URL & Base64 uploads), full WooCommerce store operations, multi-engine SEO, and cache purging. |
| [**WP Site Ops Toolkit**](./plugins/wp-site-ops-toolkit/) | DevOps & Maintenance | `1.0.0` | `Production` | Maintenance mode (HTTP 503) with client secret-link & IP allowlisting, 1-click database sanitation (expired transients, post revisions, trash, orphan postmeta), and outgoing email logger with blackhole simulation mode. |
| [**WP Assets Pulse**](./plugins/wp-assets-pulse/) | Performance & WPO | `1.0.0` | `Production` | Front-end asset delivery optimization: WordPress core debloat (emojis, block CSS, inline SVG filters), non-blocking JavaScript loading (`defer` with exclusions), and DNS preconnections for external fonts. |
| [**Modern WP Migrator**](./plugins/modern-wp-migrator/) | Backups & Migration | `7.113.4` | `Production` | Unrestricted site migration and backup engine: unlocked 500 GB file upload limit, direct 1-click restoration, zero telemetry/trackers, fully compatible with PHP 8.3+. |
| [**WP LLMs.txt Manager**](./plugins/wp-llms-txt/) | Semantic AI & SEO | `1.1.0` | `Production` | Virtual routing generator and management editor for standard `/llms.txt` and `/llms-full.txt` files according to [llmstxt.org](https://llmstxt.org) specs for LLM web indexing. |

---

## 🎯 Engineering Philosophy

- **Zero Bloat & Zero External Runtimes:** No Node.js build pipelines required for deployment, no heavy framework wrappers, no third-party tracking, and no external API gateways.
- **True Portability:** Every subfolder inside `plugins/` is an independent plugin that can be dropped into any standard `wp-content/plugins/` or `wp-content/mu-plugins/` directory.
- **No SQL Schema Pollutions:** Settings and rotating logs leverage core WordPress options (`wp_options` with `autoload = false`) and transients. No unnecessary `CREATE TABLE` executions.
- **Modern PHP Standards:** Fully typed traits, strict input sanitization, output escaping, and defensive programming tested across PHP 7.4 through PHP 8.5.

---

## 🚀 Installation & Usage

### Option 1: Standard Plugin Installation
1. Clone or download this repository.
2. Copy the desired plugin folder from `plugins/<plugin-name>/` to your WordPress site's `wp-content/plugins/` directory.
3. In your WordPress Admin panel, navigate to **Plugins > Installed Plugins** and click **Activate**.

### Option 2: Must-Use Plugin (`mu-plugins`)
For production environments where client admins shouldn't inadvertently deactivate critical operational or hardening layers:
1. Copy the plugin file or folder into `wp-content/mu-plugins/`.
2. WordPress will automatically execute it on every request without requiring manual activation.

---

## 📚 Technical Documentation

- [Essential Hardening & Security Technical Guide](./docs/essential-hardening.md)
- [WP MCP Agent Bridge Technical Guide](./docs/wp-mcp-agent-bridge.md)
- [WP Site Ops Toolkit Technical Guide](./docs/wp-site-ops-toolkit.md)
- [WP Assets Pulse Technical Guide](./docs/wp-assets-pulse.md)
- [Modern WP Migrator Technical Guide](./docs/modern-wp-migrator.md)
- [WP LLMs.txt Manager Technical Guide](./docs/wp-llms-txt.md)
- [Developer & Release Guidelines](./docs/DEVELOPMENT.md)

---

## 🧪 Testing & Verification

Every plugin includes an automated standalone test runner located in `tests/<plugin>/run.php`. Tests execute against a local WordPress instance without needing bloated external harnesses.

To execute tests for any plugin:
```bash
# Example: Running WP MCP Agent Bridge automated suite
php tests/wp-mcp-agent-bridge/run.php

# Example: Running Site Operations suite
php tests/wp-site-ops-toolkit/run.php
```

---

## 📄 License

All plugins in this repository are open-source software licensed under the **GNU General Public License v2.0 or later** ([GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)).
