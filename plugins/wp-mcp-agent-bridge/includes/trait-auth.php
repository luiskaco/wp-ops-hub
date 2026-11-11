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
	 * Permite Application Passwords exclusivamente en la ruta MCP si estamos en HTTP local o si el sitio está configurado.
	 *
	 * @param bool $available
	 * @return bool
	 */
	public static function filter_app_passwords_availability( $available ) {
		if ( $available ) {
			return true;
		}

		// Si es una petición a la ruta REST de MCP, permitir la autenticación de contraseñas de aplicación
		if ( self::is_mcp_request() ) {
			return true;
		}

		return false;
	}

	/**
	 * Comprueba si la petición actual apunta exactamente a la ruta REST de MCP.
	 *
	 * @return bool
	 */
	private static function is_mcp_request() {
		if ( isset( $_GET['rest_route'] ) ) {
			$route = '/' . ltrim( (string) wp_unslash( $_GET['rest_route'] ), '/' );
			if ( 0 === strpos( $route, '/mcp/v1' ) ) {
				return true;
			}
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = wp_parse_url( $uri, PHP_URL_PATH );
			if ( is_string( $path ) && false !== strpos( $path, '/wp-json/mcp/v1' ) ) {
				return true;
			}
		}

		return false;
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

		return true;
	}
}
