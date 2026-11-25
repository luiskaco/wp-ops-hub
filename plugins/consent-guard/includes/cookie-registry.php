<?php
/**
 * Lista de cookies del sitio que se muestra al visitante (escaneadas o añadidas a mano).
 * Se guarda en wp_options sin autoload: son unos cientos de entradas como mucho.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class CookieRegistry {

	public const string OPTION      = 'consent_guard_cookies';
	public const int MAX_COOKIES    = 500;
	public const string OWN_COOKIE  = 'cg_consent';

	/**
	 * Cookies guardadas, indexadas por nombre.
	 *
	 * @return array<string, array{name: string, category: string, domain: string, description: string, duration: string, source: string}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Cookies agrupadas por categoría, incluida la propia del banner.
	 *
	 * @return array<string, list<array{name: string, category: string, domain: string, description: string, duration: string, source: string}>>
	 */
	public static function grouped(): array {
		$groups = array_fill_keys( array_map( static fn ( Category $c ): string => $c->value, Category::cases() ), array() );

		$groups[ Category::Necessary->value ][] = self::ownCookie();
		foreach ( self::all() as $cookie ) {
			$key              = Category::fromInput( $cookie['category'] ?? '' )?->value ?? Category::Unclassified->value;
			$groups[ $key ][] = $cookie;
		}
		return $groups;
	}

	/**
	 * Nombres de cookies por categoría opcional: el JS los borra cuando el visitante retira el permiso.
	 *
	 * @return array<string, list<string>>
	 */
	public static function namesByOptionalCategory(): array {
		$names = array();
		foreach ( Category::optional() as $category ) {
			$names[ $category->value ] = array();
		}
		foreach ( self::all() as $cookie ) {
			if ( isset( $names[ $cookie['category'] ] ) ) {
				$names[ $cookie['category'] ][] = $cookie['name'];
			}
		}
		return $names;
	}

	/**
	 * Añade o actualiza una cookie validando cada campo.
	 *
	 * @param array<string, mixed> $data Datos crudos.
	 */
	public static function upsert( array $data, bool $overwrite = true ): bool {
		$name = self::sanitizeName( is_string( $data['name'] ?? null ) ? $data['name'] : '' );
		if ( '' === $name || self::OWN_COOKIE === $name ) {
			return false;
		}

		$cookies = self::all();
		if ( isset( $cookies[ $name ] ) && ! $overwrite ) {
			return false;
		}
		if ( ! isset( $cookies[ $name ] ) && count( $cookies ) >= self::MAX_COOKIES ) {
			return false;
		}

		$text = static fn ( string $key, int $max ): string =>
			mb_substr( sanitize_text_field( is_scalar( $data[ $key ] ?? null ) ? (string) $data[ $key ] : '' ), 0, $max );

		$cookies[ $name ] = array(
			'name'        => $name,
			'category'    => ( Category::fromInput( $data['category'] ?? null ) ?? Category::Unclassified )->value,
			'domain'      => $text( 'domain', 200 ),
			'description' => $text( 'description', 500 ),
			'duration'    => $text( 'duration', 60 ),
			'source'      => $text( 'source', 80 ),
		);

		ksort( $cookies, SORT_NATURAL | SORT_FLAG_CASE );
		update_option( self::OPTION, $cookies, false );
		return true;
	}

	/**
	 * Cambia la categoría de varias cookies de una vez.
	 *
	 * @param array<mixed, mixed> $categories nombre => categoría.
	 */
	public static function updateCategories( array $categories ): void {
		$cookies = self::all();
		foreach ( $categories as $name => $value ) {
			$category = Category::fromInput( $value );
			if ( is_string( $name ) && isset( $cookies[ $name ] ) && null !== $category ) {
				$cookies[ $name ]['category'] = $category->value;
			}
		}
		update_option( self::OPTION, $cookies, false );
	}

	public static function delete( string $name ): void {
		$cookies = self::all();
		unset( $cookies[ $name ] );
		update_option( self::OPTION, $cookies, false );
	}

	/**
	 * Nombre de cookie válido según RFC 6265 (sin espacios, separadores ni caracteres de control).
	 */
	public static function sanitizeName( string $name ): string {
		$name = trim( $name );
		return preg_match( '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]{1,100}$/', $name ) ? $name : '';
	}

	/**
	 * La cookie del propio banner, siempre listada como necesaria.
	 *
	 * @return array{name: string, category: string, domain: string, description: string, duration: string, source: string}
	 */
	public static function ownCookie(): array {
		return array(
			'name'        => self::OWN_COOKIE,
			'category'    => Category::Necessary->value,
			'domain'      => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'description' => __( 'Guarda tu elección de cookies para no volver a preguntarte.', 'consent-guard' ),
			/* translators: %d: número de días */
			'duration'    => sprintf( _n( '%d día', '%d días', Settings::int( 'consent_days' ), 'consent-guard' ), Settings::int( 'consent_days' ) ),
			'source'      => 'Consent Guard',
		);
	}
}
