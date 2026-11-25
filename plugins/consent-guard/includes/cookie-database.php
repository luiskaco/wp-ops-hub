<?php
/**
 * Consultas a la Open Cookie Database (Apache-2.0, data/LICENSE-open-cookie-database.txt).
 * Solo se usa en el admin (escáner y alta manual), nunca en las páginas públicas.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class CookieDatabase {

	/** @var list<array{name: string, platform: string, category: Category, domain: string, description: string, retention: string, wildcard: bool}>|null */
	private static ?array $rows = null;

	/**
	 * Todas las filas del CSV, cargadas una vez por petición.
	 *
	 * @return list<array{name: string, platform: string, category: Category, domain: string, description: string, retention: string, wildcard: bool}>
	 */
	public static function rows(): array {
		if ( null !== self::$rows ) {
			return self::$rows;
		}

		self::$rows = array();
		$file       = dirname( CONSENT_GUARD_FILE ) . '/data/open-cookie-database.csv';
		$handle     = is_readable( $file ) ? fopen( $file, 'r' ) : false;
		if ( false === $handle ) {
			return self::$rows;
		}

		fgetcsv( $handle, 0, ',', '"', '' ); // Cabecera.
		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			$name = trim( (string) ( $row[3] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			self::$rows[] = array(
				'name'        => $name,
				'platform'    => trim( (string) ( $row[1] ?? '' ) ),
				'category'    => Category::fromOpenCookieDatabase( (string) ( $row[2] ?? '' ) ),
				'domain'      => trim( (string) ( $row[4] ?? '' ) ),
				'description' => trim( (string) ( $row[5] ?? '' ) ),
				'retention'   => trim( (string) ( $row[6] ?? '' ) ),
				'wildcard'    => '1' === trim( (string) ( $row[9] ?? '0' ) ),
			);
		}
		fclose( $handle );

		return self::$rows;
	}

	/**
	 * Busca una cookie por nombre: primero coincidencia exacta y después por prefijo (comodín).
	 *
	 * @return array{name: string, platform: string, category: Category, domain: string, description: string, retention: string, wildcard: bool}|null
	 */
	public static function find( string $cookie_name ): ?array {
		$rows = self::rows();
		return array_find( $rows, static fn ( array $r ): bool => $r['name'] === $cookie_name )
			?? array_find( $rows, static fn ( array $r ): bool => $r['wildcard'] && str_starts_with( $cookie_name, $r['name'] ) );
	}

	/**
	 * Cookies conocidas de una plataforma (p. ej. «Google Analytics»).
	 *
	 * @return list<array{name: string, platform: string, category: Category, domain: string, description: string, retention: string, wildcard: bool}>
	 */
	public static function byPlatform( string $platform, int $limit = 15 ): array {
		$matches = array_values( array_filter( self::rows(), static fn ( array $r ): bool => 0 === strcasecmp( $r['platform'], $platform ) ) );
		return array_slice( $matches, 0, $limit );
	}
}
