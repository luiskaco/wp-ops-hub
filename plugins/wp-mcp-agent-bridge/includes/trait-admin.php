<?php
/**
 * Panel de administración y generador de configuración para clientes IA.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Admin {

	/**
	 * Registra el menú de administración.
	 *
	 * @return void
	 */
	public static function init_admin() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_admin_settings' ) );
	}

	/**
	 * Agrega la página bajo Herramientas.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_management_page(
			__( 'WP MCP Bridge', 'wp-mcp-agent-bridge' ),
			__( 'WP MCP Bridge', 'wp-mcp-agent-bridge' ),
			'manage_options',
			'wp-mcp-agent-bridge',
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
			'wp_mcp_agent_bridge_group',
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
		$defaults = self::get_default_settings();
		$clean    = array();

		$clean['enabled']              = ! empty( $input['enabled'] );
		$clean['allow_writes']         = ! empty( $input['allow_writes'] );
		$clean['allow_deletes']        = ! empty( $input['allow_deletes'] );
		$clean['activity_log_enabled'] = ! empty( $input['activity_log_enabled'] );

		$clean['modules'] = array();
		foreach ( array_keys( $defaults['modules'] ) as $mod ) {
			$clean['modules'][ $mod ] = ! empty( $input['modules'][ $mod ] );
		}

		return $clean;
	}

	/**
	 * Renderiza la interfaz del panel.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$endpoint = rest_url( 'mcp/v1' );
		$tools    = self::get_active_tools();
		$logs     = get_option( self::LOG_KEY, array() );
		$user     = wp_get_current_user();

		// Generar JSON para Cursor / Claude
		$mcp_json_snippet = array(
			'mcpServers' => array(
				'wordpress' => array(
					'url'     => $endpoint,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $user->user_login . ':TU_APPLICATION_PASSWORD_AQUI' ),
					),
				),
			),
		);
		$json_str = wp_json_encode( $mcp_json_snippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP MCP Agent Bridge — Conexión para Asistentes de IA', 'wp-mcp-agent-bridge' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Conecta cualquier asistente de IA (Cursor, Antigravity, Claude Desktop, VS Code) a tu WordPress mediante el estándar nativo Model Context Protocol (MCP).', 'wp-mcp-agent-bridge' ); ?>
			</p>

			<div style="display: flex; gap: 20px; margin-top: 20px;">
				<div style="flex: 1; max-width: 600px;">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'wp_mcp_agent_bridge_group' );
						?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Estado del Servidor MCP', 'wp-mcp-agent-bridge' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
										<strong><?php esc_html_e( 'Habilitar servidor MCP', 'wp-mcp-agent-bridge' ); ?></strong>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Políticas de Seguridad', 'wp-mcp-agent-bridge' ); ?></th>
								<td>
									<label style="display:block; margin-bottom: 8px;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_writes]" value="1" <?php checked( $settings['allow_writes'] ); ?> />
										<?php esc_html_e( 'Permitir operaciones de escritura (crear y editar posts, productos, medios)', 'wp-mcp-agent-bridge' ); ?>
									</label>
									<label style="display:block;">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_deletes]" value="1" <?php checked( $settings['allow_deletes'] ); ?> />
										<strong style="color: #d63638;"><?php esc_html_e( 'Permitir operaciones destructivas (eliminar posts o productos permanentemente)', 'wp-mcp-agent-bridge' ); ?></strong>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Módulos Activos', 'wp-mcp-agent-bridge' ); ?></th>
								<td>
									<?php foreach ( $settings['modules'] as $mod_key => $is_active ) : ?>
										<label style="display:inline-block; margin-right: 15px; margin-bottom: 5px;">
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[modules][<?php echo esc_attr( $mod_key ); ?>]" value="1" <?php checked( $is_active ); ?> />
											<?php echo esc_html( ucfirst( $mod_key ) ); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Registro de Auditoría', 'wp-mcp-agent-bridge' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[activity_log_enabled]" value="1" <?php checked( $settings['activity_log_enabled'] ); ?> />
										<?php esc_html_e( 'Guardar las últimas 50 llamadas de herramientas para auditoría', 'wp-mcp-agent-bridge' ); ?>
									</label>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Guardar Cambios', 'wp-mcp-agent-bridge' ) ); ?>
					</form>
				</div>

				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
					<h2><?php esc_html_e( 'Configuración para Clientes MCP', 'wp-mcp-agent-bridge' ); ?></h2>
					<p><strong>Endpoint URL:</strong> <code><?php echo esc_html( $endpoint ); ?></code></p>
					<p><strong>Herramientas expuestas actualmente:</strong> <?php echo count( $tools ); ?></p>

					<h3><?php esc_html_e( 'Snippet para Cursor / Claude Desktop / Antigravity:', 'wp-mcp-agent-bridge' ); ?></h3>
					<textarea readonly style="width: 100%; height: 160px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $json_str ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Crea una Application Password en Usuarios > Tu Perfil y reemplaza TU_APPLICATION_PASSWORD_AQUI.', 'wp-mcp-agent-bridge' ); ?>
					</p>
				</div>
			</div>

			<hr style="margin: 30px 0;" />

			<h2><?php esc_html_e( 'Última Actividad de Herramientas MCP', 'wp-mcp-agent-bridge' ); ?></h2>
			<?php if ( empty( $logs ) ) : ?>
				<p><em><?php esc_html_e( 'No hay llamadas registradas todavía.', 'wp-mcp-agent-bridge' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width: 1000px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Fecha/Hora', 'wp-mcp-agent-bridge' ); ?></th>
							<th><?php esc_html_e( 'Herramienta', 'wp-mcp-agent-bridge' ); ?></th>
							<th><?php esc_html_e( 'Usuario', 'wp-mcp-agent-bridge' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'wp-mcp-agent-bridge' ); ?></th>
							<th><?php esc_html_e( 'Detalle', 'wp-mcp-agent-bridge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $logs, 0, 15 ) as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['timestamp'] ); ?></td>
								<td><code><?php echo esc_html( $log['tool'] ); ?></code></td>
								<td><?php echo esc_html( $log['user_name'] ); ?></td>
								<td>
									<?php if ( ! empty( $log['success'] ) ) : ?>
										<span style="color: #46b450; font-weight: bold;">✔ OK</span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: bold;">✖ Error</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
