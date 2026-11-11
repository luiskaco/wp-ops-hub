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
	 * Renderiza la interfaz administrativa con diseño moderno por pestañas.
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
		?>
		<div class="wrap ops-wrap">
			<style>
				.ops-wrap {
					max-width: 1100px;
					margin: 20px auto 40px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				}
				.ops-header {
					background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
					color: #fff;
					padding: 24px 30px;
					border-radius: 8px 8px 0 0;
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				.ops-header h1 {
					color: #fff;
					margin: 0;
					font-size: 22px;
					font-weight: 700;
					display: flex;
					align-items: center;
					gap: 10px;
				}
				.ops-tabs {
					display: flex;
					background: #fff;
					border: 1px solid #cbd5e1;
					border-top: none;
					border-bottom: 2px solid #0284c7;
					padding: 0 20px;
				}
				.ops-tab {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					padding: 14px 20px;
					font-size: 14px;
					font-weight: 600;
					color: #64748b;
					text-decoration: none;
					border-bottom: 3px solid transparent;
					transition: all 0.15s;
				}
				.ops-tab:hover {
					color: #0284c7;
				}
				.ops-tab.active {
					color: #0284c7;
					border-bottom-color: #0284c7;
				}
				.ops-content {
					background: #f8fafc;
					border: 1px solid #cbd5e1;
					border-top: none;
					padding: 28px;
					border-radius: 0 0 8px 8px;
				}
				.ops-card {
					background: #fff;
					border: 1px solid #e2e8f0;
					border-radius: 8px;
					padding: 24px;
					margin-bottom: 24px;
					box-shadow: 0 1px 3px rgba(0,0,0,0.03);
				}
				.ops-card-title {
					margin: 0 0 16px;
					padding-bottom: 10px;
					border-bottom: 2px solid #f1f5f9;
					font-size: 16px;
					font-weight: 600;
					color: #0f172a;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.ops-metric-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 16px;
					margin-bottom: 24px;
				}
				.ops-metric-box {
					background: #f8fafc;
					border: 1px solid #e2e8f0;
					border-radius: 6px;
					padding: 16px;
					text-align: center;
				}
				.ops-metric-num {
					font-size: 26px;
					font-weight: 700;
					color: #0284c7;
					margin-bottom: 4px;
				}
				.ops-metric-label {
					font-size: 12px;
					color: #64748b;
					font-weight: 600;
				}
				.ops-table {
					width: 100%;
					border-collapse: collapse;
					font-size: 13px;
				}
				.ops-table th, .ops-table td {
					padding: 10px 14px;
					border-bottom: 1px solid #f1f5f9;
					text-align: left;
				}
				.ops-table th {
					background: #f8fafc;
					font-weight: 600;
					color: #475569;
				}
				.ops-badge-ok { background: #dcfce7; color: #15803d; font-weight: 600; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
				.ops-badge-sim { background: #e0f2fe; color: #0369a1; font-weight: 600; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
				.ops-badge-fail { background: #fee2e2; color: #b91c1c; font-weight: 600; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
			</style>

			<div class="ops-header">
				<div>
					<h1>
						<span class="dashicons dashicons-admin-tools" style="font-size: 26px; width: 26px; height: 26px; color: #38bdf8;"></span>
						WP Site Ops Toolkit
						<span style="font-size: 11px; background: #0284c7; padding: 2px 8px; border-radius: 12px; font-weight: 600;">v<?php echo esc_html( self::VERSION ); ?></span>
					</h1>
					<p style="margin: 6px 0 0; color: #94a3b8; font-size: 13px;">
						<?php esc_html_e( 'Suite de mantenimiento, saneamiento de base de datos y depurador de correo.', 'wp-site-ops-toolkit' ); ?>
					</p>
				</div>
			</div>

			<!-- Pestañas de Navegación -->
			<div class="ops-tabs">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'maintenance' ) ); ?>" class="ops-tab <?php echo 'maintenance' === $current_tab ? 'active' : ''; ?>">
					<span class="dashicons dashicons-hammer"></span> <?php esc_html_e( 'Modo Mantenimiento', 'wp-site-ops-toolkit' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'db' ) ); ?>" class="ops-tab <?php echo 'db' === $current_tab ? 'active' : ''; ?>">
					<span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Limpieza de BD', 'wp-site-ops-toolkit' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'mail' ) ); ?>" class="ops-tab <?php echo 'mail' === $current_tab ? 'active' : ''; ?>">
					<span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Mail Debugger', 'wp-site-ops-toolkit' ); ?>
				</a>
			</div>

			<div class="ops-content">
				<?php if ( isset( $_GET['cleaned'] ) ) : ?>
					<div class="notice notice-success is-dismissible" style="margin: 0 0 20px;">
						<p><strong><?php echo sprintf( esc_html__( 'Limpieza ejecutada exitosamente: %d elementos eliminados.', 'wp-site-ops-toolkit' ), (int) $_GET['cleaned'] ); ?></strong></p>
					</div>
				<?php endif; ?>

				<?php if ( 'maintenance' === $current_tab ) : ?>
					<!-- PESTAÑA 1: MANTENIMIENTO -->
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_site_ops_toolkit_group' ); ?>
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[db_cleaner_enabled]" value="<?php echo ! empty( $settings['db_cleaner_enabled'] ) ? '1' : '0'; ?>" />
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mail_debugger_enabled]" value="<?php echo ! empty( $settings['mail_debugger_enabled'] ) ? '1' : '0'; ?>" />
						<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mail_blackhole_mode]" value="<?php echo ! empty( $settings['mail_blackhole_mode'] ) ? '1' : '0'; ?>" />

						<div class="ops-card">
							<h3 class="ops-card-title"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Control de Mantenimiento y Staging Gate', 'wp-site-ops-toolkit' ); ?></h3>

							<label style="display: block; margin-bottom: 16px; font-weight: 600; font-size: 14px;">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_enabled]" value="1" <?php checked( $settings['maintenance_enabled'] ); ?> style="transform: scale(1.2); margin-right: 8px;" />
								<?php esc_html_e( 'Activar Modo Mantenimiento (HTTP 503)', 'wp-site-ops-toolkit' ); ?>
							</label>

							<table class="form-table">
								<tr>
									<th><label for="ops_maint_title"><?php esc_html_e( 'Título en pantalla', 'wp-site-ops-toolkit' ); ?></label></th>
									<td>
										<input type="text" id="ops_maint_title" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_title]" value="<?php echo esc_attr( $settings['maintenance_title'] ); ?>" class="regular-text" />
									</td>
								</tr>
								<tr>
									<th><label for="ops_maint_msg"><?php esc_html_e( 'Mensaje para visitantes', 'wp-site-ops-toolkit' ); ?></label></th>
									<td>
										<textarea id="ops_maint_msg" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_message]" rows="4" class="large-text"><?php echo esc_textarea( $settings['maintenance_message'] ); ?></textarea>
									</td>
								</tr>
								<tr>
									<th><label for="ops_maint_whitelist"><?php esc_html_e( 'Lista blanca de IPs (1 por línea)', 'wp-site-ops-toolkit' ); ?></label></th>
									<td>
										<textarea id="ops_maint_whitelist" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_ip_whitelist]" rows="3" class="large-text" placeholder="127.0.0.1&#10;192.168.1.1"><?php echo esc_textarea( $settings['maintenance_ip_whitelist'] ); ?></textarea>
										<p class="description"><?php echo sprintf( esc_html__( 'Tu IP actual es: %s', 'wp-site-ops-toolkit' ), '<strong>' . esc_html( $my_ip ) . '</strong>' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><label for="ops_maint_secret"><?php esc_html_e( 'Clave secreta de acceso (Preview)', 'wp-site-ops-toolkit' ); ?></label></th>
									<td>
										<input type="text" id="ops_maint_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_secret_key]" value="<?php echo esc_attr( $settings['maintenance_secret_key'] ); ?>" class="regular-text" placeholder="ej: cliente_demo_2026" />
										<?php if ( ! empty( $settings['maintenance_secret_key'] ) ) : ?>
											<p class="description">
												<?php esc_html_e( 'Enlace para clientes:', 'wp-site-ops-toolkit' ); ?>
												<code><?php echo esc_html( add_query_arg( 'ops_preview', $settings['maintenance_secret_key'], home_url() ) ); ?></code>
											</p>
										<?php endif; ?>
									</td>
								</tr>
							</table>

							<?php submit_button( __( 'Guardar Mantenimiento', 'wp-site-ops-toolkit' ) ); ?>
						</div>
					</form>

				<?php elseif ( 'db' === $current_tab ) : ?>
					<!-- PESTAÑA 2: LIMPIEZA DE BASE DE DATOS -->
					<div class="ops-card">
						<h3 class="ops-card-title"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Métricas de la Base de Datos', 'wp-site-ops-toolkit' ); ?></h3>
						<div class="ops-metric-grid">
							<div class="ops-metric-box">
								<div class="ops-metric-num"><?php echo esc_html( $db_counts['transients'] ); ?></div>
								<div class="ops-metric-label"><?php esc_html_e( 'Transients Expirados', 'wp-site-ops-toolkit' ); ?></div>
							</div>
							<div class="ops-metric-box">
								<div class="ops-metric-num"><?php echo esc_html( $db_counts['revisions'] ); ?></div>
								<div class="ops-metric-label"><?php esc_html_e( 'Revisiones de Posts', 'wp-site-ops-toolkit' ); ?></div>
							</div>
							<div class="ops-metric-box">
								<div class="ops-metric-num"><?php echo esc_html( $db_counts['trashed_posts'] ); ?></div>
								<div class="ops-metric-label"><?php esc_html_e( 'Entradas en Papelera', 'wp-site-ops-toolkit' ); ?></div>
							</div>
							<div class="ops-metric-box">
								<div class="ops-metric-num"><?php echo esc_html( $db_counts['spam_comments'] ); ?></div>
								<div class="ops-metric-label"><?php esc_html_e( 'Comentarios Spam/Papelera', 'wp-site-ops-toolkit' ); ?></div>
							</div>
							<div class="ops-metric-box">
								<div class="ops-metric-num"><?php echo esc_html( $db_counts['orphan_postmeta'] ); ?></div>
								<div class="ops-metric-label"><?php esc_html_e( 'Postmeta Huérfano', 'wp-site-ops-toolkit' ); ?></div>
							</div>
						</div>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ops_cleanup_db" />
							<?php wp_nonce_field( 'ops_cleanup_db_nonce' ); ?>

							<h4><?php esc_html_e( 'Selecciona las áreas a optimizar:', 'wp-site-ops-toolkit' ); ?></h4>
							<p>
								<label><input type="checkbox" name="targets[]" value="transients" checked /> <?php esc_html_e( 'Eliminar transients expirados de la tabla options', 'wp-site-ops-toolkit' ); ?></label><br>
								<label><input type="checkbox" name="targets[]" value="revisions" checked /> <?php esc_html_e( 'Purgar revisiones antiguas de entradas y páginas', 'wp-site-ops-toolkit' ); ?></label><br>
								<label><input type="checkbox" name="targets[]" value="trashed_posts" checked /> <?php esc_html_e( 'Vaciar papelera de entradas y páginas', 'wp-site-ops-toolkit' ); ?></label><br>
								<label><input type="checkbox" name="targets[]" value="spam_comments" checked /> <?php esc_html_e( 'Vaciar spam y comentarios eliminados', 'wp-site-ops-toolkit' ); ?></label><br>
								<label><input type="checkbox" name="targets[]" value="orphan_postmeta" checked /> <?php esc_html_e( 'Limpiar metadatos huérfanos sin entrada padre', 'wp-site-ops-toolkit' ); ?></label>
							</p>

							<button type="submit" class="button button-primary" onclick="return confirm('¿Confirmas que deseas sanear la base de datos?');" style="padding: 6px 16px; font-weight: 600;">
								<span class="dashicons dashicons-trash" style="vertical-align: text-top; margin-right: 4px;"></span>
								<?php esc_html_e( 'Ejecutar Limpieza en 1 Clic', 'wp-site-ops-toolkit' ); ?>
							</button>
						</form>
					</div>

				<?php elseif ( 'mail' === $current_tab ) : ?>
					<!-- PESTAÑA 3: MAIL DEBUGGER -->
					<div class="ops-card">
						<h3 class="ops-card-title"><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Ajustes del Capturador de Correo', 'wp-site-ops-toolkit' ); ?></h3>

						<form method="post" action="options.php">
							<?php settings_fields( 'wp_site_ops_toolkit_group' ); ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_enabled]" value="<?php echo ! empty( $settings['maintenance_enabled'] ) ? '1' : '0'; ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_title]" value="<?php echo esc_attr( $settings['maintenance_title'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_message]" value="<?php echo esc_attr( $settings['maintenance_message'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_ip_whitelist]" value="<?php echo esc_attr( $settings['maintenance_ip_whitelist'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[maintenance_secret_key]" value="<?php echo esc_attr( $settings['maintenance_secret_key'] ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[db_cleaner_enabled]" value="<?php echo ! empty( $settings['db_cleaner_enabled'] ) ? '1' : '0'; ?>" />

							<label style="display: block; margin-bottom: 12px;">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mail_debugger_enabled]" value="1" <?php checked( $settings['mail_debugger_enabled'] ); ?> />
								<strong><?php esc_html_e( 'Activar registro de correos (wp_mail)', 'wp-site-ops-toolkit' ); ?></strong>
							</label>

							<label style="display: block; margin-bottom: 16px; color: #b45309;">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mail_blackhole_mode]" value="1" <?php checked( $settings['mail_blackhole_mode'] ); ?> />
								<strong><?php esc_html_e( 'Modo Simulación / Blackhole (No enviar al exterior)', 'wp-site-ops-toolkit' ); ?></strong>
								<span style="font-size: 12px; display: block; color: #64748b;"><?php esc_html_e( 'Ideal para desarrollo local o staging. Registra los correos aquí sin enviarlos a destinatarios reales.', 'wp-site-ops-toolkit' ); ?></span>
							</label>

							<?php submit_button( __( 'Guardar Ajustes de Correo', 'wp-site-ops-toolkit' ) ); ?>
						</form>
					</div>

					<div class="ops-card">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
							<h3 class="ops-card-title" style="margin: 0; padding: 0; border: none;"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Últimos Correos Registrados', 'wp-site-ops-toolkit' ); ?></h3>

							<?php if ( ! empty( $mail_logs ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ops_clear_mail_log" />
									<?php wp_nonce_field( 'ops_clear_mail_nonce' ); ?>
									<button type="submit" class="button button-secondary" onclick="return confirm('¿Vaciar historial de correos?');"><?php esc_html_e( 'Vaciar Registro', 'wp-site-ops-toolkit' ); ?></button>
								</form>
							<?php endif; ?>
						</div>

						<?php if ( empty( $mail_logs ) ) : ?>
							<p style="color: #64748b; font-style: italic;"><?php esc_html_e( 'No hay correos registrados todavía.', 'wp-site-ops-toolkit' ); ?></p>
						<?php else : ?>
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
													<span class="ops-badge-sim"><?php esc_html_e( 'Simulado', 'wp-site-ops-toolkit' ); ?></span>
												<?php elseif ( 'failed' === $entry['status'] ) : ?>
													<span class="ops-badge-fail"><?php esc_html_e( 'Fallido', 'wp-site-ops-toolkit' ); ?></span>
												<?php else : ?>
													<span class="ops-badge-ok"><?php esc_html_e( 'Enviado', 'wp-site-ops-toolkit' ); ?></span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
