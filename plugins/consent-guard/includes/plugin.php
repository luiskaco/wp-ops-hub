<?php
/**
 * Punto de entrada: registra los módulos y gestiona activación y desactivación.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public const string VERSION = '1.0.0';
	public const string SLUG    = 'consent-guard';

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		ConsentLog::maybeInstall();
		add_action( ConsentLog::CRON_HOOK, array( ConsentLog::class, 'prune' ) );

		RestController::register();
		Shortcodes::register();
		Updater::register();

		if ( is_admin() ) {
			Admin\AdminPage::register();
			return;
		}

		if ( Settings::bool( 'enabled' ) ) {
			Frontend::register();
			ScriptBlocker::register();
		}
	}

	public static function activate(): void {
		ConsentLog::maybeInstall();
		ConsentLog::scheduleCron();

		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( ConsentLog::CRON_HOOK );
	}

	/**
	 * Vista previa de un maquetador visual: ahí no se muestra el banner ni se bloquea nada.
	 * Solo cuenta si el usuario puede editar, para que un visitante no lo desactive con un parámetro.
	 */
	public static function isBuilderPreview(): bool {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura de la URL.
		$keys = array( 'elementor-preview', 'fl_builder', 'et_fb', 'bricks', 'brickspreview', 'ct_builder', 'fb-edit', 'pagelayer-live', 'vcv-editable' );
		if ( array_any( $keys, static fn ( string $key ): bool => isset( $_GET[ $key ] ) ) ) {
			return true;
		}

		return isset( $_GET['vc_action'] ) && 'vc_inline' === $_GET['vc_action'];
		// phpcs:enable
	}
}
