<?php
/**
 * Categorías de cookies y scripts.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

enum Category: string {

	case Necessary    = 'necessary';
	case Functional   = 'functional';
	case Analytics    = 'analytics';
	case Marketing    = 'marketing';
	case Unclassified = 'unclassified';

	/**
	 * Categorías que el visitante puede aceptar o rechazar (las necesarias van siempre).
	 *
	 * @return list<self>
	 */
	public static function optional(): array {
		return array( self::Functional, self::Analytics, self::Marketing );
	}

	/**
	 * Categorías que se pueden asignar a una cookie o a un script.
	 *
	 * @return list<self>
	 */
	public static function assignable(): array {
		return self::cases();
	}

	/**
	 * Convierte texto libre en categoría, o null si no es válida.
	 */
	public static function fromInput( mixed $value ): ?self {
		return is_string( $value ) ? self::tryFrom( strtolower( trim( $value ) ) ) : null;
	}

	/**
	 * Traduce la categoría de la Open Cookie Database a una propia.
	 */
	public static function fromOpenCookieDatabase( string $value ): self {
		return match ( strtolower( trim( $value ) ) ) {
			'necessary', 'security'          => self::Necessary,
			'functional', 'personalization'  => self::Functional,
			'analytics'                      => self::Analytics,
			'marketing'                      => self::Marketing,
			default                          => self::Unclassified,
		};
	}

	public function label(): string {
		return match ( $this ) {
			self::Necessary    => __( 'Necesarias', 'consent-guard' ),
			self::Functional   => __( 'Funcionales', 'consent-guard' ),
			self::Analytics    => __( 'Analíticas', 'consent-guard' ),
			self::Marketing    => __( 'Publicidad', 'consent-guard' ),
			self::Unclassified => __( 'Sin clasificar', 'consent-guard' ),
		};
	}

	public function description(): string {
		return match ( $this ) {
			self::Necessary    => __( 'Permiten funciones básicas como el inicio de sesión, el carrito o recordar tu elección de cookies. No se pueden desactivar.', 'consent-guard' ),
			self::Functional   => __( 'Recuerdan preferencias y habilitan funciones extra, como chats, mapas o vídeos incrustados.', 'consent-guard' ),
			self::Analytics    => __( 'Nos ayudan a entender cómo se usa el sitio (visitas, páginas vistas, origen del tráfico) para mejorarlo.', 'consent-guard' ),
			self::Marketing    => __( 'Las usan las redes publicitarias para mostrarte anuncios relevantes y medir campañas.', 'consent-guard' ),
			self::Unclassified => __( 'Cookies que todavía estamos clasificando.', 'consent-guard' ),
		};
	}

	public function isOptional(): bool {
		return in_array( $this, self::optional(), true );
	}
}
