<?php
/**
 * IP del visitante y su versión anonimizada.
 *
 * Solo se confía en REMOTE_ADDR. Las cabeceras como X-Forwarded-For las puede inventar
 * cualquiera; quien esté detrás de un proxy de confianza puede usar el filtro
 * «consent_guard_client_ip» para leer la cabecera correcta.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Ip {

	public static function client(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Permite indicar la IP real cuando el sitio está detrás de un proxy de confianza.
		 *
		 * @param string $remote IP de REMOTE_ADDR.
		 */
		$ip = apply_filters( 'consent_guard_client_ip', $remote );

		return is_string( $ip ) && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Anonimiza la IP: IPv4 a /24 (último octeto a 0) e IPv6 a /48.
	 */
	public static function anonymize( string $ip ): string {
		$packed = '' !== $ip ? inet_pton( $ip ) : false;
		if ( false === $packed ) {
			return '';
		}

		$keep   = 4 === strlen( $packed ) ? 3 : 6;
		$masked = substr( $packed, 0, $keep ) . str_repeat( "\0", strlen( $packed ) - $keep );

		return (string) inet_ntop( $masked );
	}
}
