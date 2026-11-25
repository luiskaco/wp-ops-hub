<?php
/**
 * Autoloader propio: ConsentGuard\Admin\AdminPage -> includes/admin/admin-page.php.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'ConsentGuard\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );

		// Solo letras y separadores de namespace: nada de rutas ni caracteres raros.
		if ( ! preg_match( '/^[A-Za-z]+(\\\\[A-Za-z]+)*$/', $relative ) ) {
			return;
		}

		$parts = array_map(
			static fn ( string $part ): string => strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '-$0', $part ) ),
			explode( '\\', $relative )
		);

		$file = __DIR__ . '/' . implode( '/', $parts ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
