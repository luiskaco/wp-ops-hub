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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Encola estilos y scripts solo en la página de configuración del plugin.
	 *
	 * @param string $hook_suffix Hook de la pantalla actual en wp-admin.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'tools_page_wp-mcp-agent-bridge' !== $hook_suffix ) {
			return;
		}

		$base_url = plugin_dir_url( WP_MCP_AGENT_BRIDGE_FILE );

		wp_enqueue_style(
			'wp-mcp-agent-bridge-admin',
			$base_url . 'assets/admin.css',
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'wp-mcp-agent-bridge-admin',
			$base_url . 'assets/admin.js',
			array(),
			self::VERSION,
			true
		);
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
	 * Renderiza la interfaz del panel con un diseño limpio y moderno.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$endpoint = rest_url( 'mcp/v1/rpc' );
		$tools    = self::get_active_tools();
		$logs     = get_option( self::LOG_KEY, array() );
		$user     = wp_get_current_user();

		// JSON para clientes (la credencial Base64 se completa en el navegador si el usuario pega su Application Password)
		$mcp_json_snippet = array(
			'mcpServers' => array(
				'wordpress' => array(
					'url'     => $endpoint,
					'headers' => array(
						'Authorization' => 'Basic <BASE64_DE_USUARIO_Y_PASSWORD>',
					),
				),
			),
		);
		$json_str = wp_json_encode( $mcp_json_snippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$modules_info = array(
			'core'        => array(
				'name' => __( 'Núcleo (Core)', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Entradas, páginas, Custom Post Types, taxonomías y plugins.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-admin-post',
				'tag'  => __( 'Base', 'wp-mcp-agent-bridge' ),
			),
			'media'       => array(
				'name' => __( 'Biblioteca de Medios', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Gestión y subida de imágenes por URL remota y Base64.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-admin-media',
				'tag'  => __( 'Archivos', 'wp-mcp-agent-bridge' ),
			),
			'woocommerce' => array(
				'name' => __( 'WooCommerce', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Productos, inventario, precios, pedidos y clientes.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-cart',
				'tag'  => __( 'Tienda', 'wp-mcp-agent-bridge' ),
			),
			'seo'         => array(
				'name' => __( 'SEO Multigestor', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Lectura y optimización con Yoast, Rank Math y AIOSEO.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-search',
				'tag'  => __( 'Metadatos', 'wp-mcp-agent-bridge' ),
			),
			'cache'       => array(
				'name' => __( 'Caché & Rendimiento', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Purga de LiteSpeed, WP Rocket, WP Super Cache y W3TC.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-performance',
				'tag'  => __( 'Velocidad', 'wp-mcp-agent-bridge' ),
			),
		);
		?>
		<div class="wrap mcp-wrap">

			<div class="mcp-header-card">
				<div>
					<div class="mcp-header-title">
						<span class="dashicons dashicons-rest-api" style="font-size: 28px; width: 28px; height: 28px; color: #72aee6;"></span>
						<h1>WP MCP Agent Bridge</h1>
						<span class="mcp-badge">v<?php echo esc_html( self::VERSION ); ?></span>
					</div>
					<p style="margin: 6px 0 0; color: #c3c4c7; font-size: 13px;">
						<?php esc_html_e( 'Servidor nativo Model Context Protocol para conectar asistentes de IA (Cursor, Antigravity, Claude, VS Code).', 'wp-mcp-agent-bridge' ); ?>
					</p>
				</div>
				<div>
					<div class="mcp-status-pill">
						<span class="mcp-dot <?php echo ! empty( $settings['enabled'] ) ? 'mcp-dot-online' : 'mcp-dot-offline'; ?>"></span>
						<span><?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Servidor Activo', 'wp-mcp-agent-bridge' ) : esc_html__( 'Servidor Inactivo', 'wp-mcp-agent-bridge' ); ?></span>
					</div>
				</div>
			</div>

			<div class="mcp-layout">
				<!-- COLUMNA IZQUIERDA: Formulario de Configuración -->
				<div>
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_mcp_agent_bridge_group' ); ?>

						<!-- Caja 1: Estado y Gobernanza -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-shield"></span>
								<?php esc_html_e( '1. Estado y Políticas de Seguridad', 'wp-mcp-agent-bridge' ); ?>
							</h3>

							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<div>
									<label for="mcp_enabled" class="mcp-card-label"><?php esc_html_e( 'Habilitar servidor MCP', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite atender solicitudes JSON-RPC en /wp-json/mcp/v1/rpc autenticadas con Application Passwords.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_allow_writes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_writes]" value="1" <?php checked( $settings['allow_writes'] ); ?> />
								<div>
									<label for="mcp_allow_writes" class="mcp-card-label"><?php esc_html_e( 'Permitir operaciones de escritura', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite a la IA crear o modificar contenido (posts, productos, subir medios). Si está desmarcado, el servidor opera en modo solo lectura.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-card-item" style="border-left: 3px solid #d63638;">
								<input type="checkbox" id="mcp_allow_deletes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_deletes]" value="1" <?php checked( $settings['allow_deletes'] ); ?> />
								<div>
									<label for="mcp_allow_deletes" class="mcp-card-label" style="color: #d63638;"><?php esc_html_e( 'Permitir operaciones destructivas (Eliminar)', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite a la IA enviar contenido a la papelera o eliminarlo permanentemente. Se recomienda mantener deshabilitado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Caja 2: Módulos de Herramientas -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-screenoptions"></span>
								<?php esc_html_e( '2. Módulos y Herramientas Habilitadas', 'wp-mcp-agent-bridge' ); ?>
							</h3>
							<p style="font-size: 12px; color: #646970; margin-top: 0; margin-bottom: 14px;">
								<?php esc_html_e( 'Activa o desactiva qué áreas del sitio puede operar el asistente de IA.', 'wp-mcp-agent-bridge' ); ?>
							</p>

							<?php foreach ( $modules_info as $mod_key => $mod_data ) : ?>
								<div class="mcp-card-item">
									<input type="checkbox" id="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[modules][<?php echo esc_attr( $mod_key ); ?>]" value="1" <?php checked( ! empty( $settings['modules'][ $mod_key ] ) ); ?> />
									<div>
										<label for="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" class="mcp-card-label">
											<span class="dashicons <?php echo esc_attr( $mod_data['icon'] ); ?>" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top; margin-right: 4px; color: #2271b1;"></span>
											<?php echo esc_html( $mod_data['name'] ); ?>
											<span class="mcp-tag"><?php echo esc_html( $mod_data['tag'] ); ?></span>
										</label>
										<p class="mcp-card-desc"><?php echo esc_html( $mod_data['desc'] ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Caja 3: Auditoría -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-list-view"></span>
								<?php esc_html_e( '3. Auditoría de Actividad', 'wp-mcp-agent-bridge' ); ?>
							</h3>
							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_activity_log" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[activity_log_enabled]" value="1" <?php checked( $settings['activity_log_enabled'] ); ?> />
								<div>
									<label for="mcp_activity_log" class="mcp-card-label"><?php esc_html_e( 'Registrar llamadas de herramientas', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Guarda de forma rotativa las últimas 50 llamadas ejecutadas por la IA con usuario, fecha y resultado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<div style="margin-top: 15px;">
							<?php submit_button( __( 'Guardar Configuración', 'wp-mcp-agent-bridge' ), 'primary', 'submit', false, array( 'class' => 'button button-primary mcp-btn-submit' ) ); ?>
						</div>
					</form>
				</div>

				<!-- COLUMNA DERECHA: Conexión con Clientes -->
				<div>
					<div class="mcp-box" style="border-top: 3px solid #2271b1;">
						<h3 class="mcp-box-title">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'Conexión con Asistentes IA', 'wp-mcp-agent-bridge' ); ?>
						</h3>

						<label style="font-size: 12px; font-weight: 600; color: #50575e;"><?php esc_html_e( 'URL del Endpoint MCP (HTTP JSON-RPC):', 'wp-mcp-agent-bridge' ); ?></label>
						<input type="text" class="mcp-endpoint-input" readonly value="<?php echo esc_attr( $endpoint ); ?>" />

						<label for="mcp_app_password_input" style="font-size: 12px; font-weight: 600; color: #50575e; display: block;">
							<?php
							printf(
								/* translators: %s: nombre de usuario actual */
								esc_html__( 'Contraseña de aplicación para "%s" (solo local, no se guarda):', 'wp-mcp-agent-bridge' ),
								esc_html( $user->user_login )
							);
							?>
						</label>
						<input type="text" id="mcp_app_password_input" class="mcp-endpoint-input" autocomplete="off" spellcheck="false" data-username="<?php echo esc_attr( $user->user_login ); ?>" data-endpoint="<?php echo esc_attr( $endpoint ); ?>" placeholder="xxxx xxxx xxxx xxxx" />

						<div style="display: flex; justify-content: space-between; align-items: center; margin: 12px 0 6px;">
							<span style="font-size: 12px; font-weight: 600; color: #50575e;"><?php esc_html_e( 'Configuración JSON (.cursor/mcp.json):', 'wp-mcp-agent-bridge' ); ?></span>
							<button type="button" id="mcp_copy_json_btn" class="mcp-copy-btn" data-copied-label="<?php esc_attr_e( '¡Copiado!', 'wp-mcp-agent-bridge' ); ?>">
								<?php esc_html_e( 'Copiar JSON', 'wp-mcp-agent-bridge' ); ?>
							</button>
						</div>

						<textarea id="mcp_json_snippet" readonly style="width: 100%; height: 160px; font-family: monospace; font-size: 11.5px; background: #1e1e1e; color: #9cdcfe; border-radius: 4px; padding: 10px;"><?php echo esc_textarea( $json_str ); ?></textarea>

						<div style="background: #eef2fa; border-left: 3px solid #2271b1; padding: 10px 12px; border-radius: 4px; margin-top: 14px; font-size: 12px; color: #1a4282;">
							<strong><?php esc_html_e( 'Instrucciones:', 'wp-mcp-agent-bridge' ); ?></strong>
							<ol style="margin: 6px 0 0 16px; padding: 0;">
								<li><?php esc_html_e( 'Ve a Usuarios > Perfil y crea una "Contraseña de aplicación" (formato xxxx xxxx xxxx xxxx).', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Pégala en el campo de arriba: el JSON calculará el encabezado Basic Base64 automáticamente.', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Haz clic en "Copiar JSON" y pégalo en Cursor, Windsurf, Claude o Antigravity.', 'wp-mcp-agent-bridge' ); ?></li>
							</ol>
						</div>

						<div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12.5px; color: #646970;">
							<span><?php esc_html_e( 'Herramientas expuestas actualmente:', 'wp-mcp-agent-bridge' ); ?></span>
							<strong style="color: #2271b1; font-size: 14px; float: right;"><?php echo count( $tools ); ?></strong>
						</div>
					</div>
				</div>
			</div>

			<!-- Registro de Auditoría Inferior -->
			<div class="mcp-box" style="margin-top: 24px;">
				<h3 class="mcp-box-title">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Registro en Vivo de Llamadas MCP', 'wp-mcp-agent-bridge' ); ?>
				</h3>

				<?php if ( empty( $logs ) ) : ?>
					<p style="font-size: 13px; color: #646970; font-style: italic; margin: 10px 0;">
						<?php esc_html_e( 'No hay llamadas registradas todavía. Cuando tu asistente de IA ejecute una herramienta, aparecerá aquí en tiempo real.', 'wp-mcp-agent-bridge' ); ?>
					</p>
				<?php else : ?>
					<table class="mcp-table">
						<thead>
							<tr>
								<th style="width: 160px;"><?php esc_html_e( 'Fecha / Hora', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 180px;"><?php esc_html_e( 'Herramienta', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Usuario', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Estado', 'wp-mcp-agent-bridge' ); ?></th>
								<th><?php esc_html_e( 'Detalle / Mensaje', 'wp-mcp-agent-bridge' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $logs, 0, 15 ) as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['timestamp'] ); ?></td>
									<td><code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-weight: 600;"><?php echo esc_html( $log['tool'] ); ?></code></td>
									<td><?php echo esc_html( $log['user_name'] ); ?></td>
									<td>
										<?php if ( ! empty( $log['success'] ) ) : ?>
											<span class="mcp-badge-ok">✔ OK</span>
										<?php else : ?>
											<span class="mcp-badge-err">✖ Error</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
