<?php
/**
 * Módulo de eliminación de recursos y scripts innecesarios (Debloat).
 *
 * @package WP_Assets_Pulse
 */

defined( 'ABSPATH' ) || exit;

trait WP_Assets_Pulse_Debloat {

	/**
	 * Inicializa los hooks de limpieza de recursos front-end.
	 *
	 * @return void
	 */
	public static function init_debloat_hooks() {
		$settings = self::get_settings();

		// 1. Desactivación de Emojis
		if ( ! empty( $settings['disable_emojis'] ) ) {
			add_action( 'init', array( __CLASS__, 'disable_emojis' ) );
		}

		// 2. Desactivación de CSS de Bloques Gutenberg en Front-End
		if ( ! empty( $settings['disable_block_css'] ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_block_css' ), 100 );
		}

		// 3. Desactivación de Estilos Globales Inline (SVG Filters de WP 5.9+)
		if ( ! empty( $settings['disable_global_styles'] ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_global_styles' ), 100 );
			remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		}
	}

	/**
	 * Remueve por completo la carga de emojis nativos de WordPress.
	 *
	 * @return void
	 */
	public static function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter(
			'tiny_mce_plugins',
			function( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
			}
		);

		add_filter(
			'wp_resource_hints',
			function( $urls, $relation_type ) {
				if ( 'dns-prefetch' === $relation_type ) {
					$urls = array_filter(
						$urls,
						function( $url ) {
							return false === strpos( $url, 'https://s.w.org/images/core/emoji/' );
						}
					);
				}
				return $urls;
			},
			10,
			2
		);
	}

	/**
	 * Desencola el CSS de bloques de Gutenberg en el front-end.
	 *
	 * @return void
	 */
	public static function disable_block_css() {
		if ( is_admin() ) {
			return;
		}

		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-blocks-style' );
		wp_dequeue_style( 'classic-theme-styles' );
	}

	/**
	 * Remueve los estilos globales inline generados por theme.json.
	 *
	 * @return void
	 */
	public static function disable_global_styles() {
		if ( is_admin() ) {
			return;
		}

		wp_dequeue_style( 'global-styles' );
	}
}
