<?php
/**
 * Acciones del panel (admin-post.php). Cada una exige nonce propio y manage_options.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard\Admin;

use ConsentGuard\ConsentLog;
use ConsentGuard\CookieDatabase;
use ConsentGuard\CookieRegistry;
use ConsentGuard\Scanner;
use ConsentGuard\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminActions {

	public const string CAPABILITY = 'manage_options';

	/** Acción => método. El nonce de cada formulario se llama igual que la acción. */
	public const array ACTIONS = array(
		'consent_guard_save'           => 'save',
		'consent_guard_bump_revision'  => 'bumpRevision',
		'consent_guard_scan'           => 'scan',
		'consent_guard_cookie_add'     => 'addCookie',
		'consent_guard_cookie_update'  => 'updateCookies',
		'consent_guard_cookie_delete'  => 'deleteCookie',
		'consent_guard_export'         => 'export',
	);

	public static function register(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			add_action(
				'admin_post_' . $action,
				static function () use ( $action, $method ): void {
					self::guard( $action );
					self::$method();
				}
			);
		}
	}

	/**
	 * Nonce + permiso. Corta la ejecución si algo falla.
	 */
	private static function guard( string $action ): void {
		check_admin_referer( $action );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'consent-guard' ), '', array( 'response' => 403 ) );
		}
	}

	private static function save(): void {
		$tab = sanitize_key( self::post( 'tab' ) );
		if ( ! in_array( $tab, array( 'banner', 'scripts', 'log' ), true ) ) {
			$tab = 'banner';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Settings::sanitizeTab sanea campo por campo.
		$input = isset( $_POST['cg'] ) && is_array( $_POST['cg'] ) ? wp_unslash( $_POST['cg'] ) : array();

		Settings::save( Settings::sanitizeTab( $tab, $input ) );
		self::back( $tab, 'saved' );
	}

	/**
	 * Sube la revisión de la política: todos los visitantes vuelven a ver el banner.
	 */
	private static function bumpRevision(): void {
		$settings             = Settings::all();
		$settings['revision'] = Settings::int( 'revision' ) + 1;
		Settings::save( $settings );
		self::back( 'banner', 'revision' );
	}

	private static function scan(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 180 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		$result = Scanner::run();
		set_transient( 'consent_guard_scan_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		self::back( 'cookies', 'scanned' );
	}

	private static function addCookie(): void {
		$name  = CookieRegistry::sanitizeName( self::post( 'name' ) );
		$known = '' !== $name ? CookieDatabase::find( $name ) : null;

		$ok = '' !== $name && CookieRegistry::upsert(
			array(
				'name'        => $name,
				'category'    => self::post( 'category' ) ?: ( $known['category']->value ?? 'unclassified' ),
				'domain'      => self::post( 'domain' ) ?: ( $known['domain'] ?? '' ),
				'description' => self::post( 'description' ) ?: ( $known['description'] ?? '' ),
				'duration'    => self::post( 'duration' ) ?: ( $known['retention'] ?? '' ),
				'source'      => $known['platform'] ?? __( 'Manual', 'consent-guard' ),
			)
		);

		self::back( 'cookies', $ok ? 'cookie_added' : 'cookie_invalid' );
	}

	private static function updateCookies(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida cada valor contra el enum Category.
		$categories = isset( $_POST['category'] ) && is_array( $_POST['category'] ) ? wp_unslash( $_POST['category'] ) : array();
		CookieRegistry::updateCategories( $categories );
		self::back( 'cookies', 'saved' );
	}

	private static function deleteCookie(): void {
		CookieRegistry::delete( CookieRegistry::sanitizeName( self::post( 'name' ) ) );
		self::back( 'cookies', 'cookie_deleted' );
	}

	private static function export(): never {
		ConsentLog::streamCsv();
	}

	/**
	 * Campo de texto del POST, saneado.
	 */
	private static function post( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se comprobó en guard().
		$value = $_POST[ $key ] ?? '';
		return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}

	private static function back( string $tab, string $notice ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => AdminPage::SLUG,
					'tab'       => $tab,
					'cg_notice' => $notice,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
