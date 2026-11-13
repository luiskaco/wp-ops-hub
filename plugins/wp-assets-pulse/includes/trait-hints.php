<?php
/**
 * Módulo de inyección de Resource Hints (Preconnect) para fuentes y CDNs.
 *
 * @package WP_Assets_Pulse
 */

defined( 'ABSPATH' ) || exit;

trait WP_Assets_Pulse_Hints {

	/**
	 * Inicializa los hooks de resource hints.
	 *
	 * @return void
	 */
	public static function init_hints_hooks() {
		add_filter( 'wp_resource_hints', array( __CLASS__, 'add_preconnect_hints' ), 10, 2 );
	}

	/**
	 * Inyecta enlaces rel="preconnect" para los dominios configurados.
	 *
	 * @param array  $urls          Lista de URLs.
	 * @param string $relation_type Tipo de relación (preconnect, dns-prefetch, etc.).
	 * @return array
	 */
	public static function add_preconnect_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type || is_admin() ) {
			return $urls;
		}

		$settings = self::get_settings();
		$domains  = ! empty( $settings['preconnect_domains'] ) ? explode( "\n", str_replace( "\r", '', $settings['preconnect_domains'] ) ) : array();
		$domains  = array_filter( array_map( 'trim', $domains ) );

		foreach ( $domains as $domain ) {
			if ( '' !== $domain ) {
				$urls[] = esc_url_raw( $domain );
			}
		}

		return array_unique( $urls );
	}
}
