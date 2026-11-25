<?php
/**
 * Shortcodes:
 *  [consent_guard_preferences label="Cambiar preferencias"]  Botón que abre el panel.
 *  [consent_guard_cookies]                                   Tabla de cookies por categoría.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public static function register(): void {
		add_shortcode( 'consent_guard_preferences', array( self::class, 'preferencesButton' ) );
		add_shortcode( 'consent_guard_cookies', array( self::class, 'cookieTable' ) );
	}

	/**
	 * @param array<string, string>|string $atts Atributos.
	 */
	public static function preferencesButton( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'label' => __( 'Cambiar preferencias de cookies', 'consent-guard' ) ),
			is_array( $atts ) ? $atts : array(),
			'consent_guard_preferences'
		);

		return sprintf(
			'<button type="button" class="cg-inline-button" data-cg-open>%s</button>',
			esc_html( mb_substr( (string) $atts['label'], 0, 80 ) )
		);
	}

	public static function cookieTable(): string {
		ob_start();
		echo '<div class="cg-cookie-list">';
		foreach ( CookieRegistry::grouped() as $key => $cookies ) {
			$category = Category::tryFrom( (string) $key );
			if ( null === $category || array() === $cookies ) {
				continue;
			}
			printf( '<h3 class="cg-cookie-list-title">%s</h3>', esc_html( $category->label() ) );
			printf( '<p class="cg-cookie-list-desc">%s</p>', esc_html( $category->description() ) );
			Frontend::cookieTable( $cookies );
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
