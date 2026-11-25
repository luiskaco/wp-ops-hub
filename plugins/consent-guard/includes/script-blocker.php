<?php
/**
 * Bloqueo de scripts en el HTML de salida (ADR-009).
 *
 * Siempre convierte los scripts de rastreo reconocidos en type="text/plain", sin mirar la cookie
 * del visitante: así el HTML es igual para todos y se puede cachear. El JS los activa en el navegador.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class ScriptBlocker {

	/** Tipos de script que el navegador ejecuta como JavaScript. */
	private const array JS_TYPES = array( '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' );

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'startBuffer' ), 1 );
	}

	public static function startBuffer(): void {
		if ( ! self::shouldRun() ) {
			return;
		}
		ob_start( array( self::class, 'filterOutput' ) );
	}

	/**
	 * Callback del buffer de salida.
	 */
	public static function filterOutput( mixed $html ): mixed {
		if ( ! is_string( $html ) || ! self::isHtmlResponse( $html ) ) {
			return $html;
		}

		try {
			return self::process( $html, Services::rules() );
		} catch ( \Throwable $e ) {
			// Nunca romper la página por un fallo del bloqueador.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Permisio: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $html;
		}
	}

	/**
	 * Marca como bloqueados los scripts que coinciden con alguna regla.
	 *
	 * @param list<array{pattern: string, category: Category, service: string}> $rules Reglas de bloqueo.
	 */
	public static function process( string $html, array $rules ): string {
		if ( false === stripos( $html, '<script' ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		$changed   = false;

		while ( $processor->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
			$id = (string) $processor->get_attribute( 'id' );
			if ( str_starts_with( $id, 'consent-guard' ) ) {
				continue;
			}

			$type = $processor->get_attribute( 'type' );
			$type = is_string( $type ) ? strtolower( trim( $type ) ) : '';

			// Ya bloqueado a mano por el admin (type="text/plain" + data-cg-category): lo activa el JS.
			if ( ! in_array( $type, self::JS_TYPES, true ) ) {
				continue;
			}

			$category = self::categoryFor( $processor, $rules );
			if ( null === $category || ! $category->isOptional() ) {
				continue;
			}

			$processor->set_attribute( 'type', 'text/plain' );
			$processor->set_attribute( 'data-cg-category', $category->value );
			if ( '' !== $type ) {
				$processor->set_attribute( 'data-cg-type', $type );
			}
			$changed = true;
		}

		return $changed ? $processor->get_updated_html() : $html;
	}

	/**
	 * Categoría del script: la que marcó el admin con data-cg-category o la de la primera regla que coincide.
	 *
	 * @param list<array{pattern: string, category: Category, service: string}> $rules Reglas de bloqueo.
	 */
	private static function categoryFor( \WP_HTML_Tag_Processor $processor, array $rules ): ?Category {
		$explicit = Category::fromInput( $processor->get_attribute( 'data-cg-category' ) );
		if ( null !== $explicit ) {
			return $explicit;
		}

		$src = $processor->get_attribute( 'src' );
		if ( is_string( $src ) && '' !== $src ) {
			return Services::match( $src, $rules )['category'] ?? null;
		}

		return Services::match( (string) $processor->get_modifiable_text(), $rules )['category'] ?? null;
	}

	/**
	 * Solo en páginas públicas normales: nada de admin, AJAX, REST, feeds, cron ni editores visuales.
	 */
	private static function shouldRun(): bool {
		if ( ! Settings::bool( 'enabled' ) || ! Settings::bool( 'block_scripts' ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_serving_rest_request() || is_feed() || is_robots() || is_trackback() || is_embed() ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) || is_customize_preview() || Plugin::isBuilderPreview() ) {
			return false;
		}

		/**
		 * Permite desactivar el bloqueo en una petición concreta.
		 *
		 * @param bool $run Si se bloquean los scripts.
		 */
		return (bool) apply_filters( 'consent_guard_block_scripts', true );
	}

	/**
	 * Comprueba que la respuesta sea HTML (no JSON, XML ni archivos).
	 */
	private static function isHtmlResponse( string $buffer ): bool {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) ) {
				return false;
			}
		}
		return false !== stripos( $buffer, '<html' ) || false !== stripos( $buffer, '<!doctype html' );
	}
}
