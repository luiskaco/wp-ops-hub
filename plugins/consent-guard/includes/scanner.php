<?php
/**
 * Escáner pasivo de cookies.
 *
 * A diferencia de CookieAdmin, nunca envía formularios ni sigue enlaces: solo pide con GET la
 * portada y las últimas páginas publicadas del propio sitio. Detecta las cookies que el servidor
 * envía (Set-Cookie) y los servicios de terceros por sus scripts, y sugiere sus cookies conocidas.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Scanner {

	public const int MAX_URLS = 10;

	/**
	 * Ejecuta el escaneo y guarda lo encontrado. No sobrescribe cookies que el admin ya editó.
	 *
	 * @return array{urls: int, errors: int, cookies: int, services: list<string>}
	 */
	public static function run(): array {
		$found_cookies  = array();
		$found_services = array();
		$errors         = 0;
		$urls           = self::urls();
		$services       = Services::all();

		foreach ( $urls as $url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 10,
					'redirection'         => 2,
					'limit_response_size' => 2 * MB_IN_BYTES,
					'user-agent'          => 'ConsentGuardScanner/' . Plugin::VERSION . '; ' . home_url( '/' ),
					/** Desactivar la verificación SSL solo en local con certificados propios. */
					'sslverify'           => (bool) apply_filters( 'consent_guard_scanner_sslverify', true ),
				)
			);

			if ( is_wp_error( $response ) ) {
				++$errors;
				continue;
			}

			foreach ( wp_remote_retrieve_cookies( $response ) as $cookie ) {
				if ( $cookie instanceof \WP_Http_Cookie && '' !== CookieRegistry::sanitizeName( (string) $cookie->name ) ) {
					$found_cookies[ $cookie->name ] = (string) ( $cookie->domain ?: wp_parse_url( $url, PHP_URL_HOST ) );
				}
			}

			$body = self::thirdPartyScripts( wp_remote_retrieve_body( $response ) );
			foreach ( $services as $id => $service ) {
				if ( isset( $found_services[ $id ] ) ) {
					continue;
				}
				$patterns = (array) ( $service['patterns'] ?? array() );
				if ( array_any( $patterns, static fn ( mixed $p ): bool => is_string( $p ) && '' !== $p && str_contains( $body, $p ) ) ) {
					$found_services[ $id ] = $service;
				}
			}
		}

		$added = 0;

		foreach ( $found_cookies as $name => $domain ) {
			$known  = CookieDatabase::find( $name );
			$added += (int) CookieRegistry::upsert(
				array(
					'name'        => $name,
					'category'    => $known['category'] ?? Category::Unclassified,
					'domain'      => $domain,
					'description' => $known['description'] ?? '',
					'duration'    => $known['retention'] ?? '',
					'source'      => $known['platform'] ?? __( 'Escáner', 'consent-guard' ),
				),
				false
			);
		}

		foreach ( $found_services as $service ) {
			foreach ( (array) ( $service['platforms'] ?? array() ) as $platform ) {
				foreach ( CookieDatabase::byPlatform( (string) $platform ) as $known ) {
					// Las cookies con comodín (p. ej. «_ga_») se guardan con su prefijo tal cual.
					$added += (int) CookieRegistry::upsert(
						array(
							'name'        => $known['name'],
							'category'    => $service['category'] instanceof Category ? $service['category'] : $known['category'],
							'domain'      => $known['domain'],
							'description' => $known['description'],
							'duration'    => $known['retention'],
							'source'      => (string) $service['label'],
						),
						false
					);
				}
			}
		}

		$result = array(
			'urls'     => count( $urls ),
			'errors'   => $errors,
			'cookies'  => $added,
			'services' => array_values( array_map( static fn ( array $s ): string => (string) $s['label'], $found_services ) ),
		);

		update_option( 'consent_guard_last_scan', array( 'time' => time() ) + $result, false );
		return $result;
	}

	/**
	 * Junta el src y el contenido de los scripts de la página, sin los del propio plugin
	 * (el de Consent Mode contiene «gtag(» y daría un falso positivo de Google Analytics).
	 */
	public static function thirdPartyScripts( string $html ): string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$parts     = array();

		while ( $processor->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
			if ( str_starts_with( (string) $processor->get_attribute( 'id' ), 'consent-guard' ) ) {
				continue;
			}
			$parts[] = (string) $processor->get_attribute( 'src' );
			$parts[] = (string) $processor->get_modifiable_text();
		}

		return implode( "\n", $parts );
	}

	/**
	 * Portada + últimas entradas y páginas publicadas. Solo URLs del propio sitio.
	 *
	 * @return list<string>
	 */
	public static function urls(): array {
		$urls = array( home_url( '/' ) );

		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post' ),
				'post_status'      => 'publish',
				'has_password'     => false,
				'numberposts'      => self::MAX_URLS - 1,
				'orderby'          => 'modified',
				'suppress_filters' => true,
				'fields'           => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$link = get_permalink( (int) $post_id );
			if ( is_string( $link ) ) {
				$urls[] = $link;
			}
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$urls      = array_filter( array_unique( $urls ), static fn ( string $u ): bool => wp_parse_url( $u, PHP_URL_HOST ) === $home_host );

		return array_slice( array_values( $urls ), 0, self::MAX_URLS );
	}
}
