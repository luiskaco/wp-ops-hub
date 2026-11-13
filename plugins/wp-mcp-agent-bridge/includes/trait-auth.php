<?php
/**
 * Autenticación y permisos para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Auth {

	/**
	 * Hook para inicializar filtros de autenticación.
	 *
	 * @return void
	 */
	public static function init_auth() {
		// Habilita Application Passwords en HTTP local (localhost / staging) solo para la ruta MCP
		add_filter( 'wp_is_application_passwords_available', array( __CLASS__, 'filter_app_passwords_availability' ) );
	}

	/**
	 * Permite Application Passwords en la ruta MCP si hay HTTPS o entorno local.
	 *
	 * @param bool $available
	 * @return bool
	 */
	public static function filter_app_passwords_availability( $available ) {
		if ( $available ) {
			return true;
		}

		// Solo habilitar en HTTP plano cuando el entorno es local; en producción requiere HTTPS.
		if ( self::is_mcp_request() && ( is_ssl() || self::is_local_environment() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Detecta si el sitio corre en un entorno local de desarrollo.
	 *
	 * @return bool
	 */
	private static function is_local_environment() {
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}

		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
			|| '.local' === substr( $host, -6 )
			|| '.test' === substr( $host, -5 );
	}

	/**
	 * Comprueba si la petición actual apunta exactamente a la ruta REST de MCP.
	 *
	 * @return bool
	 */
	private static function is_mcp_request() {
		if ( isset( $_GET['rest_route'] ) ) {
			$route = '/' . ltrim( sanitize_text_field( (string) wp_unslash( $_GET['rest_route'] ) ), '/' );
			if ( 0 === strpos( $route, '/mcp/v1' ) ) {
				return true;
			}
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri    = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			$path   = wp_parse_url( $uri, PHP_URL_PATH );
			$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/mcp/v1';
			if ( is_string( $path ) && false !== strpos( $path, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Aplica rate limiting por usuario/IP para evitar ráfagas abusivas.
	 *
	 * @return bool|WP_Error
	 */
	private static function check_rate_limit() {
		$user_id = get_current_user_id();
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key     = 'wp_mcp_rl_' . md5( $user_id . '|' . $ip );
		$limit   = (int) apply_filters( 'wp_mcp_bridge_rate_limit', 120 );
		$count   = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return new WP_Error(
				'mcp_rate_limited',
				__( 'Límite de solicitudes MCP excedido. Intenta de nuevo en un minuto.', 'wp-mcp-agent-bridge' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Comprobación de permisos de acceso al endpoint MCP.
	 * Exige usuario autenticado con al menos capacidad de edición (edit_posts) o gestión (manage_options).
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function check_permissions( $request ) {
		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'mcp_disabled', __( 'El servidor MCP está deshabilitado en los ajustes.', 'wp-mcp-agent-bridge' ), array( 'status' => 503 ) );
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'mcp_unauthorized',
				__( 'Se requiere autenticación mediante Application Passwords de WordPress.', 'wp-mcp-agent-bridge' ),
				array( 'status' => 401 )
			);
		}

		$user = wp_get_current_user();
		if ( ! $user || ! ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ) ) ) {
			return new WP_Error(
				'mcp_forbidden',
				__( 'El usuario autenticado no posee permisos suficientes para interactuar con MCP.', 'wp-mcp-agent-bridge' ),
				array( 'status' => 403 )
			);
		}

		return self::check_rate_limit();
	}
}
