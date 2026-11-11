<?php
/**
 * Plugin Name:       WP MCP Agent Bridge
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Servidor nativo Model Context Protocol (MCP) para conectar asistentes de IA (Cursor, Antigravity, Claude Desktop) con WordPress, WooCommerce, Medios (URL y Base64), SEO y Caché.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WordPress AI Utilities
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-mcp-agent-bridge
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_MCP_AGENT_BRIDGE_FILE' ) ) {
	define( 'WP_MCP_AGENT_BRIDGE_FILE', __FILE__ );
}

// Carga de módulos estructurados por traits
require_once __DIR__ . '/includes/trait-support.php';
require_once __DIR__ . '/includes/trait-auth.php';
require_once __DIR__ . '/includes/trait-server.php';
require_once __DIR__ . '/includes/trait-tools-core.php';
require_once __DIR__ . '/includes/trait-tools-media.php';
require_once __DIR__ . '/includes/trait-tools-woo.php';
require_once __DIR__ . '/includes/trait-tools-seo.php';
require_once __DIR__ . '/includes/trait-tools-cache.php';
require_once __DIR__ . '/includes/trait-admin.php';

final class WP_MCP_Agent_Bridge {

	use WP_MCP_Bridge_Support,
		WP_MCP_Bridge_Auth,
		WP_MCP_Bridge_Server,
		WP_MCP_Bridge_Tools_Core,
		WP_MCP_Bridge_Tools_Media,
		WP_MCP_Bridge_Tools_Woo,
		WP_MCP_Bridge_Tools_SEO,
		WP_MCP_Bridge_Tools_Cache,
		WP_MCP_Bridge_Admin;

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Clave principal de opciones en wp_options.
	 */
	public const OPTION_KEY = 'wp_mcp_agent_bridge_settings';

	/**
	 * Clave del registro de auditoría en wp_options.
	 */
	public const LOG_KEY = 'wp_mcp_agent_bridge_log';

	/**
	 * Límite máximo de eventos en el log rotativo.
	 */
	public const MAX_LOG_ENTRIES = 50;

	/**
	 * Instancia Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Acceso a la instancia única.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		self::init_auth();
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		if ( is_admin() ) {
			self::init_admin();
		}
	}

	/**
	 * Ajustes por defecto del plugin.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'enabled'              => true,
			'allow_writes'         => true,
			'allow_deletes'        => false,
			'modules'              => array(
				'core'        => true,
				'media'       => true,
				'woocommerce' => true,
				'seo'         => true,
				'cache'       => true,
			),
			'activity_log_enabled' => true,
		);
	}

	/**
	 * Obtiene los ajustes combinados con los valores por defecto.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_default_settings() );
	}

	/**
	 * Recopila todas las herramientas activas según los módulos habilitados.
	 *
	 * @return array
	 */
	public static function get_active_tools() {
		$settings = self::get_settings();
		$tools    = array();

		if ( ! empty( $settings['modules']['core'] ) ) {
			$tools = array_merge( $tools, self::get_core_tools() );
		}

		if ( ! empty( $settings['modules']['media'] ) ) {
			$tools = array_merge( $tools, self::get_media_tools() );
		}

		if ( ! empty( $settings['modules']['woocommerce'] ) ) {
			$tools = array_merge( $tools, self::get_woo_tools() );
		}

		if ( ! empty( $settings['modules']['seo'] ) ) {
			$tools = array_merge( $tools, self::get_seo_tools() );
		}

		if ( ! empty( $settings['modules']['cache'] ) ) {
			$tools = array_merge( $tools, self::get_cache_tools() );
		}

		return apply_filters( 'wp_mcp_bridge_tools', $tools );
	}
}

// Inicialización
WP_MCP_Agent_Bridge::instance();
