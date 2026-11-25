<?php
/**
 * Parte pública: Consent Mode en <head>, carga del JS/CSS y HTML del banner y las preferencias.
 * Todo el HTML es igual para todos los visitantes (apto para caché); el JS decide qué mostrar.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	public const string HANDLE = 'consent-guard';

	/** Flags de JSON seguros para incrustar en <script>. */
	private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'printConsentModeDefaults' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 100 );
	}

	private static function active(): bool {
		return ! Plugin::isBuilderPreview() && ! is_customize_preview();
	}

	/**
	 * Configuración que recibe el JS.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return array(
			'cookie'     => CookieRegistry::OWN_COOKIE,
			'revision'   => Settings::int( 'revision' ),
			'days'       => Settings::int( 'consent_days' ),
			'path'       => is_string( $path ) && '' !== $path ? $path : '/',
			'secure'     => is_ssl(),
			'endpoint'   => esc_url_raw( rest_url( RestController::NAMESPACE . '/consent' ) ),
			'log'        => Settings::bool( 'log_consents' ),
			'gcm'        => Settings::bool( 'google_consent_mode' ),
			'gpc'        => Settings::bool( 'respect_gpc' ),
			'categories' => array_map( static fn ( Category $c ): string => $c->value, Category::optional() ),
			'cookies'    => CookieRegistry::namesByOptionalCategory(),
		);
	}

	/**
	 * Valores por defecto de Google Consent Mode v2 (todo denegado), antes que cualquier etiqueta de Google.
	 * Si el visitante ya eligió, se envía su elección en el mismo momento.
	 */
	public static function printConsentModeDefaults(): void {
		if ( ! self::active() || ! Settings::bool( 'google_consent_mode' ) ) {
			return;
		}

		$config = wp_json_encode(
			array(
				'cookie'   => CookieRegistry::OWN_COOKIE,
				'revision' => Settings::int( 'revision' ),
			),
			self::JSON_FLAGS
		);

		$js = <<<JS
		window.dataLayer = window.dataLayer || [];
		window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
		(function (cfg) {
			var denied = 'denied';
			gtag('consent', 'default', {
				ad_storage: denied, ad_user_data: denied, ad_personalization: denied,
				analytics_storage: denied, functionality_storage: denied, personalization_storage: denied,
				security_storage: 'granted', wait_for_update: 500
			});
			gtag('set', 'ads_data_redaction', true);
			try {
				var m = document.cookie.match(new RegExp('(?:^|; )' + cfg.cookie + '=([^;]*)'));
				if (!m) { return; }
				var s = JSON.parse(decodeURIComponent(m[1]));
				if (!s || s.r !== cfg.revision || !Array.isArray(s.c)) { return; }
				var has = function (c) { return s.c.indexOf(c) !== -1 ? 'granted' : denied; };
				gtag('consent', 'update', {
					ad_storage: has('marketing'), ad_user_data: has('marketing'), ad_personalization: has('marketing'),
					analytics_storage: has('analytics'), functionality_storage: has('functional'), personalization_storage: has('functional')
				});
			} catch (e) {}
		})({$config});
		JS;

		wp_print_inline_script_tag( $js, array( 'id' => 'consent-guard-gcm' ) );
	}

	public static function enqueue(): void {
		if ( ! self::active() ) {
			return;
		}

		$base = plugin_dir_url( CONSENT_GUARD_FILE ) . 'assets/';

		wp_enqueue_style( self::HANDLE, $base . 'consent.css', array(), Plugin::VERSION );
		wp_enqueue_script(
			self::HANDLE,
			$base . 'consent.js',
			array(),
			Plugin::VERSION,
			array(
				'in_footer' => false,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( self::HANDLE, 'window.consentGuardConfig = ' . wp_json_encode( self::config(), self::JSON_FLAGS ) . ';', 'before' );
	}

	/**
	 * HTML del banner, del panel de preferencias y del botón para reabrirlo. Oculto hasta que el JS decida.
	 */
	public static function render(): void {
		if ( ! self::active() ) {
			return;
		}

		$layout = Settings::string( 'layout' );
		$popup  = 'popup' === $layout;
		$style  = sprintf(
			'--cg-bg:%s;--cg-text:%s;--cg-accent:%s;--cg-accent-text:%s',
			sanitize_hex_color( Settings::string( 'color_bg' ) ) ?: '#ffffff',
			sanitize_hex_color( Settings::string( 'color_text' ) ) ?: '#1f2430',
			sanitize_hex_color( Settings::string( 'color_accent' ) ) ?: '#7e22ce',
			sanitize_hex_color( Settings::string( 'color_accent_text' ) ) ?: '#ffffff'
		);
		$groups = CookieRegistry::grouped();
		$tag    = $popup ? 'dialog' : 'div';
		?>
		<div id="consent-guard" class="cg" style="<?php echo esc_attr( $style ); ?>" data-cg-layout="<?php echo esc_attr( $layout ); ?>">
			<<?php echo esc_html( $tag ); ?> class="cg-banner cg-banner--<?php echo esc_attr( $layout ); ?> cg-pos--<?php echo esc_attr( Settings::string( 'position' ) ); ?>" <?php echo $popup ? '' : 'role="region" hidden'; ?> aria-labelledby="cg-title" aria-describedby="cg-message" data-cg-banner>
				<p class="cg-title" id="cg-title"><?php echo esc_html( Settings::string( 'title' ) ); ?></p>
				<div class="cg-message" id="cg-message">
					<?php echo wp_kses_post( Settings::sanitizeMessage( Settings::string( 'message' ) ) ); ?>
					<?php self::privacyLink(); ?>
				</div>
				<div class="cg-actions">
					<button type="button" class="cg-btn cg-btn--primary" data-cg-action="reject_all"><?php echo esc_html( Settings::string( 'reject_label' ) ); ?></button>
					<button type="button" class="cg-btn cg-btn--ghost" data-cg-action="customize"><?php echo esc_html( Settings::string( 'customize_label' ) ); ?></button>
					<button type="button" class="cg-btn cg-btn--primary" data-cg-action="accept_all"><?php echo esc_html( Settings::string( 'accept_label' ) ); ?></button>
				</div>
			</<?php echo esc_html( $tag ); ?>>

			<dialog class="cg-prefs" aria-labelledby="cg-prefs-title" data-cg-prefs>
				<form method="dialog" class="cg-prefs-form">
					<div class="cg-prefs-head">
						<h2 class="cg-prefs-title" id="cg-prefs-title"><?php echo esc_html( Settings::string( 'prefs_title' ) ); ?></h2>
						<button type="button" class="cg-close" data-cg-close aria-label="<?php esc_attr_e( 'Cerrar', 'consent-guard' ); ?>">&times;</button>
					</div>
					<div class="cg-prefs-body">
						<p class="cg-prefs-intro"><?php echo esc_html( Settings::string( 'prefs_intro' ) ); ?> <?php self::privacyLink(); ?></p>
						<?php foreach ( array( Category::Necessary, ...Category::optional() ) as $category ) : ?>
							<?php self::renderCategory( $category, $groups[ $category->value ] ?? array() ); ?>
						<?php endforeach; ?>
					</div>
					<div class="cg-actions cg-prefs-actions">
						<button type="button" class="cg-btn cg-btn--primary" data-cg-action="reject_all"><?php echo esc_html( Settings::string( 'reject_label' ) ); ?></button>
						<button type="button" class="cg-btn cg-btn--ghost" data-cg-action="save"><?php echo esc_html( Settings::string( 'save_label' ) ); ?></button>
						<button type="button" class="cg-btn cg-btn--primary" data-cg-action="accept_all"><?php echo esc_html( Settings::string( 'accept_label' ) ); ?></button>
					</div>
				</form>
			</dialog>

			<?php if ( Settings::bool( 'show_reopen' ) ) : ?>
				<button type="button" class="cg-reopen" data-cg-open hidden aria-label="<?php echo esc_attr( Settings::string( 'prefs_title' ) ); ?>">
					<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm6 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-7 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>
					<span><?php echo esc_html( Settings::string( 'reopen_label' ) ); ?></span>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Bloque de una categoría con su interruptor y la tabla de cookies.
	 *
	 * @param list<array<string, string>> $cookies Cookies de la categoría.
	 */
	private static function renderCategory( Category $category, array $cookies ): void {
		$id       = 'cg-cat-' . $category->value;
		$required = Category::Necessary === $category;
		?>
		<section class="cg-cat">
			<div class="cg-cat-head">
				<label class="cg-cat-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $category->label() ); ?></label>
				<?php if ( $required ) : ?>
					<span class="cg-always"><?php esc_html_e( 'Siempre activas', 'consent-guard' ); ?></span>
				<?php endif; ?>
				<input type="checkbox" role="switch" class="cg-switch" id="<?php echo esc_attr( $id ); ?>" <?php echo $required ? 'checked disabled' : 'data-cg-toggle="' . esc_attr( $category->value ) . '"'; ?> aria-describedby="<?php echo esc_attr( $id ); ?>-desc">
			</div>
			<p class="cg-cat-desc" id="<?php echo esc_attr( $id ); ?>-desc"><?php echo esc_html( $category->description() ); ?></p>
			<?php if ( array() !== $cookies ) : ?>
				<details class="cg-cookies">
					<summary>
						<?php
						/* translators: %d: número de cookies */
						echo esc_html( sprintf( _n( 'Ver %d cookie', 'Ver %d cookies', count( $cookies ), 'consent-guard' ), count( $cookies ) ) );
						?>
					</summary>
					<?php self::cookieTable( $cookies ); ?>
				</details>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Tabla de cookies (también la usa el shortcode).
	 *
	 * @param list<array<string, string>> $cookies Cookies.
	 */
	public static function cookieTable( array $cookies ): void {
		?>
		<div class="cg-table-wrap">
			<table class="cg-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Cookie', 'consent-guard' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Proveedor', 'consent-guard' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Duración', 'consent-guard' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Finalidad', 'consent-guard' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cookies as $cookie ) : ?>
						<tr>
							<td><code><?php echo esc_html( $cookie['name'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( ( $cookie['source'] ?? '' ) ?: ( $cookie['domain'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( ( $cookie['duration'] ?? '' ) ?: '—' ); ?></td>
							<td><?php echo esc_html( ( $cookie['description'] ?? '' ) ?: '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function privacyLink(): void {
		$url = Settings::string( 'privacy_url' );
		if ( '' === $url ) {
			return;
		}
		printf(
			'<a class="cg-link" href="%s">%s</a>',
			esc_url( $url, array( 'http', 'https' ) ),
			esc_html( Settings::string( 'privacy_label' ) )
		);
	}
}
