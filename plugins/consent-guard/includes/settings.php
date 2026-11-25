<?php
/**
 * Ajustes del plugin: valores por defecto, lectura y saneamiento estricto por pestaña.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const string OPTION = 'consent_guard_settings';

	public const array LAYOUTS   = array( 'box', 'bar', 'popup' );
	public const array POSITIONS = array( 'bottom-left', 'bottom-right', 'bottom-center' );
	public const array GCM_MODES = array( 'basic', 'advanced' );

	/** Máximo de días que dura el consentimiento (13 meses, recomendación de la CNIL). */
	public const int MAX_CONSENT_DAYS = 395;

	/** Máximo de reglas propias de bloqueo. */
	public const int MAX_RULES = 100;

	/** @var array<string, mixed>|null Caché por petición. */
	private static ?array $cache = null;

	/**
	 * Valores por defecto.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                => true,
			'revision'               => 1,
			'consent_days'           => 180,
			'layout'                 => 'box',
			'position'               => 'bottom-left',
			'title'                  => __( 'Usamos cookies', 'consent-guard' ),
			'message'                => __( 'Usamos cookies propias y de terceros para que el sitio funcione, analizar el tráfico y, si lo aceptas, mostrarte publicidad. Puedes aceptarlas, rechazarlas o elegir cuáles usar.', 'consent-guard' ),
			'accept_label'           => __( 'Aceptar todo', 'consent-guard' ),
			'reject_label'           => __( 'Rechazar todo', 'consent-guard' ),
			'customize_label'        => __( 'Personalizar', 'consent-guard' ),
			'save_label'             => __( 'Guardar mi elección', 'consent-guard' ),
			'prefs_title'            => __( 'Preferencias de cookies', 'consent-guard' ),
			'prefs_intro'            => __( 'Elige qué categorías de cookies permites. Puedes cambiar tu elección cuando quieras desde el botón de cookies.', 'consent-guard' ),
			'reopen_label'           => __( 'Cookies', 'consent-guard' ),
			'privacy_url'            => '',
			'privacy_label'          => __( 'Política de cookies', 'consent-guard' ),
			'color_bg'               => '#ffffff',
			'color_text'             => '#1f2430',
			'color_accent'           => '#7e22ce',
			'color_accent_text'      => '#ffffff',
			'show_reopen'            => true,
			'block_scripts'          => true,
			'google_consent_mode'    => true,
			'gcm_mode'               => 'basic',
			'respect_gpc'            => true,
			'custom_rules'           => array(),
			'log_consents'           => true,
			'log_retention_days'     => 365,
			'keep_data_on_uninstall' => false,
		);
	}

	/**
	 * Ajustes actuales combinados con los valores por defecto.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	public static function bool( string $key ): bool {
		return true === self::get( $key );
	}

	public static function int( string $key ): int {
		$value = self::get( $key );
		return is_int( $value ) ? $value : (int) self::defaults()[ $key ];
	}

	public static function string( string $key ): string {
		$value = self::get( $key );
		return is_string( $value ) ? $value : (string) ( self::defaults()[ $key ] ?? '' );
	}

	/**
	 * Guarda los ajustes (ya saneados) y limpia la caché.
	 *
	 * @param array<string, mixed> $settings Ajustes completos.
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION, $settings, true );
		self::$cache = null;
	}

	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Sanea los campos de una pestaña y los combina con los ajustes actuales.
	 * Los campos que no pertenecen a la pestaña no se tocan.
	 *
	 * @param string               $tab   Pestaña enviada.
	 * @param array<string, mixed> $input Datos del formulario (ya sin barras invertidas).
	 * @return array<string, mixed>
	 */
	public static function sanitizeTab( string $tab, array $input ): array {
		$current  = self::all();
		$defaults = self::defaults();

		$text = static fn ( string $key, int $max = 120 ): string =>
			mb_substr( sanitize_text_field( self::scalar( $input[ $key ] ?? '' ) ), 0, $max ) ?: (string) $defaults[ $key ];

		$color = static fn ( string $key ): string =>
			sanitize_hex_color( self::scalar( $input[ $key ] ?? '' ) ) ?: (string) $defaults[ $key ];

		$choice = static fn ( string $key, array $allowed ): string =>
			in_array( $input[ $key ] ?? null, $allowed, true ) ? $input[ $key ] : (string) $defaults[ $key ];

		$flag = static fn ( string $key ): bool => ! empty( $input[ $key ] );

		$range = static fn ( string $key, int $min, int $max ): int =>
			max( $min, min( $max, absint( self::scalar( $input[ $key ] ?? 0 ) ) ?: (int) $defaults[ $key ] ) );

		$changes = match ( $tab ) {
			'banner' => array(
				'enabled'           => $flag( 'enabled' ),
				'layout'            => $choice( 'layout', self::LAYOUTS ),
				'position'          => $choice( 'position', self::POSITIONS ),
				'title'             => $text( 'title' ),
				'message'           => self::sanitizeMessage( self::scalar( $input['message'] ?? '' ) ) ?: (string) $defaults['message'],
				'accept_label'      => $text( 'accept_label', 40 ),
				'reject_label'      => $text( 'reject_label', 40 ),
				'customize_label'   => $text( 'customize_label', 40 ),
				'save_label'        => $text( 'save_label', 40 ),
				'prefs_title'       => $text( 'prefs_title' ),
				'prefs_intro'       => $text( 'prefs_intro', 400 ),
				'reopen_label'      => $text( 'reopen_label', 40 ),
				'privacy_url'       => self::sanitizeUrl( self::scalar( $input['privacy_url'] ?? '' ) ),
				'privacy_label'     => $text( 'privacy_label', 60 ),
				'color_bg'          => $color( 'color_bg' ),
				'color_text'        => $color( 'color_text' ),
				'color_accent'      => $color( 'color_accent' ),
				'color_accent_text' => $color( 'color_accent_text' ),
				'show_reopen'       => $flag( 'show_reopen' ),
				'consent_days'      => $range( 'consent_days', 1, self::MAX_CONSENT_DAYS ),
			),
			'scripts' => array(
				'block_scripts'       => $flag( 'block_scripts' ),
				'google_consent_mode' => $flag( 'google_consent_mode' ),
				'gcm_mode'            => $choice( 'gcm_mode', self::GCM_MODES ),
				'respect_gpc'         => $flag( 'respect_gpc' ),
				'custom_rules'        => self::parseRules( self::scalar( $input['custom_rules'] ?? '' ) ),
			),
			'log' => array(
				'log_consents'           => $flag( 'log_consents' ),
				'log_retention_days'     => $range( 'log_retention_days', 30, 1825 ),
				'keep_data_on_uninstall' => $flag( 'keep_data_on_uninstall' ),
			),
			default => array(),
		};

		return array_merge( $current, $changes );
	}

	/**
	 * Mensaje del banner: solo enlaces y énfasis. No se toca el filtro global de protocolos.
	 */
	public static function sanitizeMessage( string $value ): string {
		$allowed = array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
		return mb_substr( trim( wp_kses( $value, $allowed, array( 'http', 'https', 'mailto' ) ) ), 0, 1500 );
	}

	/**
	 * Solo URLs http(s).
	 */
	public static function sanitizeUrl( string $value ): string {
		$url = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		return mb_substr( $url, 0, 500 );
	}

	/**
	 * Convierte el texto «patrón | categoría» (una regla por línea) en reglas válidas.
	 *
	 * @return list<array{pattern: string, category: string}>
	 */
	public static function parseRules( string $raw ): array {
		$rules = array();
		foreach ( preg_split( '/\R/', $raw ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$pattern  = mb_substr( sanitize_text_field( $parts[0] ), 0, 200 );
			$category = Category::fromInput( $parts[1] );

			// Mínimo 4 caracteres para no bloquear medio sitio con un patrón como «a».
			if ( mb_strlen( $pattern ) < 4 || null === $category || ! $category->isOptional() ) {
				continue;
			}

			$rules[ $pattern ] = array(
				'pattern'  => $pattern,
				'category' => $category->value,
			);

			if ( count( $rules ) >= self::MAX_RULES ) {
				break;
			}
		}
		return array_values( $rules );
	}

	/**
	 * Reglas propias en formato texto para el formulario.
	 */
	public static function rulesAsText(): string {
		$rules = self::get( 'custom_rules' );
		if ( ! is_array( $rules ) ) {
			return '';
		}
		return implode(
			"\n",
			array_map( static fn ( array $rule ): string => $rule['pattern'] . ' | ' . $rule['category'], $rules )
		);
	}

	/**
	 * Fuerza un valor de formulario a texto (los arrays inesperados se descartan).
	 */
	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
