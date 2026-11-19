<?php
/**
 * Interfaz administrativa y pestañas de configuración para WP Site Ops Toolkit.
 *
 * @package WP_Site_Ops_Toolkit
 */

defined( 'ABSPATH' ) || exit;

trait WP_Site_Ops_Admin {

	/**
	 * Registra el menú de administración.
	 *
	 * @return void
	 */
	public static function init_admin() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_admin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_ops_cleanup_db', array( __CLASS__, 'handle_post_db_cleanup' ) );
		add_action( 'admin_post_ops_clear_mail_log', array( __CLASS__, 'handle_post_clear_mail_log' ) );
	}

	/**
	 * Agrega la página bajo Herramientas.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_management_page(
			__( 'Site Operations', 'wp-site-ops-toolkit' ),
			__( 'Site Operations', 'wp-site-ops-toolkit' ),
			'manage_options',
			'wp-site-ops-toolkit',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Registra los ajustes de la opción en WordPress.
	 *
	 * @return void
	 */
	public static function register_admin_settings() {
		register_setting(
			'wp_site_ops_toolkit_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitiza las opciones enviadas desde el formulario.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$clean = array();

		$clean['maintenance_enabled']      = ! empty( $input['maintenance_enabled'] );
		$clean['maintenance_title']        = ! empty( $input['maintenance_title'] ) ? sanitize_text_field( $input['maintenance_title'] ) : __( 'Sitio en mantenimiento', 'wp-site-ops-toolkit' );
		$clean['maintenance_message']      = ! empty( $input['maintenance_message'] ) ? sanitize_textarea_field( $input['maintenance_message'] ) : '';
		$clean['maintenance_ip_whitelist'] = ! empty( $input['maintenance_ip_whitelist'] ) ? sanitize_textarea_field( $input['maintenance_ip_whitelist'] ) : '';
		$clean['maintenance_secret_key']   = ! empty( $input['maintenance_secret_key'] ) ? sanitize_key( $input['maintenance_secret_key'] ) : '';

		$clean['db_cleaner_enabled']    = ! empty( $input['db_cleaner_enabled'] );
		$clean['mail_debugger_enabled'] = ! empty( $input['mail_debugger_enabled'] );
		$clean['mail_blackhole_mode']   = ! empty( $input['mail_blackhole_mode'] );

		return $clean;
	}

	/**
	 * Procesa la acción POST manual de limpieza de base de datos.
	 *
	 * @return void
	 */
	public static function handle_post_db_cleanup() {
		check_admin_referer( 'ops_cleanup_db_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'wp-site-ops-toolkit' ) );
		}

		$targets = isset( $_POST['targets'] ) && is_array( $_POST['targets'] ) ? array_map( 'sanitize_key', $_POST['targets'] ) : array();
		$results = self::execute_cleanup( $targets );

		$total = array_sum( $results );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-site-ops-toolkit', 'tab' => 'db', 'cleaned' => $total ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Procesa el vaciado del log de correos.
	 *
	 * @return void
	 */
	public static function handle_post_clear_mail_log() {
		check_admin_referer( 'ops_clear_mail_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'wp-site-ops-toolkit' ) );
		}

		delete_option( self::MAIL_LOG_KEY );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-site-ops-toolkit', 'tab' => 'mail', 'cleared' => 1 ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Encola el CSS del panel solo en la página de Site Operations.
	 *
	 * @param string $hook_suffix Hook de la pantalla actual en wp-admin.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'tools_page_wp-site-ops-toolkit' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wp-site-ops-toolkit-admin',
			plugin_dir_url( WP_SITE_OPS_TOOLKIT_FILE ) . 'assets/admin.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Renderiza la interfaz administrativa con diseño por pestañas.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'maintenance';
		$settings    = self::get_settings();
		$db_counts   = self::get_cleanup_counts();
		$mail_logs   = get_option( self::MAIL_LOG_KEY, array() );
		$my_ip       = self::get_client_ip();
		$key         = self::OPTION_KEY;
		$maint_on    = ! empty( $settings['maintenance_enabled'] );

		$tabs = array(
			'maintenance' => array( __( 'Modo Mantenimiento', 'wp-site-ops-toolkit' ), 'dashicons-hammer' ),
			'db'          => array( __( 'Limpieza de BD', 'wp-site-ops-toolkit' ), 'dashicons-database' ),
			'mail'        => array( __( 'Mail Debugger', 'wp-site-ops-toolkit' ), 'dashicons-email-alt' ),
		);
		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'maintenance';
		}
		?>
		<div class="wrap">
		<div class="ops">
			<div class="ops-hero">
				<div>
					<span class="ops-eyebrow"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Operaciones del sitio', 'wp-site-ops-toolkit' ); ?></span>
					<h1 class="ops-title">
						WP Site Ops Toolkit
						<span class="ops-version">v<?php echo esc_html( self::VERSION ); ?></span>
					</h1>
					<p class="ops-sub"><?php esc_html_e( 'Suite de mantenimiento, saneamiento de base de datos y depurador de correo.', 'wp-site-ops-toolkit' ); ?></p>
				</div>
				<div class="ops-pill">
					<span class="ops-dot <?php echo $maint_on ? 'ops-dot--off' : 'ops-dot--on'; ?>"></span>
					<?php echo $maint_on ? esc_html__( 'Sitio en mantenimiento', 'wp-site-ops-toolkit' ) : esc_html__( 'Sitio en línea', 'wp-site-ops-toolkit' ); ?>
				</div>
			</div>

			<nav class="ops-tabs" aria-label="<?php esc_attr_e( 'Secciones', 'wp-site-ops-toolkit' ); ?>">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>" class="ops-tab <?php echo $slug === $current_tab ? 'is-active' : ''; ?>"<?php echo $slug === $current_tab ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $tab[1] ); ?>"></span> <?php echo esc_html( $tab[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="ops-body">
				<?php if ( isset( $_GET['cleaned'] ) ) : ?>
					<div class="ops-alert ops-alert--ok">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php echo esc_html( sprintf( __( 'Limpieza ejecutada exitosamente: %d elementos eliminados.', 'wp-site-ops-toolkit' ), (int) $_GET['cleaned'] ) ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( 'maintenance' === $current_tab ) : ?>
					<!-- PESTAÑA 1: MANTENIMIENTO -->
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_site_ops_toolkit_group' ); ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>[db_cleaner_enabled]" value="<?php echo ! empty( $settings['db_cleaner_enabled'] ) ? '1' : '0'; ?>" />
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>[mail_debugger_enabled]" value="<?php echo ! empty( $settings['mail_debugger_enabled'] ) ? '1' : '0'; ?>" />
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>[mail_blackhole_mode]" value="<?php echo ! empty( $settings['mail_blackhole_mode'] ) ? '1' : '0'; ?>" />

						<div class="ops-card">
							<h3 class="ops-card-title"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Control de Mantenimiento y Staging Gate', 'wp-site-ops-toolkit' ); ?></h3>

							<div class="ops-item ops-item--danger">
								<input type="checkbox" id="ops_maint_enabled" name="<?php echo esc_attr( $key ); ?>[maintenance_enabled]" value="1" <?php checked( $settings['maintenance_enabled'] ); ?> />
								<div>
									<label for="ops_maint_enabled" class="ops-item-label"><?php esc_html_e( 'Activar Modo Mantenimiento (HTTP 503)', 'wp-site-ops-toolkit' ); ?></label>
									<p class="ops-item-desc"><?php esc_html_e( 'Los visitantes ven la pantalla de mantenimiento. Las IPs de la lista blanca y los usuarios con sesión pueden seguir navegando.', 'wp-site-ops-toolkit' ); ?></p>
								</div>
							</div>

							<div class="ops-field">
								<label for="ops_maint_title"><?php esc_html_e( 'Título en pantalla', 'wp-site-ops-toolkit' ); ?></label>
								<input type="text" id="ops_maint_title" name="<?php echo esc_attr( $key ); ?>[maintenance_title]" value="<?php echo esc_attr( $settings['maintenance_title'] ); ?>" />
							</div>

							<div class="ops-field">
								<label for="ops_maint_msg"><?php esc_html_e( 'Mensaje para visitantes', 'wp-site-ops-toolkit' ); ?></label>
								<textarea id="ops_maint_msg" name="<?php echo esc_attr( $key ); ?>[maintenance_message]" rows="4"><?php echo esc_textarea( $settings['maintenance_message'] ); ?></textarea>
							</div>

							<div class="ops-field">
								<label for="ops_maint_whitelist"><?php esc_html_e( 'Lista blanca de IPs (1 por línea)', 'wp-site-ops-toolkit' ); ?></label>
								<textarea id="ops_maint_whitelist" class="ops-mono" name="<?php echo esc_attr( $key ); ?>[maintenance_ip_whitelist]" rows="3" placeholder="127.0.0.1&#10;192.168.1.1"><?php echo esc_textarea( $settings['maintenance_ip_whitelist'] ); ?></textarea>
								<p class="ops-hint"><?php echo wp_kses( sprintf( __( 'Tu IP actual es: %s', 'wp-site-ops-toolkit' ), '<strong>' . esc_html( $my_ip ) . '</strong>' ), array( 'strong' => array() ) ); ?></p>
							</div>

							<div class="ops-field">
								<label for="ops_maint_secret"><?php esc_html_e( 'Clave secreta de acceso (Preview)', 'wp-site-ops-toolkit' ); ?></label>
								<input type="text" id="ops_maint_secret" name="<?php echo esc_attr( $key ); ?>[maintenance_secret_key]" value="<?php echo esc_attr( $settings['maintenance_secret_key'] ); ?>" placeholder="ej: cliente_demo_2026" />
								<?php if ( ! empty( $settings['maintenance_secret_key'] ) ) : ?>
									<p class="ops-hint">
										<?php esc_html_e( 'Enlace para clientes:', 'wp-site-ops-toolkit' ); ?>
										<code><?php echo esc_html( add_query_arg( 'ops_preview', $settings['maintenance_secret_key'], home_url() ) ); ?></code>
									</p>
								<?php endif; ?>
							</div>

							<div class="ops-actions">
								<?php submit_button( __( 'Guardar Mantenimiento', 'wp-site-ops-toolkit' ), 'primary ops-btn ops-btn--primary', 'submit', false ); ?>
							</div>
						</div>
					</form>

				<?php elseif ( 'db' === $current_tab ) : ?>
					<!-- PESTAÑA 2: LIMPIEZA DE BASE DE DATOS -->
					<div class="ops-kpis">
						<div class="ops-kpi">
							<span class="ops-kpi-icon"><span class="dashicons dashicons-clock"></span></span>
							<div><div class="ops-kpi-value"><?php echo esc_html( $db_counts['transients'] ); ?></div><div class="ops-kpi-label"><?php esc_html_e( 'Transients expirados', 'wp-site-ops-toolkit' ); ?></div></div>
						</div>
						<div class="ops-kpi ops-kpi--blue">
							<span class="ops-kpi-icon"><span class="dashicons dashicons-backup"></span></span>
							<div><div class="ops-kpi-value"><?php echo esc_html( $db_counts['revisions'] ); ?></div><div class="ops-kpi-label"><?php esc_html_e( 'Revisiones de posts', 'wp-site-ops-toolkit' ); ?></div></div>
						</div>
						<div class="ops-kpi ops-kpi--amber">
							<span class="ops-kpi-icon"><span class="dashicons dashicons-trash"></span></span>
							<div><div class="ops-kpi-value"><?php echo esc_html( $db_counts['trashed_posts'] ); ?></div><div class="ops-kpi-label"><?php esc_html_e( 'Entradas en papelera', 'wp-site-ops-toolkit' ); ?></div></div>
						</div>
						<div class="ops-kpi ops-kpi--red">
							<span class="ops-kpi-icon"><span class="dashicons dashicons-warning"></span></span>
							<div><div class="ops-kpi-value"><?php echo esc_html( $db_counts['spam_comments'] ); ?></div><div class="ops-kpi-label"><?php esc_html_e( 'Comentarios spam/papelera', 'wp-site-ops-toolkit' ); ?></div></div>
						</div>
						<div class="ops-kpi ops-kpi--green">
							<span class="ops-kpi-icon"><span class="dashicons dashicons-admin-generic"></span></span>
							<div><div class="ops-kpi-value"><?php echo esc_html( $db_counts['orphan_postmeta'] ); ?></div><div class="ops-kpi-label"><?php esc_html_e( 'Postmeta huérfano', 'wp-site-ops-toolkit' ); ?></div></div>
						</div>
					</div>

					<div class="ops-card">
						<h3 class="ops-card-title"><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Selecciona las áreas a optimizar', 'wp-site-ops-toolkit' ); ?></h3>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ops_cleanup_db" />
							<?php wp_nonce_field( 'ops_cleanup_db_nonce' ); ?>

							<?php
							$targets = array(
								'transients'      => __( 'Eliminar transients expirados de la tabla options', 'wp-site-ops-toolkit' ),
								'revisions'       => __( 'Purgar revisiones antiguas de entradas y páginas', 'wp-site-ops-toolkit' ),
								'trashed_posts'   => __( 'Vaciar papelera de entradas y páginas', 'wp-site-ops-toolkit' ),
								'spam_comments'   => __( 'Vaciar spam y comentarios eliminados', 'wp-site-ops-toolkit' ),
								'orphan_postmeta' => __( 'Limpiar metadatos huérfanos sin entrada padre', 'wp-site-ops-toolkit' ),
							);
							foreach ( $targets as $target => $label ) :
								?>
								<div class="ops-item">
									<input type="checkbox" id="ops_target_<?php echo esc_attr( $target ); ?>" name="targets[]" value="<?php echo esc_attr( $target ); ?>" checked />
									<div><label for="ops_target_<?php echo esc_attr( $target ); ?>" class="ops-item-label"><?php echo esc_html( $label ); ?></label></div>
								</div>
							<?php endforeach; ?>

							<div class="ops-actions">
								<button type="submit" class="ops-btn ops-btn--danger" onclick="return confirm('<?php echo esc_js( __( '¿Confirmas que deseas sanear la base de datos?', 'wp-site-ops-toolkit' ) ); ?>');">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Ejecutar Limpieza en 1 Clic', 'wp-site-ops-toolkit' ); ?>
								</button>
							</div>
						</form>
					</div>

				<?php elseif ( 'mail' === $current_tab ) : ?>
					<!-- PESTAÑA 3: MAIL DEBUGGER -->
					<div class="ops-card">
						<h3 class="ops-card-title"><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Ajustes del Capturador de Correo', 'wp-site-ops-toolkit' ); ?></h3>

						<form method="post" action="options.php">
							<?php settings_fields( 'wp_site_ops_toolkit_group' ); ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[maintenance_enabled]" value="<?php echo ! empty( $settings['maintenance_enabled'] ) ? '1' : '0'; ?>" />
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[maintenance_title]" value="<?php echo esc_attr( $settings['maintenance_title'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[maintenance_message]" value="<?php echo esc_attr( $settings['maintenance_message'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[maintenance_ip_whitelist]" value="<?php echo esc_attr( $settings['maintenance_ip_whitelist'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[maintenance_secret_key]" value="<?php echo esc_attr( $settings['maintenance_secret_key'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>[db_cleaner_enabled]" value="<?php echo ! empty( $settings['db_cleaner_enabled'] ) ? '1' : '0'; ?>" />

							<div class="ops-item">
								<input type="checkbox" id="ops_mail_enabled" name="<?php echo esc_attr( $key ); ?>[mail_debugger_enabled]" value="1" <?php checked( $settings['mail_debugger_enabled'] ); ?> />
								<div>
									<label for="ops_mail_enabled" class="ops-item-label"><?php esc_html_e( 'Activar registro de correos (wp_mail)', 'wp-site-ops-toolkit' ); ?></label>
									<p class="ops-item-desc"><?php esc_html_e( 'Guarda cada correo que envía WordPress para poder revisarlo aquí.', 'wp-site-ops-toolkit' ); ?></p>
								</div>
							</div>

							<div class="ops-item">
								<input type="checkbox" id="ops_mail_blackhole" name="<?php echo esc_attr( $key ); ?>[mail_blackhole_mode]" value="1" <?php checked( $settings['mail_blackhole_mode'] ); ?> />
								<div>
									<label for="ops_mail_blackhole" class="ops-item-label"><?php esc_html_e( 'Modo Simulación / Blackhole (No enviar al exterior)', 'wp-site-ops-toolkit' ); ?></label>
									<p class="ops-item-desc"><?php esc_html_e( 'Ideal para desarrollo local o staging. Registra los correos aquí sin enviarlos a destinatarios reales.', 'wp-site-ops-toolkit' ); ?></p>
								</div>
							</div>

							<div class="ops-actions">
								<?php submit_button( __( 'Guardar Ajustes de Correo', 'wp-site-ops-toolkit' ), 'primary ops-btn ops-btn--primary', 'submit', false ); ?>
							</div>
						</form>
					</div>

					<div class="ops-card">
						<div class="ops-flex" style="margin-bottom: 14px;">
							<h3 class="ops-card-title" style="margin: 0 !important;"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Últimos Correos Registrados', 'wp-site-ops-toolkit' ); ?></h3>

							<?php if ( ! empty( $mail_logs ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ops_clear_mail_log" />
									<?php wp_nonce_field( 'ops_clear_mail_nonce' ); ?>
									<button type="submit" class="ops-btn ops-btn--soft" onclick="return confirm('<?php echo esc_js( __( '¿Vaciar historial de correos?', 'wp-site-ops-toolkit' ) ); ?>');"><?php esc_html_e( 'Vaciar Registro', 'wp-site-ops-toolkit' ); ?></button>
								</form>
							<?php endif; ?>
						</div>

						<?php if ( empty( $mail_logs ) ) : ?>
							<p class="ops-empty"><?php esc_html_e( 'No hay correos registrados todavía.', 'wp-site-ops-toolkit' ); ?></p>
						<?php else : ?>
							<div class="ops-tablewrap">
								<table class="ops-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Fecha / Hora', 'wp-site-ops-toolkit' ); ?></th>
											<th><?php esc_html_e( 'Para', 'wp-site-ops-toolkit' ); ?></th>
											<th><?php esc_html_e( 'Asunto', 'wp-site-ops-toolkit' ); ?></th>
											<th><?php esc_html_e( 'Estado', 'wp-site-ops-toolkit' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $mail_logs as $entry ) : ?>
											<tr>
												<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
												<td><code><?php echo esc_html( $entry['to'] ); ?></code></td>
												<td><strong><?php echo esc_html( $entry['subject'] ); ?></strong></td>
												<td>
													<?php if ( 'simulated' === $entry['status'] ) : ?>
														<span class="ops-badge ops-badge--sim"><?php esc_html_e( 'Simulado', 'wp-site-ops-toolkit' ); ?></span>
													<?php elseif ( 'failed' === $entry['status'] ) : ?>
														<span class="ops-badge ops-badge--fail"><?php esc_html_e( 'Fallido', 'wp-site-ops-toolkit' ); ?></span>
													<?php else : ?>
														<span class="ops-badge ops-badge--ok"><?php esc_html_e( 'Enviado', 'wp-site-ops-toolkit' ); ?></span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		</div>
		<?php
	}
}
