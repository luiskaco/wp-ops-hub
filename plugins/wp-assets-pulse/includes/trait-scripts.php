<?php
/**
 * Módulo de optimización de carga de JavaScript (Deferring).
 *
 * @package WP_Assets_Pulse
 */

defined( 'ABSPATH' ) || exit;

trait WP_Assets_Pulse_Scripts {

	/**
	 * Inicializa los hooks de carga de scripts.
	 *
	 * @return void
	 */
	public static function init_scripts_hooks() {
		$settings = self::get_settings();

		if ( ! empty( $settings['defer_js_enabled'] ) ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'add_defer_attribute' ), 10, 3 );
		}
	}

	/**
	 * Inyecta el atributo defer a las etiquetas <script> encoladas, respetando exclusiones.
	 *
	 * @param string $tag    Etiqueta HTML <script>.
	 * @param string $handle Handle del script registrado.
	 * @param string $src    URL del script.
	 * @return string
	 */
	public static function add_defer_attribute( $tag, $handle, $src ) {
		// No aplicar defer en el panel administrativo
		if ( is_admin() ) {
			return $tag;
		}

		// Si ya tiene defer o async, omitir
		if ( false !== strpos( $tag, 'defer' ) || false !== strpos( $tag, 'async' ) ) {
			return $tag;
		}

		$settings   = self::get_settings();
		$exclusions = ! empty( $settings['defer_js_exclusions'] ) ? explode( "\n", str_replace( "\r", '', $settings['defer_js_exclusions'] ) ) : array();
		$exclusions = array_filter( array_map( 'trim', $exclusions ) );

		// Verificar si el handle está excluido
		foreach ( $exclusions as $excluded ) {
			if ( '' !== $excluded && ( $handle === $excluded || false !== strpos( $handle, $excluded ) ) ) {
				return $tag;
			}
		}

		// Inyectar atributo defer antes de src
		return str_replace( ' src', ' defer src', $tag );
	}
}
