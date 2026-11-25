<?php
/**
 * Endpoint público para registrar consentimientos (ADR-009).
 *
 * POST /wp-json/consent-guard/v1/consent
 * Sin nonce (el HTML cacheado lo dejaría caducado y no protege nada en peticiones anónimas),
 * pero con esquema estricto, comprobación de Origin y límite de peticiones por IP anonimizada.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class RestController {

	public const string NAMESPACE = 'consent-guard/v1';

	/** Peticiones permitidas por IP anonimizada en la ventana. */
	public const int RATE_LIMIT = 20;

	/** Ventana del límite, en segundos. */
	public const int RATE_WINDOW = 600;

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/consent',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'store' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'id'         => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-f0-9]{32}$',
					),
					'action'     => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => ConsentLog::ACTIONS,
					),
					'categories' => array(
						'type'        => 'array',
						'required'    => true,
						'maxItems'    => count( Category::optional() ),
						'uniqueItems' => true,
						'items'       => array(
							'type' => 'string',
							'enum' => array_map( static fn ( Category $c ): string => $c->value, Category::optional() ),
						),
					),
					'revision'   => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	/**
	 * Endpoint público por diseño, pero solo si el registro está activo, la petición viene
	 * del propio sitio y la IP no superó el límite.
	 */
	public static function permission( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! Settings::bool( 'enabled' ) || ! Settings::bool( 'log_consents' ) ) {
			return new \WP_Error( 'consent_guard_disabled', __( 'El registro de consentimientos está desactivado.', 'consent-guard' ), array( 'status' => 403 ) );
		}

		if ( ! self::isSameOrigin( $request->get_header( 'origin' ) ) ) {
			return new \WP_Error( 'consent_guard_origin', __( 'Origen no permitido.', 'consent-guard' ), array( 'status' => 403 ) );
		}

		if ( ! self::hitRateLimit( Ip::anonymize( Ip::client() ) ) ) {
			return new \WP_Error( 'consent_guard_rate_limited', __( 'Demasiadas peticiones. Inténtalo más tarde.', 'consent-guard' ), array( 'status' => 429 ) );
		}

		return true;
	}

	public static function store( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$categories = array_values(
			array_filter(
				array_map( Category::fromInput( ... ), (array) $request->get_param( 'categories' ) ),
				static fn ( ?Category $c ): bool => null !== $c && $c->isOptional()
			)
		);

		$action = (string) $request->get_param( 'action' );

		// Coherencia entre la acción y las categorías: «aceptar todo» sin categorías no tiene sentido.
		if ( 'reject_all' === $action ) {
			$categories = array();
		} elseif ( 'accept_all' === $action ) {
			$categories = Category::optional();
		}

		// Una revisión futura no existe: se guarda la actual.
		$revision = min( (int) $request->get_param( 'revision' ), Settings::int( 'revision' ) );

		$saved = ConsentLog::insert(
			(string) $request->get_param( 'id' ),
			$revision,
			$action,
			$categories,
			Ip::anonymize( Ip::client() )
		);

		if ( ! $saved ) {
			return new \WP_Error( 'consent_guard_db', __( 'No se pudo guardar el consentimiento.', 'consent-guard' ), array( 'status' => 500 ) );
		}

		$response = new \WP_REST_Response( array( 'saved' => true ), 201 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Sin cabecera Origin (navegadores antiguos o sendBeacon) se acepta; con ella, debe ser el propio sitio.
	 */
	public static function isSameOrigin( ?string $origin ): bool {
		if ( null === $origin || '' === $origin ) {
			return true;
		}

		$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $origin_host ) && is_string( $home_host ) && strtolower( $origin_host ) === strtolower( $home_host );
	}

	/**
	 * Cuenta una petición para la IP. Devuelve false si ya superó el límite.
	 */
	public static function hitRateLimit( string $ip_anon ): bool {
		$key   = 'cg_rl_' . substr( hash_hmac( 'sha256', $ip_anon, wp_salt( 'nonce' ) ), 0, 32 );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}
}
