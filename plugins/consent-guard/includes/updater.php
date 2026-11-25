<?php
/**
 * Actualizaciones automáticas desde los releases de GitHub con Plugin Update Checker (PUC).
 *
 * El repositorio es un monorepo: cada plugin publica tags `<slug>-v<versión>` y un ZIP propio,
 * así que se filtran los releases por prefijo y se reescriben las lecturas de la raíz del repo
 * a la carpeta del plugin.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Updater {

	private const string REPOSITORY = 'https://github.com/luiskaco/wp-ops-hub/';
	private const string API_PREFIX = 'https://api.github.com/repos/luiskaco/wp-ops-hub/contents/';

	/** Releases a revisar: el repo publica los de todos los plugins juntos (máximo de PUC: 100). */
	private const int MAX_RELEASES = 100;

	public static function register(): void {
		$loader = dirname( CONSENT_GUARD_FILE ) . '/vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $loader ) ) {
			return;
		}

		require_once $loader;

		$factory = '\YahnisElsts\PluginUpdateChecker\v5\PucFactory';
		if ( ! class_exists( $factory ) ) {
			return;
		}

		$checker = $factory::buildUpdateChecker( self::REPOSITORY, CONSENT_GUARD_FILE, Plugin::SLUG );
		$api     = $checker->getVcsApi();

		// Solo los releases de este plugin y solo su ZIP.
		if ( method_exists( $api, 'setReleaseVersionFilter' ) ) {
			$api->setReleaseVersionFilter( '/^' . Plugin::SLUG . '-v/i', $api::RELEASE_FILTER_SKIP_PRERELEASE, self::MAX_RELEASES );
		}
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/^' . Plugin::SLUG . '\.zip$/i', $api::REQUIRE_RELEASE_ASSETS );
		}

		$checker->addFilter( 'request_info_result', self::normalizeVersion( ... ) );
		$checker->addFilter( 'request_update_result', self::normalizeVersion( ... ) );

		add_filter( 'pre_http_request', self::rewriteContentsRequest( ... ), 10, 3 );
	}

	/**
	 * `consent-guard-v1.2.0` → `1.2.0`, para que WordPress compare versiones semver.
	 */
	public static function normalizeVersion( mixed $result ): mixed {
		if ( is_object( $result ) && isset( $result->version ) && is_string( $result->version )
			&& preg_match( '/^' . Plugin::SLUG . '-v?(\d+(?:\.\d+)*)$/i', $result->version, $match ) ) {
			$result->version = $match[1];
		}
		return $result;
	}

	/**
	 * PUC lee la cabecera del plugin en la raíz del repo; en el monorepo vive en `plugins/<slug>/`.
	 * Solo reescribe la URL exacta de este repo y de este archivo.
	 *
	 * @param false|array<string, mixed>|\WP_Error $preempt
	 * @param array<string, mixed>                 $args
	 * @return false|array<string, mixed>|\WP_Error
	 */
	public static function rewriteContentsRequest( mixed $preempt, array $args, mixed $url ): mixed {
		static $inFlight = false;

		$source = self::API_PREFIX . Plugin::SLUG . '.php';
		if ( $inFlight || false !== $preempt || ! is_string( $url ) || ! str_starts_with( $url, $source ) ) {
			return $preempt;
		}

		$inFlight = true;
		$response = wp_remote_get( self::API_PREFIX . 'plugins/' . Plugin::SLUG . '/' . Plugin::SLUG . '.php' . substr( $url, strlen( $source ) ), $args );
		$inFlight = false;

		return $response;
	}
}
