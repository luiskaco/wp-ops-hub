<?php
/**
 * Servicios de terceros conocidos: qué patrones delatan sus scripts y en qué categoría van.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Services {

	/**
	 * Catálogo interno. «gcm» marca los servicios de Google que entienden Consent Mode:
	 * en modo avanzado no se bloquean y reciben la señal de consentimiento.
	 * «platforms» son los nombres de la Open Cookie Database para sugerir sus cookies.
	 *
	 * @return array<string, array{label: string, category: Category, patterns: list<string>, platforms: list<string>, gcm: bool}>
	 */
	public static function all(): array {
		$services = array(
			'google-analytics' => array(
				'label'     => 'Google Analytics',
				'category'  => Category::Analytics,
				'patterns'  => array( 'google-analytics.com/analytics.js', 'googletagmanager.com/gtag/js', 'google-analytics.com/ga.js', "ga('create'", 'gtag(' ),
				'platforms' => array( 'Google Analytics' ),
				'gcm'       => true,
			),
			'google-tag-manager' => array(
				'label'     => 'Google Tag Manager',
				'category'  => Category::Analytics,
				'patterns'  => array( 'googletagmanager.com/gtm.js' ),
				'platforms' => array( 'Google Tag Manager' ),
				'gcm'       => true,
			),
			'google-ads' => array(
				'label'     => 'Google Ads / DoubleClick / AdSense',
				'category'  => Category::Marketing,
				'patterns'  => array( 'googleadservices.com', 'googlesyndication.com', 'doubleclick.net', 'adsbygoogle' ),
				'platforms' => array( 'Google Ads', 'Google AdSense', 'DoubleClick/Google Marketing' ),
				'gcm'       => true,
			),
			'meta-pixel' => array(
				'label'     => 'Meta (Facebook) Pixel',
				'category'  => Category::Marketing,
				'patterns'  => array( 'connect.facebook.net', 'fbq(' ),
				'platforms' => array( 'Facebook' ),
				'gcm'       => false,
			),
			'hotjar' => array(
				'label'     => 'Hotjar',
				'category'  => Category::Analytics,
				'patterns'  => array( 'static.hotjar.com', 'hotjar.com/c/hotjar-' ),
				'platforms' => array( 'Hotjar' ),
				'gcm'       => false,
			),
			'clarity' => array(
				'label'     => 'Microsoft Clarity',
				'category'  => Category::Analytics,
				'patterns'  => array( 'clarity.ms/tag' ),
				'platforms' => array(),
				'gcm'       => false,
			),
			'microsoft-ads' => array(
				'label'     => 'Microsoft Advertising (UET)',
				'category'  => Category::Marketing,
				'patterns'  => array( 'bat.bing.com' ),
				'platforms' => array( 'Bing / Microsoft' ),
				'gcm'       => false,
			),
			'linkedin' => array(
				'label'     => 'LinkedIn Insight Tag',
				'category'  => Category::Marketing,
				'patterns'  => array( 'snap.licdn.com', '_linkedin_partner_id' ),
				'platforms' => array( 'LinkedIn' ),
				'gcm'       => false,
			),
			'tiktok' => array(
				'label'     => 'TikTok Pixel',
				'category'  => Category::Marketing,
				'patterns'  => array( 'analytics.tiktok.com' ),
				'platforms' => array( 'TikTok' ),
				'gcm'       => false,
			),
			'x-ads' => array(
				'label'     => 'X (Twitter) Pixel',
				'category'  => Category::Marketing,
				'patterns'  => array( 'static.ads-twitter.com' ),
				'platforms' => array(),
				'gcm'       => false,
			),
			'pinterest' => array(
				'label'     => 'Pinterest Tag',
				'category'  => Category::Marketing,
				'patterns'  => array( 's.pinimg.com/ct/core.js' ),
				'platforms' => array( 'Pinterest' ),
				'gcm'       => false,
			),
			'matomo' => array(
				'label'     => 'Matomo / Piwik',
				'category'  => Category::Analytics,
				'patterns'  => array( 'matomo.js', 'piwik.js', '_paq.push' ),
				'platforms' => array( 'Matomo', 'Piwik' ),
				'gcm'       => false,
			),
			'yandex-metrica' => array(
				'label'     => 'Yandex Metrica',
				'category'  => Category::Analytics,
				'patterns'  => array( 'mc.yandex.ru/metrika' ),
				'platforms' => array( 'Yandex.Metrica' ),
				'gcm'       => false,
			),
			'hubspot' => array(
				'label'     => 'HubSpot',
				'category'  => Category::Marketing,
				'patterns'  => array( 'js.hs-scripts.com', 'js.hs-analytics.net' ),
				'platforms' => array( 'Hubspot', 'HubSpot' ),
				'gcm'       => false,
			),
		);

		/**
		 * Permite añadir o quitar servicios del catálogo.
		 *
		 * @param array $services Catálogo de servicios.
		 */
		$filtered = apply_filters( 'consent_guard_services', $services );
		return is_array( $filtered ) ? $filtered : $services;
	}

	/**
	 * Reglas activas de bloqueo: catálogo + reglas propias del admin.
	 *
	 * @return list<array{pattern: string, category: Category, service: string}>
	 */
	public static function rules(): array {
		$skip_google = Settings::bool( 'google_consent_mode' ) && 'advanced' === Settings::string( 'gcm_mode' );
		$rules       = array();

		foreach ( self::all() as $id => $service ) {
			if ( ! ( $service['category'] ?? null ) instanceof Category || ( $skip_google && ! empty( $service['gcm'] ) ) ) {
				continue;
			}
			foreach ( (array) ( $service['patterns'] ?? array() ) as $pattern ) {
				if ( is_string( $pattern ) && '' !== $pattern ) {
					$rules[] = array(
						'pattern'  => $pattern,
						'category' => $service['category'],
						'service'  => (string) $id,
					);
				}
			}
		}

		$custom = Settings::get( 'custom_rules' );
		foreach ( is_array( $custom ) ? $custom : array() as $rule ) {
			$category = Category::fromInput( $rule['category'] ?? null );
			if ( null !== $category && is_string( $rule['pattern'] ?? null ) ) {
				$rules[] = array(
					'pattern'  => $rule['pattern'],
					'category' => $category,
					'service'  => 'custom',
				);
			}
		}

		return $rules;
	}

	/**
	 * Devuelve la primera regla cuyo patrón aparece en el texto, o null.
	 *
	 * @param list<array{pattern: string, category: Category, service: string}> $rules Reglas.
	 * @return array{pattern: string, category: Category, service: string}|null
	 */
	public static function match( string $haystack, array $rules ): ?array {
		if ( '' === $haystack ) {
			return null;
		}
		return array_find( $rules, static fn ( array $rule ): bool => str_contains( $haystack, $rule['pattern'] ) );
	}
}
