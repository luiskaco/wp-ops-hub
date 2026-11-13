<?php
/**
 * Plugin Name:       WP Assets Pulse
 * Plugin URI:        https://github.com/luiskaco/wp-ops-hub
 * Description:       Optimización ultraligera de recursos en front-end (WPO): eliminación de emojis y CSS innecesario, carga diferida de scripts (defer) y preconexiones de fuentes (resource hints).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WordPress Performance Toolkit
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-assets-pulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_ASSETS_PULSE_FILE' ) ) {
	define( 'WP_ASSETS_PULSE_FILE', __FILE__ );
}

// Carga de módulos por traits
require_once __DIR__ . '/includes/trait-debloat.php';
require_once __DIR__ . '/includes/trait-scripts.php';
require_once __DIR__ . '/includes/trait-hints.php';
require_once __DIR__ . '/includes/trait-admin.php';

final class WP_Assets_Pulse {

	use WP_Assets_Pulse_Debloat,
		WP_Assets_Pulse_Scripts,
		WP_Assets_Pulse_Hints,
		WP_Assets_Pulse_Admin;

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Clave de opciones en wp_options.
	 */
	public const OPTION_KEY = 'wp_assets_pulse_settings';

	/**
	 * Instancia Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Obtener instancia Singleton.
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
		self::init_debloat_hooks();
		self::init_scripts_hooks();
		self::init_hints_hooks();

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
			'disable_emojis'        => true,
			'disable_block_css'     => false,
			'disable_global_styles' => false,
			'defer_js_enabled'      => false,
			'defer_js_exclusions'   => "jquery\njquery-core",
			'preconnect_domains'    => "https://fonts.googleapis.com\nhttps://fonts.gstatic.com",
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
}

// Inicialización
WP_Assets_Pulse::instance();
