<?php
/**
 * Plugin Name:       WP Site Ops Toolkit
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Suite ligera de operaciones para WordPress: modo mantenimiento (HTTP 503) con bypass por IP o enlace secreto, saneamiento de base de datos en 1 clic y depurador/blackhole de correos salientes (wp_mail).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WordPress Operations Toolkit
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-site-ops-toolkit
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_SITE_OPS_TOOLKIT_FILE' ) ) {
	define( 'WP_SITE_OPS_TOOLKIT_FILE', __FILE__ );
}

// Cargar módulos por traits
require_once __DIR__ . '/includes/trait-maintenance.php';
require_once __DIR__ . '/includes/trait-db-cleaner.php';
require_once __DIR__ . '/includes/trait-mail-debugger.php';
require_once __DIR__ . '/includes/trait-admin.php';

final class WP_Site_Ops_Toolkit {

	use WP_Site_Ops_Maintenance,
		WP_Site_Ops_Db_Cleaner,
		WP_Site_Ops_Mail_Debugger,
		WP_Site_Ops_Admin;

	/**
	 * Versión del plugin.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Clave de opciones en wp_options.
	 */
	public const OPTION_KEY = 'wp_site_ops_toolkit_settings';

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
		self::init_maintenance_hooks();
		self::init_mail_debugger_hooks();

		if ( is_admin() ) {
			self::init_admin();
		}
	}

	/**
	 * Ajustes por defecto.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'maintenance_enabled'      => false,
			'maintenance_title'        => __( 'Sitio en mantenimiento', 'wp-site-ops-toolkit' ),
			'maintenance_message'      => __( 'Estamos realizando tareas de mantenimiento programadas. Volveremos enseguida.', 'wp-site-ops-toolkit' ),
			'maintenance_ip_whitelist' => '',
			'maintenance_secret_key'   => '',
			'db_cleaner_enabled'       => true,
			'mail_debugger_enabled'    => false,
			'mail_blackhole_mode'      => false,
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
	 * Devuelve la IP del visitante sanitizada.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
	}
}

// Inicialización
WP_Site_Ops_Toolkit::instance();
