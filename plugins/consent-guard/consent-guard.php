<?php
/**
 * Plugin Name:       Consent Guard
 * Plugin URI:        https://github.com/luiskaco/utilities_wordpress
 * Description:       Banner de consentimiento de cookies (RGPD y leyes de EE. UU.): categorías, bloqueo de scripts apto para caché, Google Consent Mode v2, registro de consentimientos exportable y escáner pasivo de cookies.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.4
 * Author:            WordPress AI Utilities
 * License:           GPL-2.0-or-later
 * Text Domain:       consent-guard
 *
 * @package ConsentGuard
 */

// Este archivo solo usa sintaxis compatible con PHP 7.4: así puede avisar en versiones
// antiguas en vez de romper el sitio. El código de includes/ exige PHP 8.4 (ADR-007).

defined( 'ABSPATH' ) || exit;

if ( defined( 'CONSENT_GUARD_FILE' ) ) {
	return;
}

define( 'CONSENT_GUARD_FILE', __FILE__ );
define( 'CONSENT_GUARD_MIN_PHP', '8.4' );

if ( version_compare( PHP_VERSION, CONSENT_GUARD_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p><strong>Consent Guard</strong> %s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: versión mínima de PHP, 2: versión de PHP del servidor */
						__( 'requiere PHP %1$s o superior. Tu servidor usa PHP %2$s, por eso el plugin no se cargó.', 'consent-guard' ),
						CONSENT_GUARD_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	register_activation_hook(
		__FILE__,
		static function () {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: versión mínima de PHP */
						__( 'Consent Guard requiere PHP %s o superior.', 'consent-guard' ),
						CONSENT_GUARD_MIN_PHP
					)
				),
				'',
				array( 'back_link' => true )
			);
		}
	);
	return;
}

require_once __DIR__ . '/includes/autoload.php';

register_activation_hook( __FILE__, array( 'ConsentGuard\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ConsentGuard\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'ConsentGuard\\Plugin', 'boot' ) );
