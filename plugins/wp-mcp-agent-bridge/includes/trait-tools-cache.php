<?php
/**
 * Herramientas de Purga y Rendimiento de Caché para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Tools_Cache {

	/**
	 * Registra herramientas de caché si hay algún plugin de caché activo.
	 *
	 * @return array
	 */
	public static function get_cache_tools() {
		return array(
			// Estado de caché
			'cache-get-status' => self::define_tool(
				'cache-get-status',
				'Consultar estado de caché',
				'Detecta qué plugins de optimización y caché están activos (LiteSpeed, WP Rocket, WP Super Cache, etc.).',
				'cache',
				true,
				array( __CLASS__, 'tool_cache_get_status' ),
				array(),
				array(),
				array( 'cap' => 'manage_options' )
			),

			// Purgar caché
			'cache-purge' => self::define_tool(
				'cache-purge',
				'Purgar caché del sitio',
				'Vacía la caché global de páginas o purga la URL de un post específico tras realizar modificaciones.',
				'cache',
				false,
				array( __CLASS__, 'tool_cache_purge' ),
				array(
					'post_id' => self::schema_int( 'ID del post a purgar individualmente. Si se omite, purga todo el sitio.', 1 ),
				),
				array(),
				array( 'cap' => 'manage_options' )
			),
		);
	}

	/**
	 * Callback para cache-get-status.
	 */
	public static function tool_cache_get_status( $args ) {
		return array(
			'litespeed'      => defined( 'LSCWP_V' ),
			'wp_rocket'      => defined( 'WP_ROCKET_VERSION' ),
			'wp_super_cache' => function_exists( 'wp_cache_clean_cache' ),
			'w3_total_cache' => defined( 'W3TC' ),
		);
	}

	/**
	 * Callback para cache-purge.
	 */
	public static function tool_cache_purge( $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$purged  = array();

		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			if ( $post_id > 0 && function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
				$purged[] = 'WP Rocket (Post ID: ' . $post_id . ')';
			} else {
				rocket_clean_domain();
				$purged[] = 'WP Rocket (Todo el sitio)';
			}
		}

		// LiteSpeed Cache
		if ( defined( 'LSCWP_V' ) && class_exists( 'LiteSpeed\Purge' ) ) {
			if ( $post_id > 0 ) {
				do_action( 'litespeed_purge_post', $post_id );
				$purged[] = 'LiteSpeed (Post ID: ' . $post_id . ')';
			} else {
				do_action( 'litespeed_purge_all' );
				$purged[] = 'LiteSpeed (Todo el sitio)';
			}
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, true );
			$purged[] = 'WP Super Cache';
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			if ( $post_id > 0 && function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $post_id );
				$purged[] = 'W3 Total Cache (Post ID: ' . $post_id . ')';
			} else {
				w3tc_flush_all();
				$purged[] = 'W3 Total Cache (Todo el sitio)';
			}
		}

		if ( empty( $purged ) ) {
			return array(
				'purged'  => false,
				'message' => __( 'No se detectó ningún plugin de caché activo soportado.', 'wp-mcp-agent-bridge' ),
			);
		}

		return array(
			'purged'   => true,
			'systems'  => $purged,
			'message'  => __( 'Caché purgada exitosamente.', 'wp-mcp-agent-bridge' ),
		);
	}
}
