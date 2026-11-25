<?php
/**
 * Pantalla Ajustes > Permisio, con pestañas.
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard\Admin;

use ConsentGuard\Category;
use ConsentGuard\ConsentLog;
use ConsentGuard\CookieRegistry;
use ConsentGuard\Plugin;
use ConsentGuard\Services;
use ConsentGuard\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const string SLUG = 'consent-guard';

	private const string FIELD = 'cg';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_init', array( ConsentLog::class, 'scheduleCron' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CONSENT_GUARD_FILE ), array( self::class, 'actionLinks' ) );
		AdminActions::register();
	}

	public static function menu(): void {
		add_options_page(
			__( 'Permisio', 'consent-guard' ),
			__( 'Permisio', 'consent-guard' ),
			AdminActions::CAPABILITY,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	public static function assets( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'consent-guard-admin', plugin_dir_url( CONSENT_GUARD_FILE ) . 'assets/admin.css', array(), Plugin::VERSION );
	}

	/**
	 * @param array<int|string, string> $links Enlaces de la fila del plugin.
	 * @return array<int|string, string>
	 */
	public static function actionLinks( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Ajustes', 'consent-guard' ) )
		);
		return $links;
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function tabs(): array {
		return array(
			'banner'  => array( __( 'Banner', 'consent-guard' ), 'dashicons-format-chat' ),
			'scripts' => array( __( 'Scripts y Consent Mode', 'consent-guard' ), 'dashicons-shield' ),
			'cookies' => array( __( 'Cookies', 'consent-guard' ), 'dashicons-list-view' ),
			'log'     => array( __( 'Registro', 'consent-guard' ), 'dashicons-clipboard' ),
		);
	}

	public static function render(): void {
		if ( ! current_user_can( AdminActions::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura de la pestaña y el aviso.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'banner';
		$notice = isset( $_GET['cg_notice'] ) ? sanitize_key( wp_unslash( $_GET['cg_notice'] ) ) : '';
		// phpcs:enable
		$tabs = self::tabs();
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'banner';
		}
		$on = Settings::bool( 'enabled' );
		?>
		<div class="wrap">
		<div class="cga">
			<div class="cga-hero">
				<div>
					<span class="cga-eyebrow"><span class="dashicons dashicons-privacy"></span> <?php esc_html_e( 'Privacidad y cookies', 'consent-guard' ); ?></span>
					<h1 class="cga-title">Permisio <span class="cga-version">v<?php echo esc_html( Plugin::VERSION ); ?></span></h1>
					<p class="cga-sub"><?php esc_html_e( 'Banner de consentimiento, bloqueo de scripts de rastreo y registro de consentimientos.', 'consent-guard' ); ?></p>
				</div>
				<div class="cga-pill">
					<span class="cga-dot <?php echo $on ? 'cga-dot--on' : 'cga-dot--off'; ?>"></span>
					<?php echo $on ? esc_html__( 'Banner activo', 'consent-guard' ) : esc_html__( 'Banner desactivado', 'consent-guard' ); ?>
				</div>
			</div>

			<nav class="cga-tabs" aria-label="<?php esc_attr_e( 'Secciones', 'consent-guard' ); ?>">
				<?php foreach ( $tabs as $slug => $item ) : ?>
					<a href="<?php echo esc_url( self::url( $slug ) ); ?>" class="cga-tab <?php echo $slug === $tab ? 'is-active' : ''; ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $item[1] ); ?>"></span> <?php echo esc_html( $item[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="cga-body">
				<?php
				self::notice( $notice );
				match ( $tab ) {
					'scripts' => self::tabScripts(),
					'cookies' => self::tabCookies(),
					'log'     => self::tabLog(),
					default   => self::tabBanner(),
				};
				?>
			</div>
		</div>
		</div>
		<?php
	}

	private static function url( string $tab ): string {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);
	}

	private static function notice( string $code ): void {
		$messages = array(
			'saved'          => array( 'ok', __( 'Cambios guardados.', 'consent-guard' ) ),
			'revision'       => array( 'ok', __( 'Listo: todos los visitantes volverán a ver el banner en su próxima visita.', 'consent-guard' ) ),
			'scanned'        => array( 'ok', __( 'Escaneo terminado.', 'consent-guard' ) ),
			'cookie_added'   => array( 'ok', __( 'Cookie guardada.', 'consent-guard' ) ),
			'cookie_deleted' => array( 'ok', __( 'Cookie eliminada.', 'consent-guard' ) ),
			'cookie_invalid' => array( 'error', __( 'El nombre de la cookie no es válido: usa solo letras, números y los símbolos permitidos en cookies, sin espacios.', 'consent-guard' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		[ $type, $text ] = $messages[ $code ];
		printf(
			'<div class="cga-alert cga-alert--%1$s" role="status"><span class="dashicons dashicons-%2$s"></span><span>%3$s</span></div>',
			esc_attr( $type ),
			'ok' === $type ? 'yes-alt' : 'warning',
			esc_html( $text )
		);
	}

	/**
	 * Abre un formulario hacia admin-post.php con su nonce.
	 */
	private static function formOpen( string $action, string $class = '' ): void {
		printf( '<form method="post" action="%s" class="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $class ) );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );
		self::nonce( $action );
	}

	/**
	 * Nonce sin atributo id: hay varios formularios por pantalla y los id no se pueden repetir.
	 */
	private static function nonce( string $action ): void {
		printf( '<input type="hidden" name="_wpnonce" value="%s">', esc_attr( wp_create_nonce( $action ) ) );
	}

	private static function name( string $key ): string {
		return self::FIELD . '[' . $key . ']';
	}

	private static function textField( string $key, string $label, string $hint = '', string $type = 'text' ): void {
		$id = 'cg-' . $key;
		?>
		<div class="cga-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( (string) Settings::get( $key ) ); ?>"<?php echo $hint ? ' aria-describedby="' . esc_attr( $id ) . '-hint"' : ''; ?>>
			<?php if ( $hint ) : ?>
				<p class="cga-hint" id="<?php echo esc_attr( $id ); ?>-hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function toggle( string $key, string $label, string $desc ): void {
		$id = 'cg-' . $key;
		?>
		<div class="cga-item">
			<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="1" <?php checked( Settings::bool( $key ) ); ?> aria-describedby="<?php echo esc_attr( $id ); ?>-desc">
			<div>
				<label class="cga-item-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<p class="cga-item-desc" id="<?php echo esc_attr( $id ); ?>-desc"><?php echo esc_html( $desc ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, string> $options valor => etiqueta.
	 */
	private static function select( string $key, string $label, array $options ): void {
		$id      = 'cg-' . $key;
		$current = Settings::string( $key );
		?>
		<div class="cga-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>">
				<?php foreach ( $options as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	private static function saveButton(): void {
		echo '<div class="cga-actions">';
		submit_button( __( 'Guardar cambios', 'consent-guard' ), 'cga-btn cga-btn--primary', 'submit', false );
		echo '</div>';
	}

	// ---------------------------------------------------------------------
	// Pestañas
	// ---------------------------------------------------------------------

	private static function tabBanner(): void {
		self::formOpen( 'consent_guard_save' );
		echo '<input type="hidden" name="tab" value="banner">';
		?>
		<div class="cga-grid cga-grid--2">
			<div>
				<div class="cga-card cga-card--accent">
					<h2 class="cga-card-title"><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Textos del banner', 'consent-guard' ); ?></h2>
					<?php
					self::toggle( 'enabled', __( 'Mostrar el banner', 'consent-guard' ), __( 'Si lo apagas, no se muestra el banner ni se bloquea ningún script.', 'consent-guard' ) );
					self::textField( 'title', __( 'Título', 'consent-guard' ) );
					?>
					<div class="cga-field">
						<label for="cg-message"><?php esc_html_e( 'Mensaje', 'consent-guard' ); ?></label>
						<textarea id="cg-message" rows="4" name="<?php echo esc_attr( self::name( 'message' ) ); ?>" aria-describedby="cg-message-hint"><?php echo esc_textarea( Settings::string( 'message' ) ); ?></textarea>
						<p class="cga-hint" id="cg-message-hint"><?php esc_html_e( 'Permite enlaces, negrita y cursiva. El resto del HTML se elimina.', 'consent-guard' ); ?></p>
					</div>
					<?php
					self::textField( 'accept_label', __( 'Botón «Aceptar»', 'consent-guard' ) );
					self::textField( 'reject_label', __( 'Botón «Rechazar»', 'consent-guard' ) );
					self::textField( 'customize_label', __( 'Botón «Personalizar»', 'consent-guard' ) );
					self::textField( 'save_label', __( 'Botón «Guardar elección»', 'consent-guard' ) );
					self::textField( 'prefs_title', __( 'Título del panel de preferencias', 'consent-guard' ) );
					self::textField( 'prefs_intro', __( 'Introducción del panel', 'consent-guard' ) );
					self::textField( 'privacy_url', __( 'URL de la política de cookies', 'consent-guard' ), __( 'Se enlaza en el banner y en el panel. Solo http o https.', 'consent-guard' ), 'url' );
					self::textField( 'privacy_label', __( 'Texto del enlace a la política', 'consent-guard' ) );
					?>
				</div>
			</div>
			<div>
				<div class="cga-card">
					<h2 class="cga-card-title"><span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Aspecto', 'consent-guard' ); ?></h2>
					<?php
					self::select(
						'layout',
						__( 'Formato', 'consent-guard' ),
						array(
							'box'   => __( 'Caja flotante', 'consent-guard' ),
							'bar'   => __( 'Barra inferior', 'consent-guard' ),
							'popup' => __( 'Ventana central (bloquea la página)', 'consent-guard' ),
						)
					);
					self::select(
						'position',
						__( 'Posición de la caja', 'consent-guard' ),
						array(
							'bottom-left'   => __( 'Abajo a la izquierda', 'consent-guard' ),
							'bottom-right'  => __( 'Abajo a la derecha', 'consent-guard' ),
							'bottom-center' => __( 'Abajo al centro', 'consent-guard' ),
						)
					);
					foreach ( array(
						'color_bg'          => __( 'Fondo', 'consent-guard' ),
						'color_text'        => __( 'Texto', 'consent-guard' ),
						'color_accent'      => __( 'Botones y enlaces', 'consent-guard' ),
						'color_accent_text' => __( 'Texto de los botones', 'consent-guard' ),
					) as $key => $label ) {
						self::textField( $key, $label, '', 'color' );
					}
					self::toggle( 'show_reopen', __( 'Botón flotante para cambiar la elección', 'consent-guard' ), __( 'Retirar el consentimiento debe ser tan fácil como darlo (RGPD art. 7.3). Si lo apagas, pon el shortcode [consent_guard_preferences] en el pie de página.', 'consent-guard' ) );
					self::textField( 'reopen_label', __( 'Texto del botón flotante', 'consent-guard' ) );
					self::textField( 'consent_days', __( 'Días que dura la elección', 'consent-guard' ), __( 'Entre 1 y 395 (13 meses, el máximo que recomiendan las autoridades europeas).', 'consent-guard' ), 'number' );
					?>
				</div>
			</div>
		</div>
		<?php
		self::saveButton();
		echo '</form>';

		self::formOpen( 'consent_guard_bump_revision', 'cga-card' );
		?>
			<h2 class="cga-card-title"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Volver a pedir el consentimiento', 'consent-guard' ); ?></h2>
			<p class="cga-card-lead">
				<?php
				printf(
					/* translators: %d: número de revisión */
					esc_html__( 'Úsalo cuando añadas un servicio nuevo o cambies tu política. Todos los visitantes verán el banner otra vez. Revisión actual: %d.', 'consent-guard' ),
					(int) Settings::int( 'revision' )
				);
				?>
			</p>
			<div class="cga-actions"><button type="submit" class="cga-btn cga-btn--soft"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Pedir consentimiento de nuevo', 'consent-guard' ); ?></button></div>
		</form>
		<?php
	}

	private static function tabScripts(): void {
		self::formOpen( 'consent_guard_save' );
		echo '<input type="hidden" name="tab" value="scripts">';
		?>
		<div class="cga-grid cga-grid--2">
			<div>
				<div class="cga-card cga-card--accent">
					<h2 class="cga-card-title"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Bloqueo y señales', 'consent-guard' ); ?></h2>
					<?php
					self::toggle( 'block_scripts', __( 'Bloquear scripts de rastreo hasta que haya consentimiento', 'consent-guard' ), __( 'Los scripts reconocidos se cargan desactivados y se activan cuando el visitante acepta su categoría. Funciona con caché de página.', 'consent-guard' ) );
					self::toggle( 'google_consent_mode', __( 'Google Consent Mode v2', 'consent-guard' ), __( 'Envía a Google la elección del visitante (todo denegado hasta que acepte). Necesario para Google Ads y Analytics en Europa.', 'consent-guard' ) );
					self::select(
						'gcm_mode',
						__( 'Modo de Consent Mode', 'consent-guard' ),
						array(
							'basic'    => __( 'Básico: las etiquetas de Google no cargan hasta el consentimiento (más seguro)', 'consent-guard' ),
							'advanced' => __( 'Avanzado: cargan siempre y Google ajusta lo que mide según la señal', 'consent-guard' ),
						)
					);
					self::toggle( 'respect_gpc', __( 'Respetar Global Privacy Control (GPC)', 'consent-guard' ), __( 'Si el navegador del visitante envía la señal GPC, publicidad queda desactivada aunque pulse «Aceptar todo». Lo exigen varias leyes de EE. UU. (California, Colorado).', 'consent-guard' ) );
					?>
					<div class="cga-field">
						<label for="cg-custom_rules"><?php esc_html_e( 'Reglas propias', 'consent-guard' ); ?></label>
						<textarea id="cg-custom_rules" class="cga-mono" rows="6" name="<?php echo esc_attr( self::name( 'custom_rules' ) ); ?>" aria-describedby="cg-rules-hint" placeholder="widget.ejemplo.com | functional"><?php echo esc_textarea( Settings::rulesAsText() ); ?></textarea>
						<p class="cga-hint" id="cg-rules-hint"><?php esc_html_e( 'Una por línea: texto que aparece en la URL o el código del script | categoría (functional, analytics o marketing). Mínimo 4 caracteres.', 'consent-guard' ); ?></p>
					</div>
				</div>
			</div>
			<div>
				<div class="cga-card">
					<h2 class="cga-card-title"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Servicios que se reconocen solos', 'consent-guard' ); ?></h2>
					<div class="cga-tablewrap">
						<table class="cga-table">
							<thead><tr><th scope="col"><?php esc_html_e( 'Servicio', 'consent-guard' ); ?></th><th scope="col"><?php esc_html_e( 'Categoría', 'consent-guard' ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( Services::all() as $service ) : ?>
									<tr>
										<td><?php echo esc_html( (string) $service['label'] ); ?></td>
										<td><span class="cga-badge cga-badge--sim"><?php echo esc_html( $service['category'] instanceof Category ? $service['category']->label() : '' ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="cga-note">
						<?php esc_html_e( 'Para bloquear un script a mano, añádele el atributo data-cg-category="analytics" (o functional, marketing). Los iframes (YouTube, mapas) no se bloquean en esta versión.', 'consent-guard' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
		self::saveButton();
		echo '</form>';
	}

	private static function tabCookies(): void {
		$scan    = get_transient( 'consent_guard_scan_' . get_current_user_id() );
		$last    = get_option( 'consent_guard_last_scan' );
		$cookies = CookieRegistry::all();
		$options = array();
		foreach ( Category::assignable() as $category ) {
			$options[ $category->value ] = $category->label();
		}

		if ( is_array( $scan ) ) {
			delete_transient( 'consent_guard_scan_' . get_current_user_id() );
			?>
			<div class="cga-alert cga-alert--info" role="status">
				<span class="dashicons dashicons-search"></span>
				<span>
					<?php
					printf(
						/* translators: 1: páginas revisadas, 2: errores, 3: cookies nuevas, 4: servicios detectados */
						esc_html__( 'Páginas revisadas: %1$d (con error: %2$d). Cookies nuevas: %3$d. Servicios detectados: %4$s.', 'consent-guard' ),
						(int) ( $scan['urls'] ?? 0 ),
						(int) ( $scan['errors'] ?? 0 ),
						(int) ( $scan['cookies'] ?? 0 ),
						esc_html( array() !== ( $scan['services'] ?? array() ) ? implode( ', ', (array) $scan['services'] ) : __( 'ninguno', 'consent-guard' ) )
					);
					?>
				</span>
			</div>
			<?php
		}
		?>
		<div class="cga-grid cga-grid--2">
			<div>
				<?php self::formOpen( 'consent_guard_scan', 'cga-card cga-card--accent' ); ?>
					<h2 class="cga-card-title"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Escanear el sitio', 'consent-guard' ); ?></h2>
					<p class="cga-card-lead"><?php esc_html_e( 'Visita la portada y las 9 páginas más recientes como un visitante anónimo. Solo lee: nunca envía formularios. Detecta las cookies del servidor y los servicios de terceros por sus scripts, y añade sus cookies conocidas. No cambia las cookies que ya editaste.', 'consent-guard' ); ?></p>
					<?php if ( is_array( $last ) && isset( $last['time'] ) ) : ?>
						<p class="cga-hint">
							<?php
							/* translators: %s: hace cuánto tiempo */
							echo esc_html( sprintf( __( 'Último escaneo: hace %s.', 'consent-guard' ), human_time_diff( (int) $last['time'] ) ) );
							?>
						</p>
					<?php endif; ?>
					<div class="cga-actions"><button type="submit" class="cga-btn cga-btn--primary"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Escanear ahora', 'consent-guard' ); ?></button></div>
				</form>
			</div>
			<div>
				<?php self::formOpen( 'consent_guard_cookie_add', 'cga-card' ); ?>
					<h2 class="cga-card-title"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Añadir cookie a mano', 'consent-guard' ); ?></h2>
					<p class="cga-card-lead"><?php esc_html_e( 'Si la cookie está en la Open Cookie Database, los campos vacíos se completan solos.', 'consent-guard' ); ?></p>
					<div class="cga-field"><label for="cg-new-name"><?php esc_html_e( 'Nombre', 'consent-guard' ); ?></label><input type="text" id="cg-new-name" name="name" required maxlength="100" class="cga-mono"></div>
					<div class="cga-field">
						<label for="cg-new-category"><?php esc_html_e( 'Categoría', 'consent-guard' ); ?></label>
						<select id="cg-new-category" name="category">
							<option value=""><?php esc_html_e( 'Automática (según la base de datos)', 'consent-guard' ); ?></option>
							<?php foreach ( $options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cga-field"><label for="cg-new-domain"><?php esc_html_e( 'Dominio o proveedor', 'consent-guard' ); ?></label><input type="text" id="cg-new-domain" name="domain" maxlength="200"></div>
					<div class="cga-field"><label for="cg-new-duration"><?php esc_html_e( 'Duración', 'consent-guard' ); ?></label><input type="text" id="cg-new-duration" name="duration" maxlength="60"></div>
					<div class="cga-field"><label for="cg-new-description"><?php esc_html_e( 'Finalidad', 'consent-guard' ); ?></label><input type="text" id="cg-new-description" name="description" maxlength="500"></div>
					<div class="cga-actions"><button type="submit" class="cga-btn cga-btn--soft"><?php esc_html_e( 'Añadir', 'consent-guard' ); ?></button></div>
				</form>
			</div>
		</div>

		<div class="cga-card">
			<h2 class="cga-card-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Cookies del sitio', 'consent-guard' ); ?></h2>
			<p class="cga-card-lead"><?php esc_html_e( 'Es la lista que ven los visitantes en el panel de preferencias. Cuando alguien retira un permiso, se borran las cookies de esa categoría.', 'consent-guard' ); ?></p>
			<?php if ( array() === $cookies ) : ?>
				<p class="cga-empty"><?php esc_html_e( 'Todavía no hay cookies. Escanea el sitio o añade una a mano.', 'consent-guard' ); ?></p>
			<?php else : ?>
				<?php self::formOpen( 'consent_guard_cookie_update' ); ?>
					<div class="cga-tablewrap">
						<table class="cga-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Cookie', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Categoría', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Proveedor', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Duración', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Finalidad', 'consent-guard' ); ?></th>
									<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Acciones', 'consent-guard' ); ?></span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $cookies as $cookie ) : ?>
									<tr>
										<td><code><?php echo esc_html( $cookie['name'] ); ?></code></td>
										<td>
											<label class="screen-reader-text" for="cg-cat-<?php echo esc_attr( md5( $cookie['name'] ) ); ?>"><?php esc_html_e( 'Categoría', 'consent-guard' ); ?></label>
											<select id="cg-cat-<?php echo esc_attr( md5( $cookie['name'] ) ); ?>" name="category[<?php echo esc_attr( $cookie['name'] ); ?>]">
												<?php foreach ( $options as $value => $label ) : ?>
													<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $cookie['category'], $value ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td><?php echo esc_html( $cookie['source'] ?: $cookie['domain'] ); ?></td>
										<td><?php echo esc_html( $cookie['duration'] ?: '—' ); ?></td>
										<td><?php echo esc_html( $cookie['description'] ?: '—' ); ?></td>
										<td>
											<button type="submit" class="cga-btn cga-btn--soft" form="cg-del-<?php echo esc_attr( md5( $cookie['name'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nombre de la cookie */ __( 'Eliminar %s', 'consent-guard' ), $cookie['name'] ) ); ?>"><span class="dashicons dashicons-trash"></span></button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php self::saveButton(); ?>
				</form>
				<?php
				// Un formulario de borrado por cookie, fuera de la tabla (los formularios no se pueden anidar).
				foreach ( $cookies as $cookie ) {
					printf( '<form method="post" action="%s" id="cg-del-%s" hidden>', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( md5( $cookie['name'] ) ) );
					echo '<input type="hidden" name="action" value="consent_guard_cookie_delete">';
					printf( '<input type="hidden" name="name" value="%s">', esc_attr( $cookie['name'] ) );
					self::nonce( 'consent_guard_cookie_delete' );
					echo '</form>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function tabLog(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo paginación.
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$total   = ConsentLog::count();
		$rows    = ConsentLog::page( $page );
		$pages   = max( 1, (int) ceil( $total / 50 ) );
		$summary = ConsentLog::summary( 30 );
		$labels  = array(
			'accept_all' => __( 'Aceptó todo', 'consent-guard' ),
			'reject_all' => __( 'Rechazó todo', 'consent-guard' ),
			'custom'     => __( 'Eligió categorías', 'consent-guard' ),
		);
		?>
		<div class="cga-kpis">
			<div class="cga-kpi"><span class="cga-kpi-icon"><span class="dashicons dashicons-clipboard"></span></span><div><div class="cga-kpi-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></div><div class="cga-kpi-label"><?php esc_html_e( 'Registros guardados', 'consent-guard' ); ?></div></div></div>
			<div class="cga-kpi cga-kpi--green"><span class="cga-kpi-icon"><span class="dashicons dashicons-yes"></span></span><div><div class="cga-kpi-value"><?php echo esc_html( number_format_i18n( $summary['accept_all'] ) ); ?></div><div class="cga-kpi-label"><?php esc_html_e( 'Aceptaron todo (30 días)', 'consent-guard' ); ?></div></div></div>
			<div class="cga-kpi cga-kpi--red"><span class="cga-kpi-icon"><span class="dashicons dashicons-no"></span></span><div><div class="cga-kpi-value"><?php echo esc_html( number_format_i18n( $summary['reject_all'] ) ); ?></div><div class="cga-kpi-label"><?php esc_html_e( 'Rechazaron todo (30 días)', 'consent-guard' ); ?></div></div></div>
			<div class="cga-kpi cga-kpi--blue"><span class="cga-kpi-icon"><span class="dashicons dashicons-admin-settings"></span></span><div><div class="cga-kpi-value"><?php echo esc_html( number_format_i18n( $summary['custom'] ) ); ?></div><div class="cga-kpi-label"><?php esc_html_e( 'Eligieron categorías (30 días)', 'consent-guard' ); ?></div></div></div>
		</div>

		<div class="cga-grid cga-grid--2">
			<div class="cga-card cga-card--accent">
				<h2 class="cga-card-title"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Últimos consentimientos', 'consent-guard' ); ?></h2>
				<?php if ( array() === $rows ) : ?>
					<p class="cga-empty"><?php esc_html_e( 'Todavía no hay registros.', 'consent-guard' ); ?></p>
				<?php else : ?>
					<div class="cga-tablewrap">
						<table class="cga-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Fecha', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Acción', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Categorías', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'IP anonimizada', 'consent-guard' ); ?></th>
									<th scope="col"><?php esc_html_e( 'ID', 'consent-guard' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( get_date_from_gmt( (string) $row->created_at, 'Y-m-d H:i' ) ); ?></td>
										<td><?php echo esc_html( $labels[ $row->action ] ?? (string) $row->action ); ?></td>
										<td><?php echo esc_html( '' !== (string) $row->categories ? (string) $row->categories : __( 'solo necesarias', 'consent-guard' ) ); ?></td>
										<td><code><?php echo esc_html( (string) $row->ip_anon ); ?></code></td>
										<td><code title="<?php echo esc_attr( (string) $row->consent_id ); ?>"><?php echo esc_html( substr( (string) $row->consent_id, 0, 8 ) ); ?>…</code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( $pages > 1 ) : ?>
						<div class="cga-actions">
							<?php
							echo wp_kses_post(
								(string) paginate_links(
									array(
										'base'    => add_query_arg( 'paged', '%#%', self::url( 'log' ) ),
										'format'  => '',
										'current' => $page,
										'total'   => $pages,
									)
								)
							);
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div>
				<?php self::formOpen( 'consent_guard_save', 'cga-card' ); ?>
					<input type="hidden" name="tab" value="log">
					<h2 class="cga-card-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Ajustes del registro', 'consent-guard' ); ?></h2>
					<?php
					self::toggle( 'log_consents', __( 'Registrar los consentimientos', 'consent-guard' ), __( 'Sirve para demostrar el consentimiento ante una autoridad (RGPD art. 7.1). Guarda fecha, elección, revisión de la política e IP anonimizada; nada más.', 'consent-guard' ) );
					self::textField( 'log_retention_days', __( 'Días que se conservan', 'consent-guard' ), __( 'Entre 30 y 1825. Lo más antiguo se borra solo cada día.', 'consent-guard' ), 'number' );
					self::toggle( 'keep_data_on_uninstall', __( 'Conservar los registros al desinstalar', 'consent-guard' ), __( 'Si está apagado, borrar el plugin elimina también el registro.', 'consent-guard' ) );
					self::saveButton();
					?>
				</form>
				<?php self::formOpen( 'consent_guard_export', 'cga-card' ); ?>
					<h2 class="cga-card-title"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Exportar', 'consent-guard' ); ?></h2>
					<p class="cga-card-lead"><?php esc_html_e( 'Descarga todo el registro en CSV.', 'consent-guard' ); ?></p>
					<div class="cga-actions"><button type="submit" class="cga-btn cga-btn--soft"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Descargar CSV', 'consent-guard' ); ?></button></div>
				</form>
			</div>
		</div>
		<?php
	}
}
